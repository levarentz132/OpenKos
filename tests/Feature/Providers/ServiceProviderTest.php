<?php

use App\Services\MailManager;
use App\Services\Platform\ComposerPluginDiscovery;
use App\Services\Settings\PlatformSettingsStore;
use App\Services\Settings\SettingManager;
use App\Services\WhatsAppManager;
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
