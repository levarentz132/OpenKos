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

    $property = Property::factory()->create(['name' => 'Highlander Stay Grogol', 'deposit_amount' => 0]);
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

    $property = Property::factory()->create(['name' => 'Highlander Stay', 'deposit_amount' => 0]);
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
        ->assertJsonPath('message', 'Pesanan kamar berhasil dibatalkan.');

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

    $property = Property::factory()->create(['deposit_amount' => 0]);
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

test('competing pending booking orders are automatically cancelled when another user completes payment', function () {
    $checkoutUrl = 'https://staging.doku.com/checkout-link-v2/compete-test';

    Http::fake([
        'https://api-sandbox.doku.com/checkout/v1/payment' => Http::response([
            'order' => ['invoice_number' => 'dummy'],
            'payment' => [
                'url' => $checkoutUrl,
                'token_id' => 'TKN-COMPETE',
            ],
            'message' => ['SUCCESS'],
        ], 200),
    ]);

    $property = Property::factory()->create();
    $unit = Unit::factory()->withRate(1800000)->create([
        'property_id' => $property->id,
        'capacity' => 1,
        'status' => UnitStatus::Available,
    ]);

    // User A books the unit (Order A)
    $resA = $this->postJson('/api/v1/cart', [
        'unit_id' => $unit->id,
        'name' => 'User A (Unpaid)',
        'phone' => '081211110001',
        'start_date' => '2026-10-01',
    ]);
    $resA->assertCreated();
    $orderA = BookingOrder::findOrFail($resA->json('order.id'));
    expect($orderA->status)->toBe(BookingOrder::STATUS_PENDING);

    // User B also books the unit (Order B)
    $resB = $this->postJson('/api/v1/cart', [
        'unit_id' => $unit->id,
        'name' => 'User B (First to Pay)',
        'phone' => '081211110002',
        'start_date' => '2026-10-01',
    ]);
    $resB->assertCreated();
    $orderB = BookingOrder::findOrFail($resB->json('order.id'));
    expect($orderB->status)->toBe(BookingOrder::STATUS_PENDING);

    // User B completes payment via DOKU webhook
    $clientId = 'BRN-0208-1788852244810';
    $secretKey = 'SK-vkKdx1b9ZOLoYiyeMuqz';
    $target = '/api/webhooks/payment/doku';
    $requestId = 'REQ-' . uniqid();
    $timestamp = gmdate('Y-m-d\TH:i:s\Z');

    $payload = [
        'order' => [
            'invoice_number' => $orderB->reference,
            'amount' => 1800000,
        ],
        'transaction' => [
            'status' => 'SUCCESS',
            'date' => '2026-09-16T16:00:00Z',
            'original_request_id' => $requestId,
        ],
        'channel' => ['id' => 'QRIS'],
    ];

    $rawBody = json_encode($payload);
    $digest = base64_encode(hash('sha256', $rawBody, true));
    $component = "Client-Id:{$clientId}\nRequest-Id:{$requestId}\nRequest-Timestamp:{$timestamp}\nRequest-Target:{$target}\nDigest:{$digest}";
    $signature = 'HMACSHA256=' . base64_encode(hash_hmac('sha256', $component, $secretKey, true));

    $webhookResponse = $this->call('POST', $target, [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_CLIENT_ID' => $clientId,
        'HTTP_REQUEST_ID' => $requestId,
        'HTTP_REQUEST_TIMESTAMP' => $timestamp,
        'HTTP_SIGNATURE' => $signature,
        'HTTP_REQUEST_TARGET' => $target,
    ], $rawBody);

    $webhookResponse->assertOk()->assertJson(['status' => 'processed']);

    // Order B is PAID
    $orderB->refresh();
    expect($orderB->status)->toBe(BookingOrder::STATUS_PAID)
        ->and($orderB->lease_id)->not->toBeNull();

    // Order A is automatically CANCELLED with conflict note
    $orderA->refresh();
    expect($orderA->status)->toBe(BookingOrder::STATUS_CANCELLED)
        ->and($orderA->notes)->toContain('Dibatalkan otomatis: Kamar telah dibayar oleh pengguna lain');

    // Unit is now Occupied
    $unit->refresh();
    expect($unit->status)->toBe(UnitStatus::Occupied);
});

test('cart checkout fails with 422 ROOM_ALREADY_PAID when unit has already been paid and occupied by another user', function () {
    $property = Property::factory()->create();
    $unit = Unit::factory()->withRate(2000000)->create([
        'property_id' => $property->id,
        'capacity' => 1,
        'status' => UnitStatus::Available,
    ]);

    // User creates booking order in cart
    $res = $this->postJson('/api/v1/cart', [
        'unit_id' => $unit->id,
        'name' => 'Late Payer',
        'phone' => '081298765432',
        'start_date' => '2026-10-01',
    ]);
    $res->assertCreated();
    $orderId = $res->json('order.id');

    // In the meantime, another tenant occupied the unit
    $unit->update(['status' => UnitStatus::Occupied]);

    // Late Payer attempts to checkout
    $checkoutRes = $this->postJson("/api/v1/cart/{$orderId}/checkout");
    $checkoutRes->assertStatus(422)
        ->assertJsonPath('code', 'ROOM_ALREADY_PAID')
        ->assertJsonPath('message', 'Maaf, kamar ini baru saja disewa dan dibayar oleh pengguna lain. Silakan pilih kamar lain yang masih tersedia.');

    // Booking order is now marked cancelled
    $order = BookingOrder::findOrFail($orderId);
    expect($order->status)->toBe(BookingOrder::STATUS_CANCELLED);
});

