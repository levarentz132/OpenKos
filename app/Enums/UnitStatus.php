<?php

namespace App\Enums;

enum UnitStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Maintenance = 'maintenance';
    case Unavailable = 'unavailable';
    case Employee = 'employee';
    case Vendor = 'vendor';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Occupied => 'Occupied',
            self::Maintenance => 'Maintenance',
            self::Unavailable => 'Unavailable',
            self::Employee => 'Employee / Staff',
            self::Vendor => 'Vendor',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
