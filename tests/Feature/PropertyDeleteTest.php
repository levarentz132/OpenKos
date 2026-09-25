<?php

use App\Enums\LeaseStatus;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;

test('owner can soft delete a property and its units', function () {
    $user = User::factory()->owner()->create();

    $property = Property::factory()->create(['name' => 'Property to Delete', 'is_active' => true]);
    $unit = Unit::factory()->create(['property_id' => $property->id]);

    $response = $this->actingAs($user)->delete(route('properties.destroy', $property));

    $response->assertRedirect(route('properties.index'));

    $property->refresh();
    expect($property->trashed())->toBeTrue();
    expect($property->is_active)->toBeFalse();
    expect($unit->fresh()->trashed())->toBeTrue();
});

test('owner can restore a soft-deleted property and its units', function () {
    $user = User::factory()->owner()->create();

    $property = Property::factory()->create(['name' => 'Property to Restore', 'is_active' => true]);
    $unit = Unit::factory()->create(['property_id' => $property->id]);

    $property->delete();
    $unit->delete();

    $response = $this->actingAs($user)->post(route('properties.restore', $property));

    $response->assertRedirect();

    $property->refresh();
    expect($property->trashed())->toBeFalse();
    expect($property->is_active)->toBeTrue();
    expect($unit->fresh()->trashed())->toBeFalse();
});

test('owner can permanently force delete an archived property', function () {
    $user = User::factory()->owner()->create();

    $property = Property::factory()->create(['name' => 'Property Force Delete', 'is_active' => false]);
    $unit = Unit::factory()->create(['property_id' => $property->id]);
    $property->delete();
    $unit->delete();

    $response = $this->actingAs($user)->delete(route('properties.destroy', $property), ['force' => true]);

    $response->assertRedirect(route('properties.index'));

    expect(Property::withTrashed()->find($property->id))->toBeNull();
    expect(Unit::withTrashed()->find($unit->id))->toBeNull();
});

test('cannot delete a property that has active leases', function () {
    $user = User::factory()->owner()->create();

    $property = Property::factory()->create(['name' => 'Kos With Active Lease', 'is_active' => true]);
    $unit = Unit::factory()->create(['property_id' => $property->id]);
    $lease = Lease::factory()->create([
        'unit_id' => $unit->id,
        'status' => LeaseStatus::Active,
    ]);

    $response = $this->actingAs($user)->from(route('properties.index'))->delete(route('properties.destroy', $property));

    $response->assertRedirect(route('properties.index'));

    $property->refresh();
    expect($property->trashed())->toBeFalse();
    expect($property->is_active)->toBeTrue();
});
