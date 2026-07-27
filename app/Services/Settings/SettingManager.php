<?php

namespace App\Services\Settings;

use App\Models\Setting;

class SettingManager
{
    public function __construct(
        private SettingRegistry $registry,
        private SettingCaster $caster,
    ) {}

    public function get(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->allWithDefaults();
        }

        $setting = Setting::where('key', $key)->first();

        if ($setting) {
            return $setting->resolveValue();
        }

        $def = $this->registry->get($key);

        return $def['default'] ?? null;
    }

    public function set(string $key, mixed $value, ?string $cast = null): Setting
    {
        $cast ??= $this->registry->get($key)['cast'] ?? 'string';

        $stored = $this->caster->serialize($value, $cast);

        return Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $cast],
        );
    }

    public function some(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }

        return $result;
    }

    public function getEffectiveMailConfig(): array
    {
        try {
            $stored = Setting::get('mail_config');
        } catch (\Throwable) {
            $stored = [];
        }

        if (! is_array($stored)) {
            $stored = [];
        }

        $envDriver = config('mail.default') ?: env('MAIL_MAILER', 'log');
        $envHost = config('mail.mailers.smtp.host') ?: env('MAIL_HOST');
        $envPort = config('mail.mailers.smtp.port') ?: env('MAIL_PORT');
        $envUsername = config('mail.mailers.smtp.username') ?: env('MAIL_USERNAME');
        $envPassword = config('mail.mailers.smtp.password') ?: env('MAIL_PASSWORD');
        $envEncryption = config('mail.mailers.smtp.encryption') ?: env('MAIL_ENCRYPTION');
        $envFromAddress = config('mail.from.address') ?: env('MAIL_FROM_ADDRESS');
        $envFromName = config('mail.from.name') ?: env('MAIL_FROM_NAME');

        return [
            'driver' => filled(data_get($stored, 'driver')) ? $stored['driver'] : ($envDriver ?: 'log'),
            'host' => filled(data_get($stored, 'host')) ? (string) $stored['host'] : (string) ($envHost ?: ''),
            'port' => filled(data_get($stored, 'port')) ? (int) $stored['port'] : ($envPort ? (int) $envPort : 587),
            'username' => filled(data_get($stored, 'username')) ? (string) $stored['username'] : (string) ($envUsername ?: ''),
            'password' => filled(data_get($stored, 'password')) ? (string) $stored['password'] : (string) ($envPassword ?: ''),
            'encryption' => filled(data_get($stored, 'encryption')) ? (string) $stored['encryption'] : (string) ($envEncryption ?: 'null'),
            'from_address' => filled(data_get($stored, 'from_address')) ? (string) $stored['from_address'] : (string) ($envFromAddress ?: ''),
            'from_name' => filled(data_get($stored, 'from_name')) ? (string) $stored['from_name'] : (string) ($envFromName ?: ''),
        ];
    }

    private function allWithDefaults(): array
    {
        $defaults = [];
        foreach ($this->registry->all() as $key => $def) {
            $defaults[$key] = $this->caster->serialize($def['default'], $def['cast']);
        }

        return array_merge($defaults, Setting::pluck('value', 'key')->all());
    }
}
