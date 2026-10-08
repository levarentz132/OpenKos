<?php

namespace App\Services\Settings;

class SettingCaster
{
    public function serialize(mixed $value, ?string $cast = 'string'): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($cast) {
            'array' => is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (string) $value,
            'encrypted:array' => encrypt(json_encode(is_array($value) ? $value : [$value])),
            'encrypted' => encrypt((string) $value),
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
            'integer' => (string) (int) $value,
            default => is_array($value) ? json_encode($value) : (string) $value,
        };
    }

    public function deserialize(?string $value, ?string $cast = 'string'): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($cast === 'encrypted' || $cast === 'encrypted:array') {
            return match ($cast) {
                'encrypted:array' => json_decode(decrypt($value), true) ?: [],
                default => decrypt($value),
            };
        }

        return match ($cast) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'integer' => (int) $value,
            'array' => is_string($value) ? (json_decode($value, true) ?: []) : [],
            default => $value,
        };
    }
}
