<?php

namespace App\Providers;

use App\Database\PostgresConnection;
use App\Enums\PaymentStatus;
use App\Events\Maintenance\MaintenanceTicketCreated;
use App\Events\Maintenance\MaintenanceTicketUpdated;
use App\Events\Payment\PaymentStatusChanged;
use App\Events\Reminder\InvoiceReminderDispatched;
use App\Models\MaintenanceTicket;
use App\Models\Setting;
use App\Notifications\RentReminder;
use App\Notifications\TenantPortalNotification;
use App\Services\Settings\SettingManager;
use App\Services\WhatsAppManager;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingManager::class);
        $this->app->singleton(MailManager::class);
        $this->app->singleton(WhatsAppManager::class);

        Connection::resolverFor('pgsql', fn ($pdo, $database, $prefix, $config) => new PostgresConnection($pdo, $database, $prefix, $config));
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthEvents();
        $this->configureMail();
        $this->configureDomainEvents();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureAuthEvents(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            $event->user->forceFill(['last_login_at' => now()])->save();
        });
    }

    protected function configureMail(): void
    {
        try {
            $config = Setting::effectiveMailConfig();
        } catch (QueryException) {
            return;
        }

        config()->set('mail.default', $config['driver'] ?? 'log');

        config()->set('mail.mailers.smtp.host', $config['host'] ?? '');
        config()->set('mail.mailers.smtp.port', $config['port'] ?? 587);
        config()->set('mail.mailers.smtp.username', $config['username'] ?? '');
        config()->set('mail.mailers.smtp.password', $config['password'] ?? '');
        $encryption = $config['encryption'] ?? null;
        if ($encryption === 'null') {
            $encryption = null;
        }
        config()->set('mail.mailers.smtp.encryption', $encryption);

        if ($fromAddress = $config['from_address'] ?? null) {
            config()->set('mail.from.address', $fromAddress);
            config()->set('mail.from.name', $config['from_name'] ?? '');
        }
    }

    protected function configureDomainEvents(): void
    {
        Event::listen(MaintenanceTicketCreated::class, function (MaintenanceTicketCreated $event): void {
            $this->notifyMaintenanceTenant($event->ticket, 'maintenance_created');
        });

        Event::listen(MaintenanceTicketUpdated::class, function (MaintenanceTicketUpdated $event): void {
            $this->notifyMaintenanceTenant($event->ticket, 'maintenance_updated');
        });

        Event::listen(PaymentStatusChanged::class, function (PaymentStatusChanged $event): void {
            if ($event->to !== PaymentStatus::Confirmed) {
                return;
            }

            $invoice = $event->payment->invoice()->with('lease.primaryTenant')->first();
            $tenant = $invoice?->lease?->primaryTenant;
            if (! $tenant) {
                return;
            }

            $tenant->notify(new TenantPortalNotification([
                'type' => 'payment_confirmed',
                'title' => __('Payment confirmed'),
                'message' => __('Your payment has been confirmed.'),
                'url' => route('portal.billing.invoices.show', $invoice),
            ]));
        });

        Event::listen(InvoiceReminderDispatched::class, function (InvoiceReminderDispatched $event): void {
            $lease = $event->event->lease;
            $lease->loadMissing('primaryTenant.user');
            $tenant = $lease->primaryTenant;

            $channels = Setting::get('reminder_channels') ?? ['log'];

            if (! $tenant?->hasReminderRoute($channels)) {
                return;
            }

            $tenant->notify(new RentReminder($event->event));
        });
    }

    private function notifyMaintenanceTenant(MaintenanceTicket $ticket, string $type): void
    {
        $tenant = $ticket->creator?->tenant;
        if (! $tenant) {
            return;
        }

        $tenant->notify(new TenantPortalNotification([
            'type' => $type,
            'title' => __('Maintenance update'),
            'message' => $ticket->title,
            'url' => route('portal.maintenance-tickets.show', $ticket),
        ]));
    }
}
