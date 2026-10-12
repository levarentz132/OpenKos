<?php

use App\Enums\BillingUnit;
use App\Enums\UnitStatus;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitRate;

describe('Unit Custom Rates API Verification', function () {
    it('returns monthly rate accurately when unit has customized 2-week and 1-month rates', function () {
        $property = Property::factory()->create([
            'name' => 'Kos Kebon Jeruk Indah',
            'is_active' => true,
        ]);

        $unit = Unit::factory()->withRate(2200000)->create([
            'property_id' => $property->id,
            'name' => 'Room 101',
            'status' => UnitStatus::Available,
        ]);

        // Add customized 2-week rate
        UnitRate::create([
            'unit_id' => $unit->id,
            'billing_interval' => 2,
            'billing_unit' => BillingUnit::Week,
            'amount' => 950000,
            'is_active' => true,
        ]);

        // 1. Available Rooms API
        $response = $this->getJson(route('api.v1.available-rooms'));
        $response->assertOk()
            ->assertJsonPath('success', true);

        $propertyData = collect($response->json('data'))->firstWhere('slug', $property->slug);
        expect($propertyData)->not->toBeNull();
        expect($propertyData['price_range'])->toContain('2.200.000/bulan');
        expect($propertyData['price_range'])->not->toContain('950.000');

        $roomDetail = collect($propertyData['available_room_details'])->firstWhere('name', 'Room 101');
        expect($roomDetail)->not->toBeNull();
        // The monthly_rate must be the 1-month price (2,200,000), NOT the 2-week price (950,000)!
        expect((float) $roomDetail['monthly_rate'])->toBe(2200000.0);
        expect($roomDetail['rates'])->toHaveCount(2);

        // 2. AI Search API
        $aiSearchRes = $this->getJson(route('api.v1.ai.search', ['q' => 'Kos Kebon Jeruk Indah']));
        $aiSearchRes->assertOk();
        $aiPropData = collect($aiSearchRes->json('data'))->firstWhere('id', $property->id);
        expect($aiPropData)->not->toBeNull();
        expect($aiPropData['min_price_numeric'])->toBe(2200000);
        expect($aiPropData['price_display'])->toContain('2.200.000/bulan');
        expect($aiPropData['price_display'])->not->toContain('950.000');

        // 3. AI Room Details API
        $aiRoomRes = $this->getJson(route('api.v1.ai.rooms.show', ['id' => $property->id]));
        $aiRoomRes->assertOk();
        $aiRooms = collect($aiRoomRes->json('data.available_rooms'));
        $aiRoom = $aiRooms->firstWhere('name', 'Room 101');
        expect($aiRoom)->not->toBeNull();
        expect((float) $aiRoom['monthly_rate'])->toBe(2200000.0);
        expect($aiRoom['monthly_rate_display'])->toBe('Rp 2.200.000 / bulan');
    });
});
