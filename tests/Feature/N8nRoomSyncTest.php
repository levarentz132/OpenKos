<?php

use App\Enums\UnitStatus;
use App\Models\Property;
use App\Models\Unit;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Http;

uses()->beforeEach(function () {
    config(['services.n8n.secret' => null]);
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(RegionAndCitySeeder::class);
});

test('n8n getVacantRooms returns available units', function () {
    $property = Property::factory()->create(['name' => 'Villa Kos']);
    Unit::factory()->create(['property_id' => $property->id, 'name' => '101', 'status' => UnitStatus::Available]);
    Unit::factory()->occupied()->create(['property_id' => $property->id, 'name' => '102']);

    $response = $this->getJson('/api/v1/units/vacant');

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'count' => 1,
        ])
        ->assertJsonFragment([
            'unit_name' => '101',
            'property_name' => 'Villa Kos',
        ]);
});

test('n8n syncEmptyRooms creates and updates rooms to available status', function () {
    $property = Property::factory()->create(['name' => 'Melati Residence']);

    $payload = [
        'property_id' => $property->id,
        'rooms' => [
            ['name' => 'Room 201', 'floor' => '2', 'status' => 'available'],
            ['name' => 'Room 202', 'floor' => '2', 'status' => 'available'],
        ],
    ];

    $response = $this->postJson('/api/v1/sync/units/empty-rooms', $payload);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'synced_count' => 2,
        ]);

    $this->assertDatabaseHas('units', [
        'property_id' => $property->id,
        'name' => 'Room 201',
        'status' => UnitStatus::Available->value,
    ]);

    $this->assertDatabaseHas('units', [
        'property_id' => $property->id,
        'name' => 'Room 202',
        'status' => UnitStatus::Available->value,
    ]);
});

test('openkos automatically pushes room vacated webhook to n8n when unit becomes available', function () {
    Http::fake([
        'https://n8n.example.com/webhook/vacant-rooms' => Http::response(['status' => 'ok'], 200),
    ]);

    config(['services.n8n.webhook_url' => 'https://n8n.example.com/webhook/vacant-rooms']);

    $property = Property::factory()->create(['name' => 'Rose Villa']);
    $unit = Unit::factory()->create(['property_id' => $property->id, 'status' => UnitStatus::Occupied]);

    // Update status to available
    $unit->update(['status' => UnitStatus::Available]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://n8n.example.com/webhook/vacant-rooms'
            && $request['event'] === 'room_vacated'
            && $request['unit']['property_name'] === 'Rose Villa';
    });
});

test('artisan command openkos:push-n8n-vacant pushes all vacant rooms', function () {
    Http::fake([
        'https://n8n.example.com/webhook/vacant-rooms' => Http::response(['status' => 'ok'], 200),
    ]);

    config(['services.n8n.webhook_url' => 'https://n8n.example.com/webhook/vacant-rooms']);

    $property = Property::factory()->create(['name' => 'Rose Villa']);
    Unit::factory()->create(['property_id' => $property->id, 'status' => UnitStatus::Available]);

    $this->artisan('openkos:push-n8n-vacant')
        ->assertExitCode(0);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://n8n.example.com/webhook/vacant-rooms'
            && $request['event'] === 'vacant_rooms_bulk_sync'
            && $request['count'] >= 1;
    });
});
