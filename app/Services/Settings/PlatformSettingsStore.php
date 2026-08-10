<?php

namespace App\Services\Settings;

use OpenKOS\Core\Contracts\SettingsStore;

class PlatformSettingsStore implements SettingsStore
{
    public function __construct(private SettingManager $settings) {}

    public function get(string $key): mixed
    {
        return $this->settings->get($key);
    }

    public function set(string $key, mixed $value, string $type): void
    {
        $this->settings->set($key, $value, $type);
    }
}
