<?php

use App\Models\Setting;
use App\Notifications\UserInvitation;
use App\Providers\NotificationServiceProvider;
use App\Services\MailManager;
use App\Services\Platform\ComposerPluginDiscovery;
use App\Services\Settings\PlatformSettingsStore;
use App\Services\Settings\SettingManager;
use App\Services\WhatsAppManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use OpenKOS\Core\Contracts\PluginDiscovery;
use OpenKOS\Core\Contracts\SettingsStore;

it('registers application services through dedicated providers', function () {
    expect(app(SettingManager::class))
        ->toBe(app(SettingManager::class))
        ->and(app(SettingsStore::class))
        ->toBeInstanceOf(PlatformSettingsStore::class)
        ->and(app(PluginDiscovery::class))
        ->toBeInstanceOf(ComposerPluginDiscovery::class)
        ->and(app(MailManager::class))
        ->toBe(app(MailManager::class))
        ->and(app(WhatsAppManager::class))
        ->toBe(app(WhatsAppManager::class));
});

it('maps OpenKOS mail drivers to valid Laravel mailers', function () {
    Setting::set('mail_config', ['driver' => 'openkos/smtp']);

    (new NotificationServiceProvider(app()))->boot();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers'))->toHaveKey(config('mail.default'));
});

it('preserves configured native Laravel mailers', function () {
    config(['mail.mailers.ses' => ['transport' => 'log']]);
    Setting::set('mail_config', ['driver' => 'ses']);

    (new NotificationServiceProvider(app()))->boot();

    expect(config('mail.default'))->toBe('ses')
        ->and(config('mail.mailers'))->toHaveKey(config('mail.default'));

    Notification::route('mail', 'user@example.com')
        ->notify(new UserInvitation('https://example.com/invitation'));
});

it('warns and falls back to Laravel log for unknown mail drivers', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with('Unknown mail driver; falling back to Laravel log mailer.', [
            'driver' => 'openkos/resend',
            'fallback' => 'log',
        ]);
    Setting::set('mail_config', ['driver' => 'openkos/resend']);

    (new NotificationServiceProvider(app()))->boot();

    expect(config('mail.default'))->toBe('log')
        ->and(config('mail.mailers'))->toHaveKey(config('mail.default'));
});
