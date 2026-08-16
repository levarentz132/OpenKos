<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Ewallet = 'ewallet';
    case Gateway = 'gateway';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Transfer => 'Bank Transfer',
            self::Ewallet => 'E-Wallet',
            self::Gateway => 'Online Gateway',
            self::Other => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function manualValues(): array
    {
        return array_values(array_filter(self::values(), fn (string $value): bool => $value !== self::Gateway->value));
    }
}
