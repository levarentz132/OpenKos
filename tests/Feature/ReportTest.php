<?php

use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(RegionAndCitySeeder::class);
});

test('reports index requires authentication', function () {
    $this->get('/reports')->assertRedirect('login');
});

test('reports index returns monthly income and occupancy review for owner', function () {
    $user = User::factory()->owner()->create();

    $property = Property::factory()->create();
    Unit::factory()->occupied()->create(['property_id' => $property->id]);
    Unit::factory()->create(['property_id' => $property->id, 'status' => 'available']);

    $this->actingAs($user)
        ->get('/reports?start_date=2026-01-01&end_date=2026-06-30')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->has('income_report')
            ->has('income_report.months')
            ->has('income_report.properties')
            ->has('occupancy_review')
            ->where('occupancy_review.total_units', 2)
            ->where('occupancy_review.occupied_units', 1)
            ->where('occupancy_review.available_units', 1)
            ->where('filters.start_date', '2026-01-01')
            ->where('filters.end_date', '2026-06-30')
        );
});

test('reports index filters by property_id when specified', function () {
    $user = User::factory()->owner()->create();

    $prop1 = Property::factory()->create();
    $prop2 = Property::factory()->create();

    $this->actingAs($user)
        ->get("/reports?property_id={$prop1->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.property_id', (string) $prop1->id)
        );
});
