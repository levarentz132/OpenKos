<?php

use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use App\Models\BookingOrder;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use OpenKOS\Core\Enums\PaymentStatus;

beforeEach(function () {
    $settings = app(\OpenKOS\Platform\Settings\SettingsManager::class);
    $settings->set('payment_gateway', 'doku');
    $settings->set('payment_gateway_config', [
        'doku' => [
            'client_id' => 'BRN-0208-1788852244810',
            'secret_key' => 'SK-vkKdx1b9ZOLoYiyeMuqz',
            'environment' => 'sandbox',
        ],
    ]);
});

test('guest can create booking order saved in cart without creating lease immediately', function () {
    $checkoutUrl = 'https://staging.doku.com/checkout-link-v2/booking-session-123';

    Http::fake([
        'https://api-sandbox.doku.com/checkout/v1/payment' => Http::response([
            'order' => ['invoice_number' => 'dummy'],
            'payment' => [
                'url' => $checkoutUrl,
                'token_id' => 'TKN-BOOKING-123',
            ],
            'message' => ['SUCCESS'],
        ], 200),
    ]);

    $property = Property::factory()->create(['name' => 'Highlander Stay Grogol']);
    $unit = Unit::factory()->withRate(1750000)->create([
        'property_id' => $property->id,
        'name' => 'Kamar 101',
        'status' => UnitStatus::Available,
    ]);

    $response = $this->postJson('/api/v1/bookings', [
        'unit_id' => $unit->id,
        'name' => 'Budi Santoso',
        'phone' => '081299998888',
        'email' => 'budi.santoso@example.com',
        'start_date' => '2026-10-01',
        'duration_months' => 2,
        'notes' => 'Booking via website',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Booking order created and saved in cart. Please complete payment to confirm your lease.')
        ->assertJsonPath('order.property.name', 'Highlander Stay Grogol')
        ->assertJsonPath('order.unit.name', 'Kamar 101')
        ->assertJsonPath('order.guest.name', 'Budi Santoso')
        ->assertJsonPath('order.guest.phone', '6281299998888')
        ->assertJsonPath('order.amount', 1750000)
        ->assertJsonPath('order.status', 'pending')
        ->assertJsonPath('order.checkout_url', $checkoutUrl)
        ->assertJsonPath('order.lease_created', false);

    // Verify database entities: BookingOrder is stored, but Lease is NOT yet created
    $bookingOrder = BookingOrder::where('guest_phone', '6281299998888')->first();
    expect($bookingOrder)->not->toBeNull()
        ->and($bookingOrder->status)->toBe(BookingOrder::STATUS_PENDING)
        ->and($bookingOrder->lease_id)->toBeNull();

    // Unit must remain Available before payment
    $unit->refresh();
    expect($unit->status)->toBe(UnitStatus::Available);

    // No lease or invoice yet
    expect(Lease::count())->toBe(0)
        ->and(Invoice::count())->toBe(0);
});

test('cart endpoints support viewing, checkout, and removing items', function () {
    $checkoutUrl = 'https://staging.doku.com/checkout-link-v2/booking-cart-456';

    Http::fake([
        'https://api-sandbox.doku.com/checkout/v1/payment' => Http::response([
            'order' => ['invoice_number' => 'dummy'],
            'payment' => [
                'url' => $checkoutUrl,
                'token_id' => 'TKN-CART-456',
            ],
            'message' => ['SUCCESS'],
        ], 200),
    ]);

    $property = Property::factory()->create(['name' => 'Highlander Stay']);
    $unit = Unit::factory()->withRate(2000000)->create([
        'property_id' => $property->id,
        'status' => UnitStatus::Available,
    ]);

    $cartToken = 'test-cart-token-' . uniqid();

    // 1. Add to cart
    $addResponse = $this->postJson('/api/v1/cart', [
        'unit_id' => $unit->id,
        'name' => 'Siti Nurhaliza',
        'phone' => '081344445555',
        'start_date' => '2026-10-15',
        'duration_months' => 1,
        'cart_token' => $cartToken,
    ]);

    $addResponse->assertCreated();
    $orderId = $addResponse->json('order.id');

    // 2. View cart
    $viewResponse = $this->getJson("/api/v1/cart?cart_token={$cartToken}");
    $viewResponse->assertOk()
        ->assertJsonPath('cart.count', 1)
        ->assertJsonPath('cart.total', 2000000)
        ->assertJsonPath('cart.items.0.id', $orderId)
        ->assertJsonPath('cart.items.0.unit_name', $unit->name);

    // 3. Refresh checkout URL
    $checkoutResponse = $this->postJson("/api/v1/cart/{$orderId}/checkout");
    $checkoutResponse->assertOk()
        ->assertJsonPath('checkout_url', $checkoutUrl);

    // 4. Remove from cart
    $deleteResponse = $this->deleteJson("/api/v1/cart/{$orderId}");
    $deleteResponse->assertOk()
        ->assertJsonPath('message', 'Booking item removed from cart.');

    // 5. Verify cart is empty now
    $emptyCartResponse = $this->getJson("/api/v1/cart?cart_token={$cartToken}");
    $emptyCartResponse->assertOk()
        ->assertJsonPath('cart.count', 0);
});

test('booking order payment webhook automatically creates lease, occupies unit, and settles invoice', function () {
    $checkoutUrl = 'https://staging.doku.com/checkout-link-v2/booking-settle-456';

    Http::fake([
        'https://api-sandbox.doku.com/checkout/v1/payment' => Http::response([
            'order' => ['invoice_number' => 'dummy'],
            'payment' => [
                'url' => $checkoutUrl,
                'token_id' => 'TKN-SETTLE-456',
            ],
            'message' => ['SUCCESS'],
        ], 200),
    ]);

    $property = Property::factory()->create();
    $unit = Unit::factory()->withRate(1500000)->create([
        'property_id' => $property->id,
        'status' => UnitStatus::Available,
    ]);

    // 1. Guest creates booking order (stored in cart)
    $orderRes = $this->postJson('/api/v1/bookings', [
        'unit_id' => $unit->id,
        'name' => 'Dewi Lestari',
        'phone' => '081277773333',
        'email' => 'dewi@example.com',
        'start_date' => '2026-10-01',
        'duration_months' => 1,
    ]);

    $orderRes->assertCreated();
    $bookingOrderId = $orderRes->json('order.id');
    $reference = $orderRes->json('order.reference');

    $bookingOrder = BookingOrder::findOrFail($bookingOrderId);
    expect($bookingOrder->status)->toBe(BookingOrder::STATUS_PENDING)
        ->and($bookingOrder->lease_id)->toBeNull();

    expect(Lease::count())->toBe(0);

    // 2. DOKU Webhook arrives with SUCCESS payment for this booking order reference
    $clientId = 'BRN-0208-1788852244810';
    $secretKey = 'SK-vkKdx1b9ZOLoYiyeMuqz';
    $target = '/api/webhooks/payment/doku';
    $requestId = 'REQ-' . uniqid();
    $timestamp = gmdate('Y-m-d\TH:i:s\Z');

    $payload = [
        'order' => [
            'invoice_number' => $reference,
            'amount' => 1500000,
        ],
        'transaction' => [
            'status' => 'SUCCESS',
            'date' => '2026-09-16T16:00:00Z',
            'original_request_id' => $requestId,
        ],
        'channel' => ['id' => 'VIRTUAL_ACCOUNT_BCA'],
    ];

    $rawBody = json_encode($payload);
    $digest = base64_encode(hash('sha256', $rawBody, true));
    $component = "Client-Id:{$clientId}\nRequest-Id:{$requestId}\nRequest-Timestamp:{$timestamp}\nRequest-Target:{$target}\nDigest:{$digest}";
    $signature = 'HMACSHA256=' . base64_encode(hash_hmac('sha256', $component, $secretKey, true));

    $webhookResponse = $this->call(
        'POST',
        $target,
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CLIENT_ID' => $clientId,
            'HTTP_REQUEST_ID' => $requestId,
            'HTTP_REQUEST_TIMESTAMP' => $timestamp,
            'HTTP_SIGNATURE' => $signature,
            'HTTP_REQUEST_TARGET' => $target,
        ],
        $rawBody,
    );

    $webhookResponse->assertOk()
        ->assertJson(['status' => 'processed']);

    // 3. Verify that the booking order is now fulfilled and lease created!
    $bookingOrder->refresh();
    expect($bookingOrder->status)->toBe(BookingOrder::STATUS_PAID)
        ->and($bookingOrder->lease_id)->not->toBeNull()
        ->and($bookingOrder->invoice_id)->not->toBeNull()
        ->and($bookingOrder->paid_at)->not->toBeNull();

    // Verify Unit is now Occupied
    $unit->refresh();
    expect($unit->status)->toBe(UnitStatus::Occupied);

    // Verify Lease was created
    $lease = Lease::find($bookingOrder->lease_id);
    expect($lease)->not->toBeNull()
        ->and($lease->unit_id)->toBe($unit->id)
        ->and($lease->status)->toBe(LeaseStatus::Active);

    // Verify Tenant was created
    $tenant = Tenant::where('phone', '6281277773333')->first();
    expect($tenant)->not->toBeNull()
        ->and($tenant->name)->toBe('Dewi Lestari')
        ->and($bookingOrder->tenant_id)->toBe($tenant->id);

    // Verify Invoice is created and Paid
    $invoice = Invoice::find($bookingOrder->invoice_id);
    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and((float) $invoice->amount_paid)->toBe(1500000.0);

    // Verify Payment table record created
    $payment = Payment::where('invoice_id', $invoice->id)->first();
    expect($payment)->not->toBeNull()
        ->and((float) $payment->amount)->toBe(1500000.0)
        ->and($payment->payment_method)->toBe('gateway');
});

test('order fails if unit is unavailable or under maintenance', function () {
    $unit = Unit::factory()->create([
        'status' => UnitStatus::Maintenance,
    ]);

    $response = $this->postJson('/api/v1/bookings', [
        'unit_id' => $unit->id,
        'name' => 'John Doe',
        'phone' => '081211112222',
        'start_date' => '2026-10-01',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'This room is currently under maintenance or unavailable for booking.');
});
