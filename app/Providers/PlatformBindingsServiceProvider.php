<?php

namespace App\Providers;

use App\Services\Platform\ComposerPluginDiscovery;
use Illuminate\Support\ServiceProvider;
use OpenKOS\Core\Contracts\PluginDiscovery;
use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\Settings\SettingsPage;

class PlatformBindingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PluginDiscovery::class, ComposerPluginDiscovery::class);
    }

    public function boot(): void
    {
        $this->app->booted(function (): void {
            $this->registerPlatformSettingsPages();
        });
    }

    private function registerPlatformSettingsPages(): void
    {
        app(OpenKOSManager::class)->settings()
            ->registerPage(new SettingsPage('profile', 'Profile', '/settings/profile', ownerOnly: false, group: 'Account', order: 100, routeName: 'profile.edit'))
            ->registerPage(new SettingsPage('security', 'Security', '/settings/security', ownerOnly: false, group: 'Account', order: 200, routeName: 'security.edit'))
            ->registerPage(new SettingsPage('general', 'General', '/settings/general', group: null, order: 0, routeName: 'settings.general.edit'))
            ->registerPage(new SettingsPage('reminders', 'Reminders', '/settings/reminders', group: 'Notifications', order: 100, routeName: 'settings.reminders.edit'))
            ->registerPage(new SettingsPage('mail', 'Mail', '/settings/mail', group: 'Integrations', order: 100, routeName: 'settings.mail.edit'));
    }
}
