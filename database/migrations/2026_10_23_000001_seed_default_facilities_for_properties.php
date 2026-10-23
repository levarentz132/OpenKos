<?php

use App\Models\Property;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defaultFacilities = [
            'WiFi Fiber Cepat',
            'AC Dingin',
            'Kamar Mandi Dalam',
            'Water Heater (Air Panas)',
            'Kasur Springbed & Bantal',
            'Lemari Pakaian & Meja',
            'CCTV & Keamanan 24 Jam',
            'Parkir Motor Aman',
            'Dapur Bersama',
            'Akses Gerbang 24 Jam',
        ];

        Property::whereNull('facilities')->orWhere('facilities', '[]')->each(function (Property $property) use ($defaultFacilities) {
            $property->facilities = $defaultFacilities;
            $property->save();
        });
    }

    public function down(): void
    {
        // No down action needed
    }
};
