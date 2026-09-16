<?php

use App\Actions\Payments\StartGatewayPayment;
use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Property;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Payments\Gateways\DokuPaymentGateway;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\Http;
use OpenKOS\Core\Data\Payment\Money;
use OpenKOS\Core\Data\Payment\PaymentRequest;
use OpenKOS\Core\Data\Payment\PaymentStatusLookupRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookRequest;
use OpenKOS\Core\Enums\PaymentStatus;
use OpenKOS\Core\Exceptions\PaymentWebhookVerificationException;

beforeEach(function () {
    config([
        'services.doku.client_id' => 'TEST-CLIENT-ID',
        'services.doku.secret_key' => 'TEST-SECRET-KEY',
        'services.doku.environment' => 'sandbox',
    ]);
});

test('doku payment gateway is registered and resolvable', function () {
    $manager = app(PaymentGatewayManager::class);

    expect($manager->find('doku'))->toBeInstanceOf(DokuPaymentGateway::class)
        ->and($manager->supportsStatusLookup('doku'))->toBeTrue();

    $gateway = $manager->find('doku');
    expect($gateway->key())->toBe('doku')
        ->and($gateway->displayName())->toBe('DOKU Checkout (Jokul)')
        ->and($gateway->configurationSchema())->toBeArray()
        ->and(array_keys($gateway->configurationSchema()))->toContain('client_id', 'secret_key', 'environment');
});

test('doku gateway creates checkout session with valid hmac signature', function () {
    $checkoutUrl = 'https://staging.doku.com/checkout-link-v2/dummy-token-123';

    Http::fake([
        'https://api-sandbox.doku.com/checkout/v1/payment' => Http::response([
            'order' => [
                'invoice_number' => 'INV-TEST-001',
            ],
            'payment' => [
                'url' => $checkoutUrl,
                'token_id' => 'TOKEN-123',
                'expired_date' => '2026-09-16T17:00:00Z',
            ],
            'message' => ['SUCCESS'],
        ], 200),
    ]);

    $gateway = new DokuPaymentGateway([
        'client_id' => 'BRN-TEST',
        'secret_key' => 'SK-TEST',
        'environment' => 'sandbox',
    ]);

    $request = new PaymentRequest(
        reference: 'INV-TEST-001',
        amount: new Money(150000, 'IDR'),
        description: 'Invoice INV-TEST-001',
    );

    $result = $gateway->createPayment($request);

    expect($result->status)->toBe(PaymentStatus::Pending)
        ->and($result->providerReference)->toBe('INV-TEST-001')
        ->and($result->instructions->url)->toBe($checkoutUrl)
        ->and($result->amount->minorUnits)->toBe(150000);

    Http::assertSent(function ($req) {
        $headers = $req->headers();

        return $headers['Client-Id'][0] === 'BRN-TEST'
            && isset($headers['Request-Id'][0])
            && isset($headers['Request-Timestamp'][0])
            && str_starts_with($headers['Signature'][0], 'HMACSHA256=')
            && $req['order']['amount'] === 150000
            && $req['order']['invoice_number'] === 'INV-TEST-001';
    });
});

