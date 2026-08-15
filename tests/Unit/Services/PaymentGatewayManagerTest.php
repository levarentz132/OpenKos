<?php

use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenKOS\Core\Contracts\PaymentGateway;
use OpenKOS\Core\Data\Payment\CheckoutInstructions;
use OpenKOS\Core\Data\Payment\PaymentCreationResult;
use OpenKOS\Core\Data\Payment\PaymentRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookResult;
use OpenKOS\Core\Enums\PaymentStatus;
use OpenKOS\Platform\Payment\PaymentRegistry;
use OpenKOS\Platform\Settings\SettingsManager;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns provider metadata without exposing secret values', function () {
    $registry = new PaymentRegistry;
    $registry->registerGateway('test/gateway', ManagerTestPaymentGateway::class);
    $settings = app(SettingsManager::class);
    $settings->set(PaymentGatewayManager::CONFIG_KEY, [
        'test/gateway' => [
            'environment' => 'sandbox',
            'secret_key' => 'top-secret',
        ],
    ]);

    $gateway = new PaymentGatewayManager($registry, $settings, app());
    $provider = $gateway->all()[0];

    expect($provider['status'])->toBe('configured')
        ->and($provider['configuration'])->toBe(['environment' => 'sandbox'])
        ->and($provider['secret_fields'])->toBe(['secret_key']);
});

it('resolves only a configured active gateway', function () {
    $registry = new PaymentRegistry;
    $registry->registerGateway('test/gateway', ManagerTestPaymentGateway::class);
    $settings = app(SettingsManager::class);
    $settings->set(PaymentGatewayManager::CONFIG_KEY, [
        'test/gateway' => [
            'environment' => 'sandbox',
            'secret_key' => 'top-secret',
        ],
    ]);
    $settings->set(PaymentGatewayManager::ACTIVE_KEY, 'test/gateway');

    $gateway = new PaymentGatewayManager($registry, $settings, app());

    expect($gateway->activeKey())->toBe('test/gateway')
        ->and($gateway->find('test/gateway'))->toBeInstanceOf(ManagerTestPaymentGateway::class)
        ->and($gateway->active())->toBeInstanceOf(ManagerTestPaymentGateway::class);

    $settings->set(PaymentGatewayManager::ACTIVE_KEY, 'missing/gateway');

    expect($gateway->activeKey())->toBe('missing/gateway')
        ->and($gateway->find('missing/gateway'))->toBeNull()
        ->and($gateway->active())->toBeNull();
});

it('keeps broken providers visible without making enumeration throw', function () {
    $registry = new PaymentRegistry;
    $registry->registerGateway('broken/gateway', BrokenManagerTestPaymentGateway::class);
    $settings = app(SettingsManager::class);

    $gateway = new PaymentGatewayManager($registry, $settings, app());
    $provider = $gateway->all()[0];

    expect($provider['status'])->toBe('unavailable')
        ->and($provider['error'])->toBe('This payment gateway is unavailable.')
        ->and($gateway->find('broken/gateway'))->toBeNull();
});

class ManagerTestPaymentGateway implements PaymentGateway
{
    public function __construct(public array $config = []) {}

    public function key(): string
    {
        return 'test/gateway';
    }

    public function displayName(): string
    {
        return 'Test Gateway';
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
        return [
            'environment' => [
                'label' => 'Environment',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'sandbox', 'label' => 'Sandbox'],
                    ['value' => 'production', 'label' => 'Production'],
                ],
            ],
            'secret_key' => [
                'label' => 'Secret key',
                'type' => 'password',
                'required' => true,
            ],
        ];
    }
}

class BrokenManagerTestPaymentGateway implements PaymentGateway
{
    public function __construct()
    {
        throw new RuntimeException('broken gateway');
    }

    public function key(): string
    {
        return 'broken/gateway';
    }

    public function displayName(): string
    {
        return 'Broken Gateway';
    }

    public function createPayment(PaymentRequest $request): PaymentCreationResult
    {
        throw new RuntimeException('broken gateway');
    }

    public function handleCallback(PaymentWebhookRequest $request): PaymentWebhookResult
    {
        throw new RuntimeException('broken gateway');
    }

    public function configurationSchema(): array
    {
        return [];
    }
}
