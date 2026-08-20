<?php

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;

describe('Bulk Units & Delete Lease', function () {
    it('creates multiple units in bulk for a property', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();

        $response = $this->actingAs($user)->post(route('properties.units.bulk-store', $property), [
            'prefix' => 'Kamar',
            'start_number' => 101,
            'count' => 5,
            'floor' => '1',
            'capacity' => 2,
            'monthly_rate' => 1750000,
        ]);

        $response->assertRedirect();

        expect($property->units()->count())->toBe(5);
        expect(Unit::where('name', 'Kamar 101')->exists())->toBeTrue();
        expect(Unit::where('name', 'Kamar 105')->exists())->toBeTrue();

        $unit = Unit::where('name', 'Kamar 101')->first();
        expect($unit->rates()->where('billing_unit', 'month')->value('amount'))->toBe('1750000.00');
    });

    it('permanently deletes a lease and its invoices', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->create(['property_id' => $property->id, 'status' => 'occupied']);
        $tenant = Tenant::factory()->create();

        $lease = Lease::factory()->create([
            'unit_id' => $unit->id,
            'primary_tenant_id' => $tenant->id,
            'status' => 'active',
        ]);

        $invoice = Invoice::create([
            'lease_id' => $lease->id,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'pending',
            'total' => 1500000,
            'amount_paid' => 0,
        ]);

        $response = $this->actingAs($user)->delete(route('leases.delete', $lease));

        $response->assertRedirect();

        expect(Lease::where('id', $lease->id)->exists())->toBeFalse();
        expect(Invoice::where('id', $invoice->id)->exists())->toBeFalse();
        expect($unit->fresh()->status->value)->toBe('available');
    });

    it('deletes multiple selected leases in bulk', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $unitA = Unit::factory()->create(['property_id' => $property->id, 'status' => 'occupied']);
        $unitB = Unit::factory()->create(['property_id' => $property->id, 'status' => 'occupied']);
        $tenant = Tenant::factory()->create();

        $leaseA = Lease::factory()->create(['unit_id' => $unitA->id, 'primary_tenant_id' => $tenant->id, 'status' => 'active']);
        $leaseB = Lease::factory()->create(['unit_id' => $unitB->id, 'primary_tenant_id' => $tenant->id, 'status' => 'active']);

        $response = $this->actingAs($user)->post(route('leases.bulk-delete'), [
            'ids' => [$leaseA->id, $leaseB->id],
        ]);

        $response->assertRedirect();

        expect(Lease::whereIn('id', [$leaseA->id, $leaseB->id])->count())->toBe(0);
        expect($unitA->fresh()->status->value)->toBe('available');
        expect($unitB->fresh()->status->value)->toBe('available');
    });

    it('updates multiple units in bulk for a property', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $unitA = Unit::factory()->create(['property_id' => $property->id, 'floor' => '1', 'capacity' => 1]);
        $unitB = Unit::factory()->create(['property_id' => $property->id, 'floor' => '1', 'capacity' => 1]);

        $response = $this->actingAs($user)->put(route('properties.units.bulk-update', $property), [
            'unit_ids' => [$unitA->id, $unitB->id],
            'fields' => ['floor', 'capacity', 'monthly_rate'],
            'floor' => '2',
            'capacity' => 3,
            'monthly_rate' => 2500000,
        ]);

        $response->assertRedirect();

        expect($unitA->fresh()->floor)->toBe('2');
        expect($unitA->fresh()->capacity)->toBe(3);
        expect($unitA->rates()->where('billing_unit', 'month')->value('amount'))->toBe('2500000.00');

        expect($unitB->fresh()->floor)->toBe('2');
        expect($unitB->fresh()->capacity)->toBe(3);
        expect($unitB->rates()->where('billing_unit', 'month')->value('amount'))->toBe('2500000.00');
    });
});