test('doku webhook verification succeeds with valid signature and settles attempt', function () {
    $secretKey = 'SK-WEBHOOK-TEST';
    $clientId = 'BRN-WEBHOOK-TEST';
    $requestId = 'REQ-NOTIF-001';
    $timestamp = '2026-09-16T12:00:00Z';
    $target = '/api/webhooks/payment/doku';

    $payload = [
        'service' => ['id' => 'ONLINE_PAYMENT'],
        'order' => [
            'invoice_number' => 'ATTEMPT-REF-100',
            'amount' => 500000,
        ],
        'transaction' => [
            'status' => 'SUCCESS',
            'date' => '2026-09-16T12:05:00Z',
            'original_request_id' => 'ORIG-REQ-100',
        ],
        'channel' => ['id' => 'VIRTUAL_ACCOUNT_BCA'],
    ];

    $rawBody = json_encode($payload);
    $digest = base64_encode(hash('sha256', $rawBody, true));
    $component = "Client-Id:{$clientId}\nRequest-Id:{$requestId}\nRequest-Timestamp:{$timestamp}\nRequest-Target:{$target}\nDigest:{$digest}";
    $signature = 'HMACSHA256=' . base64_encode(hash_hmac('sha256', $component, $secretKey, true));

    $gateway = new DokuPaymentGateway([
        'client_id' => $clientId,
        'secret_key' => $secretKey,
    ]);

    $webhookRequest = new PaymentWebhookRequest(
        rawBody: $rawBody,
        headers: [
            'client-id' => $clientId,
            'request-id' => $requestId,
            'request-timestamp' => $timestamp,
            'signature' => $signature,
            'request-target' => $target,
        ],
    );

    $result = $gateway->handleCallback($webhookRequest);

    expect($result->status)->toBe(PaymentStatus::Settled)
        ->and($result->reference)->toBe('ATTEMPT-REF-100')
        ->and($result->providerReference)->toBe('ATTEMPT-REF-100')
        ->and($result->amount->minorUnits)->toBe(500000);
});

test('doku webhook rejects invalid signature', function () {
    $gateway = new DokuPaymentGateway([
        'client_id' => 'BRN-TEST',
        'secret_key' => 'SK-TEST',
    ]);

    $webhookRequest = new PaymentWebhookRequest(
        rawBody: json_encode(['order' => ['invoice_number' => '123']]),
        headers: [
            'client-id' => 'BRN-TEST',
            'request-id' => 'REQ-1',
            'request-timestamp' => '2026-09-16T12:00:00Z',
            'signature' => 'HMACSHA256=invalid-signature',
        ],
    );

    $gateway->handleCallback($webhookRequest);
})->throws(PaymentWebhookVerificationException::class);

test('doku webhook controller endpoint processes payment and updates invoice', function () {
    $clientId = 'BRN-0208-1788852244810';
    $secretKey = 'SK-vkKdx1b9ZOLoYiyeMuqz';

    $settings = app(\OpenKOS\Platform\Settings\SettingsManager::class);
    $settings->set('payment_gateway', 'doku');
    $settings->set('payment_gateway_config', [
        'doku' => [
            'client_id' => $clientId,
            'secret_key' => $secretKey,
            'environment' => 'sandbox',
        ],
    ]);

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['user_id' => $user->id]);
    $lease = Lease::factory()->create(['primary_tenant_id' => $tenant->id]);

    $invoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'status' => InvoiceStatus::Pending,
        'total' => 350000,
        'amount_paid' => 0,
    ]);

    $attemptRef = 'ATTEMPT-' . uniqid();
    $attempt = PaymentAttempt::create([
        'invoice_id' => $invoice->id,
        'gateway_key' => 'doku',
        'reference' => $attemptRef,
        'provider_reference' => $attemptRef,
        'amount' => 350000,
        'currency' => 'IDR',
        'status' => PaymentStatus::Pending,
    ]);

    $target = '/api/webhooks/payment/doku';
    $requestId = 'REQ-' . uniqid();
    $timestamp = gmdate('Y-m-d\TH:i:s\Z');

    $payload = [
        'order' => [
            'invoice_number' => $attemptRef,
            'amount' => 350000,
        ],
        'transaction' => [
            'status' => 'SUCCESS',
            'date' => '2026-09-16T12:00:00Z',
            'original_request_id' => $requestId,
        ],
        'channel' => ['id' => 'QRIS'],
    ];

    $rawBody = json_encode($payload);
    $digest = base64_encode(hash('sha256', $rawBody, true));
    $component = "Client-Id:{$clientId}\nRequest-Id:{$requestId}\nRequest-Timestamp:{$timestamp}\nRequest-Target:{$target}\nDigest:{$digest}";
    $signature = 'HMACSHA256=' . base64_encode(hash_hmac('sha256', $component, $secretKey, true));

    $response = $this->call(
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

    $response->assertOk()
        ->assertJson(['status' => 'processed']);

    $attempt->refresh();
    $invoice->refresh();

    expect($attempt->status)->toBe(PaymentStatus::Settled)
        ->and($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and((float) $invoice->amount_paid)->toBe(350000.0);
});

