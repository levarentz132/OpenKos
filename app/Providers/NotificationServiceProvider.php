<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\MailManager;
use App\Services\WhatsAppManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\ServiceProvider;

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
}
