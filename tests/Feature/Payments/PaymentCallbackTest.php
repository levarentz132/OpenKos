<?php

use App\Enums\Role;
use App\Models\BookingOrder;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => Role::Owner->value, 'guard_name' => 'web']);
});

test('owner accessing portal billing without payment params is redirected to dashboard without 403', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::Owner->value);

    $response = $this->actingAs($owner)->get('/portal/billing');

    $response->assertRedirect('/dashboard');
});

test('owner returning from doku trial payment is redirected to payment gateway settings with success flash', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::Owner->value);

    $response = $this->actingAs($owner)->get('/portal/billing?status=trial_finish&reference=TRIAL-TEST1234');

    $response->assertRedirect(route('settings.payment-gateway.edit'));
});

test('guest returning from payment gateway with payment params sees public payment status page', function () {
    $property = Property::factory()->create();
    $unit = Unit::factory()->create(['property_id' => $property->id]);
    $order = BookingOrder::create([
        'reference' => 'BK-RETURN-TEST',
        'unit_id' => $unit->id,
        'guest_name' => 'John Guest',
        'guest_phone' => '628123456789',
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'duration_months' => 1,
        'amount' => 1500000,
        'status' => 'pending',
        'cart_token' => (string) str()->uuid(),
    ]);

    $response = $this->get('/portal/billing?status=SUCCESS&order_id=BK-RETURN-TEST');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('payments/status')
            ->where('reference', 'BK-RETURN-TEST')
            ->where('status', 'success')
            ->where('amount', 1500000)
            ->where('isGuest', true)
        );
});

test('guest accessing portal billing without params is redirected to login', function () {
    $response = $this->get('/portal/billing');

    $response->assertRedirect(route('login'));
});

test('tenant accessing portal billing sees their invoices', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['user_id' => $user->id]);

    $property = Property::factory()->create();
    $unit = Unit::factory()->create(['property_id' => $property->id]);
    $lease = Lease::factory()->create([
        'unit_id' => $unit->id,
        'status' => \App\Enums\LeaseStatus::Active,
    ]);
    $lease->tenants()->attach($tenant->id, ['is_primary' => true]);

    $response = $this->actingAs($user)->get('/portal/billing');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant-portal/payments/index')
        );
});
