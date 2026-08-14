<?php

use OpenKOS\Core\Contracts\PaymentGateway;
use OpenKOS\Core\Data\Payment\CheckoutInstructions;
use OpenKOS\Core\Data\Payment\PaymentCreationResult;
use OpenKOS\Core\Data\Payment\PaymentRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookResult;
use OpenKOS\Core\Enums\PaymentStatus;
use OpenKOS\Platform\Payment\PaymentRegistry;

function fakePaymentGateway(): PaymentGateway
{
    return new class implements PaymentGateway
    {
        public function key(): string
        {
            return 'fake';
        }

        public function displayName(): string
        {
            return 'Fake Gateway';
        }

        public function createPayment(PaymentRequest $request): PaymentCreationResult
        {
            return new PaymentCreationResult(
                providerReference: 'provider-reference',
                status: PaymentStatus::Pending,
                amount: $request->amount,
                instructions: new CheckoutInstructions,
            );
        }

        public function handleCallback(PaymentWebhookRequest $request): PaymentWebhookResult
        {
            return new PaymentWebhookResult(
                eventReference: 'event-reference',
                providerReference: 'provider-reference',
                status: PaymentStatus::Pending,
            );
        }

        public function configurationSchema(): array
        {
            return [];
        }
    };
}

it('registers gateways as class-strings or instances', function () {
    $registry = new PaymentRegistry;
    $instance = fakePaymentGateway();

    $registry->registerGateway('by-class', $instance::class)
        ->registerGateway('by-instance', $instance);

    expect($registry->has('by-class'))->toBeTrue()
        ->and($registry->has('missing'))->toBeFalse()
        ->and($registry->gateways())->toBe(['by-class' => $instance::class, 'by-instance' => $instance]);
});

it('serializes to gateway keys only', function () {
    $registry = new PaymentRegistry;
    $registry->registerGateway('fake', fakePaymentGateway());

    expect($registry->toArray())->toBe(['fake']);
});

it('throws on duplicate gateway keys', function () {
    $registry = new PaymentRegistry;
    $registry->registerGateway('fake', fakePaymentGateway());

    $registry->registerGateway('fake', fakePaymentGateway());
})->throws(InvalidArgumentException::class, 'Payment gateway [fake] is already registered.');
