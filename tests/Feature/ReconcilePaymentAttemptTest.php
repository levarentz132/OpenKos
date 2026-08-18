<?php

use App\Actions\Payments\ReconcilePaymentAttempt;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Results\Payment\ReconcilePaymentAttemptResult;
use App\Services\Payments\PaymentGatewayManager;
use OpenKOS\Core\Contracts\PaymentGateway;
use OpenKOS\Core\Contracts\PaymentGatewayStatusLookup;
use OpenKOS\Core\Data\Payment\Money;
use OpenKOS\Core\Data\Payment\PaymentProviderResult;
use OpenKOS\Core\Enums\PaymentStatus;

function bindStatusLookupGateway(PaymentProviderResult $result): void
{
    $gateway = Mockery::mock(PaymentGateway::class, PaymentGatewayStatusLookup::class);
    $gateway->shouldReceive('lookupPaymentStatus')->once()->andReturn($result);

    $manager = Mockery::mock(PaymentGatewayManager::class);
    $manager->shouldReceive('find')->with('test-gateway')->andReturn($gateway);

    app()->instance(PaymentGatewayManager::class, $manager);
}

it('settles a provider-reported payment through the shared accounting path', function () {
    $invoice = Invoice::factory()->create([
        'total' => 1_500_000,
        'amount_paid' => 0,
    ]);
    $attempt = PaymentAttempt::factory()->for($invoice)->create([
        'gateway_key' => 'test-gateway',
        'provider_reference' => 'provider-1',
        'reference' => 'attempt-1',
        'amount' => 1_500_000,
        'currency' => 'IDR',
    ]);
    bindStatusLookupGateway(new PaymentProviderResult(
        providerReference: 'provider-1',
        status: PaymentStatus::Settled,
        reference: $attempt->reference,
        amount: new Money(1_500_000, 'IDR'),
        metadata: ['provider_status' => 'COMPLETED'],
    ));

    $result = app(ReconcilePaymentAttempt::class)->execute($attempt);

    expect($result->status)->toBe(ReconcilePaymentAttemptResult::SETTLED)
        ->and($attempt->fresh()->status)->toBe(PaymentStatus::Settled)
        ->and($attempt->fresh()->payment_id)->not->toBeNull()
        ->and(Payment::query()->count())->toBe(1)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('keeps an active provider payment pending without creating accounting', function () {
    $invoice = Invoice::factory()->create(['total' => 1_500_000]);
    $attempt = PaymentAttempt::factory()->for($invoice)->create([
        'gateway_key' => 'test-gateway',
        'provider_reference' => 'provider-1',
        'reference' => 'attempt-1',
        'amount' => 1_500_000,
    ]);
    bindStatusLookupGateway(new PaymentProviderResult(
        providerReference: 'provider-1',
        status: PaymentStatus::Pending,
        reference: $attempt->reference,
        amount: new Money(1_500_000, 'IDR'),
        metadata: ['provider_status' => 'ACTIVE'],
    ));

    $result = app(ReconcilePaymentAttempt::class)->execute($attempt);

    expect($result->status)->toBe(ReconcilePaymentAttemptResult::PENDING)
        ->and($attempt->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and(Payment::query()->count())->toBe(0);
});

it('does not reconcile an attempt without a provider reference', function () {
    $attempt = PaymentAttempt::factory()->create(['provider_reference' => null]);

    $result = app(ReconcilePaymentAttempt::class)->execute($attempt);

    expect($result->status)->toBe(ReconcilePaymentAttemptResult::UNSUPPORTED)
        ->and($attempt->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('does not settle a fully paid invoice through an old gateway attempt', function () {
    $invoice = Invoice::factory()->create([
        'total' => 1_500_000,
        'amount_paid' => 1_500_000,
        'status' => InvoiceStatus::Paid,
    ]);
    $attempt = PaymentAttempt::factory()->for($invoice)->create([
        'gateway_key' => 'test-gateway',
        'provider_reference' => 'provider-1',
        'reference' => 'attempt-1',
        'amount' => 1_500_000,
    ]);
    bindStatusLookupGateway(new PaymentProviderResult(
        providerReference: 'provider-1',
        status: PaymentStatus::Settled,
        reference: $attempt->reference,
        amount: new Money(1_500_000, 'IDR'),
    ));

    $result = app(ReconcilePaymentAttempt::class)->execute($attempt);

    expect($result->status)->toBe(ReconcilePaymentAttemptResult::ANOMALY)
        ->and($attempt->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and(Payment::query()->count())->toBe(0);
});
