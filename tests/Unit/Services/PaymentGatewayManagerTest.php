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
        ->and($provider['secret_fields'])->toBe(['secret_key'])
        ->and($provider['configuration_schema']['environment']['presentation'])->toBe('segmented')
        ->and($provider['configuration_schema']['environment']['default'])->toBe('sandbox')
        ->and($provider['configuration_schema']['webhook_setup']['instructions'])->toBe([
            'Open the webhook settings.',
            'Add the webhook URL shown below.',
        ])
        ->and($provider['configuration_schema']['webhook_setup']['link'])->toBe([
            'label' => 'Open webhook settings',
            'url' => 'https://example.test/webhooks',
        ])
        ->and($provider['configuration_schema']['webhook_setup']['url'])->toBe('/api/webhooks/test')
        ->and($provider['configuration_schema']['secret_key']['description'])->toBe('Keep this value secret.')
        ->and($provider['configuration_schema']['secret_key']['visible_when'])->toBe([
            'field' => 'environment',
            'value' => 'sandbox',
        ]);
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

it('rejects gateways whose contract key differs from the registry key', function () {
    $registry = new PaymentRegistry;
    $registry->registerGateway('registry/gateway', MismatchedManagerTestPaymentGateway::class);

    $gateway = new PaymentGatewayManager($registry, app(SettingsManager::class), app());

    expect($gateway->find('registry/gateway'))->toBeNull()
        ->and($gateway->all()[0]['status'])->toBe('unavailable');
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
                'presentation' => 'segmented',
                'default' => 'sandbox',
                'options' => [
                    ['value' => 'sandbox', 'label' => 'Sandbox'],
                    ['value' => 'production', 'label' => 'Production'],
                ],
            ],
            'webhook_setup' => [
                'label' => 'Webhook setup',
                'type' => 'info',
                'instructions' => [
                    'Open the webhook settings.',
                    'Add the webhook URL shown below.',
                ],
                'link' => [
                    'label' => 'Open webhook settings',
                    'url' => 'https://example.test/webhooks',
                ],
                'url' => '/api/webhooks/test',
            ],
            'secret_key' => [
                'label' => 'Secret key',
                'type' => 'password',
                'required' => true,
                'description' => 'Keep this value secret.',
                'visible_when' => [
                    'field' => 'environment',
                    'value' => 'sandbox',
                ],
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

class MismatchedManagerTestPaymentGateway extends ManagerTestPaymentGateway
{
    public function key(): string
    {
        return 'provider/gateway';
    }
}
