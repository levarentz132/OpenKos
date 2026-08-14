<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\MailManager;
use App\Services\WhatsAppManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use OpenKOS\Platform\Notification\NotificationRegistry;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MailManager::class);
        $this->app->singleton(WhatsAppManager::class);
    }

    public function boot(): void
    {
        $this->configureMail();
    }

    protected function configureMail(): void
    {
        try {
            $config = Setting::effectiveMailConfig();
        } catch (QueryException) {
            return;
        }

        $driver = $config['driver'] ?? 'log';
        config()->set('mail.default', $this->resolveLaravelMailer($driver));

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

        $this->configureLaravelMailer($driver, $config);
    }

    private function resolveLaravelMailer(string $driver): string
    {
        $registration = $this->app->make(NotificationRegistry::class)->driver('mail', $driver);

        if ($registration) {
            if ($registration->laravelMailer === null) {
                Log::warning('OpenKOS mail driver has no Laravel mailer capability; falling back to Laravel log mailer.', [
                    'driver' => $driver,
                    'fallback' => 'log',
                    'reason' => 'missing_capability',
                ]);

                return 'log';
            }

            if (array_key_exists($registration->laravelMailer, config('mail.mailers', []))) {
                return $registration->laravelMailer;
            }

            Log::warning('Configured Laravel mailer for OpenKOS mail driver is unavailable; falling back to Laravel log mailer.', [
                'driver' => $driver,
                'mailer' => $registration->laravelMailer,
                'fallback' => 'log',
                'reason' => 'invalid_capability',
            ]);

            return 'log';
        }

        $mailer = match ($driver) {
            'openkos/smtp', 'smtp' => 'smtp',
            'openkos/log', 'log' => 'log',
            default => $driver,
        };

        if (array_key_exists($mailer, config('mail.mailers', []))) {
            return $mailer;
        }

        Log::warning('Unknown mail driver; falling back to Laravel log mailer.', [
            'driver' => $driver,
            'fallback' => 'log',
            'reason' => 'unknown_driver',
        ]);

        return 'log';
    }

    private function configureLaravelMailer(string $driver, array $config): void
    {
        $registration = $this->app->make(NotificationRegistry::class)->driver('mail', $driver);

        if (! $registration?->laravelMailer) {
            return;
        }

        $driverConfig = $config['drivers'][$driver] ?? [];

        if (! is_array($driverConfig) || $driverConfig === []) {
            return;
        }

        $mailer = $registration->laravelMailer;
        config()->set("mail.mailers.{$mailer}", array_replace(
            config("mail.mailers.{$mailer}", []),
            $driverConfig,
        ));
    }
}
