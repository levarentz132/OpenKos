<?php

use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitRate;
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

test('guest can create booking order which creates tenant, lease, invoice, and checkout url', function () {
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
        ->assertJsonPath('message', 'Order created successfully. Lease and invoice have been generated.')
        ->assertJsonPath('order.property.name', 'Highlander Stay Grogol')
        ->assertJsonPath('order.unit.name', 'Kamar 101')
        ->assertJsonPath('order.tenant.name', 'Budi Santoso')
        ->assertJsonPath('order.tenant.phone', '6281299998888')
        ->assertJsonPath('order.invoice.total', 1750000)
        ->assertJsonPath('order.invoice.status', 'pending')
        ->assertJsonPath('order.checkout_url', $checkoutUrl)
        ->assertJsonStructure([
            'order' => [
                'lease_id',
                'lease_reference',
                'token',
            ],
        ]);

    // Verify database entities created
    $tenant = Tenant::where('phone', '6281299998888')->first();
    expect($tenant)->not->toBeNull();

    $unit->refresh();
    expect($unit->status)->toBe(UnitStatus::Occupied);

    $lease = $tenant->leases()->first();
    expect($lease)->not->toBeNull()
        ->and($lease->unit_id)->toBe($unit->id)
        ->and($lease->status)->toBe(LeaseStatus::Active);

    $invoice = $lease->invoices()->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Pending)
        ->and((float) $invoice->total)->toBe(1750000.0);
});

test('booking order payment webhook automatically records payment and settles invoice', function () {
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

    // 1. Guest creates booking order
    $orderRes = $this->postJson('/api/v1/bookings', [
        'unit_id' => $unit->id,
        'name' => 'Dewi Lestari',
        'phone' => '081277773333',
        'email' => 'dewi@example.com',
        'start_date' => '2026-10-01',
        'duration_months' => 1,
    ]);

    $orderRes->assertCreated();
    $invoiceId = $orderRes->json('order.invoice.id');
    $invoice = Invoice::findOrFail($invoiceId);
    $attempt = $invoice->paymentAttempts()->latest('id')->first();
    expect($attempt)->not->toBeNull();

    // 2. DOKU Webhook arrives with SUCCESS payment
    $clientId = 'BRN-0208-1788852244810';
    $secretKey = 'SK-vkKdx1b9ZOLoYiyeMuqz';
    $target = '/api/webhooks/payment/doku';
    $requestId = 'REQ-' . uniqid();
    $timestamp = gmdate('Y-m-d\TH:i:s\Z');

    $payload = [
        'order' => [
            'invoice_number' => $attempt->reference,
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

    // 3. Verify Payment record created and invoice is marked Paid
    $invoice->refresh();
    $attempt->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and((float) $invoice->amount_paid)->toBe(1500000.0)
        ->and($attempt->status)->toBe(PaymentStatus::Settled);

    // Verify Payment table record
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

test('order fails if tenant already has an active lease', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create([
        'user_id' => $user->id,
        'phone' => '628123456789',
    ]);

    $unit1 = Unit::factory()->create(['status' => UnitStatus::Occupied]);
    Lease::factory()->create([
        'primary_tenant_id' => $tenant->id,
        'unit_id' => $unit1->id,
        'status' => LeaseStatus::Active,
    ]);

    $unit2 = Unit::factory()->create(['status' => UnitStatus::Available]);

    $response = $this->postJson('/api/v1/bookings', [
        'unit_id' => $unit2->id,
        'name' => 'Existing Tenant',
        'phone' => '08123456789',
        'start_date' => '2026-10-01',
    ]);

    $response->assertStatus(422);
});