test('tenant api can initiate doku checkout session for an invoice', function () {
    $checkoutUrl = 'https://staging.doku.com/checkout-link-v2/session-xyz';

    Http::fake([
        'https://api-sandbox.doku.com/checkout/v1/payment' => Http::response([
            'order' => ['invoice_number' => 'dummy'],
            'payment' => [
                'url' => $checkoutUrl,
                'token_id' => 'TKN-XYZ',
            ],
            'message' => ['SUCCESS'],
        ], 200),
    ]);

    $settings = app(\OpenKOS\Platform\Settings\SettingsManager::class);
    $settings->set('payment_gateway', 'doku');
    $settings->set('payment_gateway_config', [
        'doku' => [
            'client_id' => 'BRN-0208-1788852244810',
            'secret_key' => 'SK-vkKdx1b9ZOLoYiyeMuqz',
            'environment' => 'sandbox',
        ],
    ]);

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['user_id' => $user->id]);
    $property = Property::factory()->create();
    $unit = Unit::factory()->create(['property_id' => $property->id]);
    $lease = Lease::factory()->create([
        'primary_tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'status' => LeaseStatus::Active,
    ]);

    $invoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'status' => InvoiceStatus::Pending,
        'total' => 200000,
        'amount_paid' => 0,
        'due_date' => now()->addDays(3),
    ]);

    $token = $user->createToken('tenant-device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/tenant/invoices/{$invoice->id}/checkout");

    $response->assertOk()
        ->assertJsonPath('message', 'Checkout session created successfully.')
        ->assertJsonPath('checkout_url', $checkoutUrl)
        ->assertJsonPath('attempt.amount', 200000)
        ->assertJsonPath('attempt.status', 'pending');

    expect($invoice->paymentAttempts()->count())->toBe(1);
});

test('doku gateway can lookup transaction status from orders api', function () {
    $invoiceNumber = 'INV-LOOKUP-999';

    Http::fake([
        'https://api-sandbox.doku.com/orders/v1/status/' . $invoiceNumber => Http::response([
            'order' => [
                'invoice_number' => $invoiceNumber,
                'amount' => 750000,
            ],
            'transaction' => [
                'status' => 'SUCCESS',
                'date' => '2026-09-16T14:30:00Z',
            ],
            'channel' => [
                'id' => 'VIRTUAL_ACCOUNT_MANDIRI',
            ],
        ], 200),
    ]);

    $gateway = new DokuPaymentGateway([
        'client_id' => 'BRN-TEST',
        'secret_key' => 'SK-TEST',
        'environment' => 'sandbox',
    ]);

    $lookupRequest = new PaymentStatusLookupRequest(
        providerReference: $invoiceNumber,
        reference: $invoiceNumber,
    );

    $result = $gateway->lookupPaymentStatus($lookupRequest);

    expect($result->status)->toBe(PaymentStatus::Settled)
        ->and($result->providerReference)->toBe($invoiceNumber)
        ->and($result->reference)->toBe($invoiceNumber)
        ->and($result->amount->minorUnits)->toBe(750000)
        ->and($result->metadata['channel_id'])->toBe('VIRTUAL_ACCOUNT_MANDIRI');

    Http::assertSent(function ($req) use ($invoiceNumber) {
        $headers = $req->headers();

        return $req->url() === 'https://api-sandbox.doku.com/orders/v1/status/' . $invoiceNumber
            && $headers['Client-Id'][0] === 'BRN-TEST'
            && isset($headers['Request-Id'][0])
            && isset($headers['Request-Timestamp'][0])
            && str_starts_with($headers['Signature'][0], 'HMACSHA256=');
    });
});
