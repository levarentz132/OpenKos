<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;
use Inertia\Testing\AssertableInertia as Assert;
use OpenKOS\Platform\Settings\SettingsManager;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => Role::Owner->value, 'guard_name' => 'web']);
});

test('owner can access payment gateway dashboard settings and view doku', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::Owner->value);

    $settings = app(SettingsManager::class);
    $settings->set(PaymentGatewayManager::ACTIVE_KEY, 'doku');
    $settings->set(PaymentGatewayManager::CONFIG_KEY, [
        'doku' => [
            'environment' => 'sandbox',
            'client_id' => 'BRN-0208-1788852244810',
            'secret_key' => 'SK-vkKdx1b9ZOLoYiyeMuqz',
            'api_key' => 'doku_key_sandbox_11f95366b305485d8e9de31f9399cc8e',
            'callback_url' => 'http://127.0.0.1:8000/portal/billing',
        ],
    ]);

    $response = $this->actingAs($owner)->get('/settings/payment-gateway');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/payment-gateway')
            ->where('active_key', 'doku')
            ->where('active_status', 'active')
            ->has('gateways', fn (Assert $gateways) => $gateways
                ->each(fn (Assert $gateway) => $gateway
                    ->where('key', 'doku')
                    ->where('label', 'DOKU Checkout (Jokul)')
                    ->where('status', 'configured')
                    ->etc()
                )
            )
        );
});

test('owner can update doku sandbox credentials via dashboard patch endpoint', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::Owner->value);

    $response = $this->actingAs($owner)->patch('/settings/payment-gateway', [
        'gateway' => 'doku',
        'configuration' => [
            'environment' => 'sandbox',
            'client_id' => 'BRN-0208-1788852244810',
            'secret_key' => 'SK-vkKdx1b9ZOLoYiyeMuqz',
            'api_key' => 'doku_key_sandbox_11f95366b305485d8e9de31f9399cc8e',
            'callback_url' => 'http://127.0.0.1:8000/portal/billing',
        ],
    ]);

    $response->assertRedirect();

    $settings = app(SettingsManager::class);
    expect($settings->get(PaymentGatewayManager::ACTIVE_KEY))->toBe('doku');

    $config = $settings->get(PaymentGatewayManager::CONFIG_KEY);
    expect($config['doku']['client_id'])->toBe('BRN-0208-1788852244810')
        ->and($config['doku']['secret_key'])->toBe('SK-vkKdx1b9ZOLoYiyeMuqz')
        ->and($config['doku']['environment'])->toBe('sandbox');
});

test('owner can create a doku sandbox trial checkout session via dashboard', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::Owner->value);

    $settings = app(SettingsManager::class);
    $settings->set(PaymentGatewayManager::ACTIVE_KEY, 'doku');
    $settings->set(PaymentGatewayManager::CONFIG_KEY, [
        'doku' => [
            'environment' => 'sandbox',
            'client_id' => 'BRN-0208-1788852244810',
            'secret_key' => 'SK-vkKdx1b9ZOLoYiyeMuqz',
        ],
    ]);

    $checkoutUrl = 'https://staging.doku.com/checkout-link-v2/trial-session-test';
    Illuminate\Support\Facades\Http::fake([
        'https://api-sandbox.doku.com/checkout/v1/payment' => Illuminate\Support\Facades\Http::response([
            'order' => ['invoice_number' => 'dummy'],
            'payment' => [
                'url' => $checkoutUrl,
                'token_id' => 'TKN-TRIAL-123',
            ],
            'message' => ['SUCCESS'],
        ], 200),
    ]);

    $response = $this->actingAs($owner)->postJson('/settings/payment-gateway/trial', [
        'amount' => 10000,
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('checkout_url', $checkoutUrl)
        ->assertJsonPath('amount', 10000)
        ->assertJsonPath('environment', 'sandbox');
});

test('developer can call public sandbox trial api and simulate payment webhook', function () {
    $checkoutUrl = 'https://staging.doku.com/checkout-link-v2/api-trial-123';
    Illuminate\Support\Facades\Http::fake([
        'https://api-sandbox.doku.com/checkout/v1/payment' => Illuminate\Support\Facades\Http::response([
            'order' => ['invoice_number' => 'dummy'],
            'payment' => [
                'url' => $checkoutUrl,
                'token_id' => 'TKN-API-TRIAL',
            ],
            'message' => ['SUCCESS'],
        ], 200),
    ]);

    $settings = app(SettingsManager::class);
    $settings->set(PaymentGatewayManager::ACTIVE_KEY, 'doku');
    $settings->set(PaymentGatewayManager::CONFIG_KEY, [
        'doku' => [
            'environment' => 'sandbox',
            'client_id' => 'BRN-0208-1788852244810',
            'secret_key' => 'SK-vkKdx1b9ZOLoYiyeMuqz',
        ],
    ]);

    // 1. Request trial checkout
    $trialRes = $this->postJson('/api/v1/sandbox/trial', [
        'amount' => 15000,
    ]);

    $trialRes->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('checkout_url', $checkoutUrl)
        ->assertJsonPath('amount', 15000);

    // 2. Create a room and a pending booking order to simulate
    $property = \App\Models\Property::factory()->create();
    $unit = \App\Models\Unit::factory()->withRate(1500000)->create([
        'property_id' => $property->id,
        'status' => \App\Enums\UnitStatus::Available,
    ]);

    $bookingOrder = \App\Models\BookingOrder::create([
        'cart_token' => 'trial-cart',
        'reference' => 'BK-TRIAL-SIM',
        'unit_id' => $unit->id,
        'guest_name' => 'Trial User',
        'guest_phone' => '628123456700',
        'guest_email' => 'trial@example.com',
        'start_date' => '2026-10-01',
        'amount' => 1500000,
        'status' => \App\Models\BookingOrder::STATUS_PENDING,
    ]);

    // 3. Call simulate-payment
    $simRes = $this->postJson('/api/v1/sandbox/simulate-payment', [
        'reference' => 'BK-TRIAL-SIM',
    ]);

    $simRes->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('order.status', 'paid');

    $bookingOrder->refresh();
    expect($bookingOrder->status)->toBe(\App\Models\BookingOrder::STATUS_PAID)
        ->and($bookingOrder->lease_id)->not->toBeNull();

    $unit->refresh();
    expect($unit->status)->toBe(\App\Enums\UnitStatus::Occupied);
});

