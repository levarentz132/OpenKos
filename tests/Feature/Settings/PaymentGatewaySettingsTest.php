<?php

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Collection;
use OpenKOS\Core\Contracts\PaymentGateway;
use OpenKOS\Core\Data\Payment\CheckoutInstructions;
use OpenKOS\Core\Data\Payment\PaymentCreationResult;
use OpenKOS\Core\Data\Payment\PaymentRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookResult;
use OpenKOS\Core\Enums\PaymentStatus;
use OpenKOS\Platform\Payment\PaymentRegistry;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    app(PaymentRegistry::class)->registerGateway('test/billing', BillingTestPaymentGateway::class);
});

it('renders registered gateways and redacts only secret values', function () {
    $owner = User::factory()->owner()->create();

    Setting::set(PaymentGatewayManager::CONFIG_KEY, [
        'test/billing' => [
            'environment' => 'sandbox',
            'secret_key' => 'top-secret',
        ],
    ], 'encrypted:array');
    Setting::set(PaymentGatewayManager::ACTIVE_KEY, 'test/billing');

    $this->actingAs($owner)
        ->get(route('settings.payment-gateway.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/payment-gateway')
            ->where('active_key', 'test/billing')
            ->where('active_status', 'active')
            ->where('gateways', function (Collection $gateways): bool {
                $gateway = $gateways->firstWhere('key', 'test/billing');

                return ($gateway['configuration']['environment'] ?? null) === 'sandbox'
                    && ! array_key_exists('secret_key', $gateway['configuration'] ?? [])
                    && ($gateway['secret_fields'][0] ?? null) === 'secret_key';
            }));
});

it('encrypts configuration and activates a complete gateway', function () {
    $owner = User::factory()->owner()->create();

    $this->from(route('settings.payment-gateway.edit'))
        ->actingAs($owner)
        ->patch(route('settings.payment-gateway.update'), [
            'gateway' => 'test/billing',
            'configuration' => [
                'environment' => 'production',
                'secret_key' => 'top-secret',
            ],
        ])
        ->assertRedirect(route('settings.payment-gateway.edit'));

    $stored = Setting::where('key', PaymentGatewayManager::CONFIG_KEY)->firstOrFail();

    expect($stored->type)->toBe('encrypted:array')
        ->and($stored->value)->not->toContain('top-secret')
        ->and(Setting::get(PaymentGatewayManager::ACTIVE_KEY))->toBe('test/billing')
        ->and(Setting::get(PaymentGatewayManager::CONFIG_KEY)['test/billing']['secret_key'])->toBe('top-secret');
});

it('rejects activating an incomplete gateway', function () {
    $owner = User::factory()->owner()->create();

    $this->from(route('settings.payment-gateway.edit'))
        ->actingAs($owner)
        ->patch(route('settings.payment-gateway.update'), [
            'gateway' => 'test/billing',
            'configuration' => ['environment' => 'sandbox'],
        ])
        ->assertSessionHasErrors(['configuration.secret_key']);

    expect(Setting::get(PaymentGatewayManager::ACTIVE_KEY))->toBeNull();
});

it('preserves an existing secret when the password field is left blank', function () {
    $owner = User::factory()->owner()->create();

    Setting::set(PaymentGatewayManager::CONFIG_KEY, [
        'test/billing' => [
            'environment' => 'sandbox',
            'secret_key' => 'top-secret',
        ],
    ], 'encrypted:array');
    Setting::set(PaymentGatewayManager::ACTIVE_KEY, 'test/billing');

    $this->actingAs($owner)
        ->patch(route('settings.payment-gateway.update'), [
            'gateway' => 'test/billing',
            'configuration' => ['environment' => 'production'],
        ])
        ->assertRedirect();

    expect(Setting::get(PaymentGatewayManager::CONFIG_KEY)['test/billing'])->toBe([
        'environment' => 'production',
        'secret_key' => 'top-secret',
    ]);
});

it('shows a missing active provider without clearing its configured key', function () {
    $owner = User::factory()->owner()->create();
    Setting::set(PaymentGatewayManager::ACTIVE_KEY, 'missing/gateway');

    $this->actingAs($owner)
        ->get(route('settings.payment-gateway.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('active_key', 'missing/gateway')
            ->where('active_status', 'unavailable'));
});

it('forbids tenant-linked users from viewing and updating Billing settings', function () {
    $tenantUser = User::factory()->create();
    Tenant::factory()->withUser($tenantUser)->create();

    $this->actingAs($tenantUser)
        ->get(route('settings.payment-gateway.edit'))
        ->assertForbidden();

    $this->actingAs($tenantUser)
        ->patch(route('settings.payment-gateway.update'), [
            'gateway' => 'test/billing',
            'configuration' => [],
        ])
        ->assertForbidden();
});

class BillingTestPaymentGateway implements PaymentGateway
{
    public function __construct(public array $config = []) {}

    public function key(): string
    {
        return 'test/billing';
    }

    public function displayName(): string
    {
        return 'Billing Test Gateway';
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