test('double payment race condition safely marks second paid order as payment_conflict without crashing', function () {
    $property = Property::factory()->create();
    $unit = Unit::factory()->withRate(1500000)->create([
        'property_id' => $property->id,
        'capacity' => 1,
        'status' => UnitStatus::Available,
    ]);

    // Order 1 (User A)
    $orderA = BookingOrder::create([
        'cart_token' => 'cart-a',
        'reference' => 'BK-ORDER-AAA',
        'unit_id' => $unit->id,
        'guest_name' => 'User A (First to settle)',
        'guest_phone' => '628111111111',
        'guest_email' => 'usera@example.com',
        'start_date' => '2026-10-01',
        'amount' => 1500000,
        'status' => BookingOrder::STATUS_PENDING,
    ]);

    // Order 2 (User B)
    $orderB = BookingOrder::create([
        'cart_token' => 'cart-b',
        'reference' => 'BK-ORDER-BBB',
        'unit_id' => $unit->id,
        'guest_name' => 'User B (Simultaneous payment)',
        'guest_phone' => '628222222222',
        'guest_email' => 'userb@example.com',
        'start_date' => '2026-10-01',
        'amount' => 1500000,
        'status' => BookingOrder::STATUS_PENDING,
    ]);

    $clientId = 'BRN-0208-1788852244810';
    $secretKey = 'SK-vkKdx1b9ZOLoYiyeMuqz';
    $target = '/api/webhooks/payment/doku';

    // 1. Webhook for Order A settles first
    $reqA = 'REQ-A-' . uniqid();
    $timeA = gmdate('Y-m-d\TH:i:s\Z');
    $payloadA = [
        'order' => ['invoice_number' => $orderA->reference, 'amount' => 1500000],
        'transaction' => ['status' => 'SUCCESS', 'date' => $timeA, 'original_request_id' => $reqA],
        'channel' => ['id' => 'BCA_VA'],
    ];
    $rawA = json_encode($payloadA);
    $digestA = base64_encode(hash('sha256', $rawA, true));
    $sigA = 'HMACSHA256=' . base64_encode(hash_hmac('sha256', "Client-Id:{$clientId}\nRequest-Id:{$reqA}\nRequest-Timestamp:{$timeA}\nRequest-Target:{$target}\nDigest:{$digestA}", $secretKey, true));

    $resA = $this->call('POST', $target, [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_CLIENT_ID' => $clientId,
        'HTTP_REQUEST_ID' => $reqA,
        'HTTP_REQUEST_TIMESTAMP' => $timeA,
        'HTTP_SIGNATURE' => $sigA,
        'HTTP_REQUEST_TARGET' => $target,
    ], $rawA);

    $resA->assertOk()->assertJson(['status' => 'processed']);
    $orderA->refresh();
    expect($orderA->status)->toBe(BookingOrder::STATUS_PAID);

    // 2. Webhook for Order B arrives next (User B also paid before discovering it was occupied)
    $reqB = 'REQ-B-' . uniqid();
    $timeB = gmdate('Y-m-d\TH:i:s\Z');
    $payloadB = [
        'order' => ['invoice_number' => $orderB->reference, 'amount' => 1500000],
        'transaction' => ['status' => 'SUCCESS', 'date' => $timeB, 'original_request_id' => $reqB],
        'channel' => ['id' => 'QRIS'],
    ];
    $rawB = json_encode($payloadB);
    $digestB = base64_encode(hash('sha256', $rawB, true));
    $sigB = 'HMACSHA256=' . base64_encode(hash_hmac('sha256', "Client-Id:{$clientId}\nRequest-Id:{$reqB}\nRequest-Timestamp:{$timeB}\nRequest-Target:{$target}\nDigest:{$digestB}", $secretKey, true));

    $resB = $this->call('POST', $target, [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_CLIENT_ID' => $clientId,
        'HTTP_REQUEST_ID' => $reqB,
        'HTTP_REQUEST_TIMESTAMP' => $timeB,
        'HTTP_SIGNATURE' => $sigB,
        'HTTP_REQUEST_TARGET' => $target,
    ], $rawB);

    // Webhook should return HTTP 200 with status 'payment_conflict' so gateway doesn't retry loop
    $resB->assertOk()->assertJson(['status' => 'payment_conflict']);

    // Order B is safely marked as payment_conflict with full tracking for admin
    $orderB->refresh();
    expect($orderB->status)->toBe(BookingOrder::STATUS_PAYMENT_CONFLICT)
        ->and($orderB->paid_at)->not->toBeNull()
        ->and($orderB->notes)->toContain('OVERBOOKING DETECTED')
        ->and($orderB->lease_id)->toBeNull();

    // User A's lease remains valid and untouched
    expect(Lease::count())->toBe(1);
});

