<?php

use App\Enums\PaymentStatus;
use App\Events\Payment\PaymentStatusChanged;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantPortalNotification;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('tenant can view and mark notifications as read', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $tenant->notify(new TenantPortalNotification([
        'type' => 'maintenance_created',
        'title' => 'Maintenance update',
        'message' => 'Leaking faucet',
        'url' => null,
    ]));

    $notification = $tenant->notifications()->first();

    $this->actingAs($user)
        ->get(route('portal.notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant-portal/notifications/index')
            ->where('unreadCount', 1)
            ->where('notifications.data.0.title', 'Maintenance update'));

    $this->post(route('portal.notifications.read', $notification->id))
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('tenant cannot mark another tenants notification as read', function () {
    $user = User::factory()->create();
    Tenant::factory()->withUser($user)->create();
    $otherTenant = Tenant::factory()->withUser()->create();
    $otherTenant->notify(new TenantPortalNotification([
        'type' => 'maintenance_created',
        'title' => 'Private',
        'message' => 'Private',
    ]));
    $notification = $otherTenant->notifications()->first();

    $this->actingAs($user)
        ->post(route('portal.notifications.read', $notification->id))
        ->assertNotFound();
});

test('tenant can mark all notifications as read', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();

    foreach (['First', 'Second'] as $title) {
        $tenant->notify(new TenantPortalNotification([
            'type' => 'maintenance_created',
            'title' => $title,
            'message' => $title,
        ]));
    }

    $this->actingAs($user)
        ->post(route('portal.notifications.read-all'))
        ->assertRedirect();

    expect($tenant->unreadNotifications()->count())->toBe(0);
});

test('payment confirmation creates a tenant notification', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);
    $invoice = Invoice::factory()->create(['lease_id' => $lease->id]);
    $payment = $invoice->payments()->create([
        'amount' => 100000,
        'payment_date' => now(),
        'status' => PaymentStatus::Confirmed,
    ]);

    PaymentStatusChanged::dispatch($payment, PaymentStatus::Pending, PaymentStatus::Confirmed);

    expect($tenant->notifications()->where('type', 'payment_confirmed')->exists())->toBeTrue();
});

test('maintenance ticket creation creates a tenant notification', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->post(route('portal.maintenance-tickets.store'), [
            'title' => 'Leaking faucet',
            'location_type' => 'unit',
        ])
        ->assertRedirect();

    expect($tenant->notifications()->where('type', 'maintenance_created')->exists())->toBeTrue();
});

test('lease expiration command creates only one notification at thirty days', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create();
    Lease::factory()->create([
        'primary_tenant_id' => $tenant->id,
        'end_date' => today()->addDays(30),
    ]);

    $this->artisan('app:send-lease-expiration-notifications')->assertSuccessful();
    $this->artisan('app:send-lease-expiration-notifications')->assertSuccessful();

    expect($tenant->notifications()->where('type', 'lease_expiring')->count())->toBe(1);
});
