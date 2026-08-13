<?php

namespace App\Providers;

use App\Enums\PaymentStatus;
use App\Events\Maintenance\MaintenanceTicketCreated;
use App\Events\Maintenance\MaintenanceTicketUpdated;
use App\Events\Payment\PaymentStatusChanged;
use App\Events\Reminder\InvoiceReminderDispatched;
use App\Models\MaintenanceTicket;
use App\Notifications\RentReminder;
use App\Notifications\TenantPortalNotification;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthEvents();
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
