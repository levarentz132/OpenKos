<?php

namespace App\Http\Controllers\Settings;

use App\Events\Settings\SettingsUpdated;
use App\Http\Controllers\Controller;
use App\Models\PaymentAttempt;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use OpenKOS\Core\Enums\PaymentStatus;
use OpenKOS\Platform\Settings\SettingsManager;

class PaymentGatewayController extends Controller
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private SettingsManager $settings,
    ) {}

    public function edit(): Response
    {
        $gateways = $this->gateways->all();
        $activeKey = $this->gateways->activeKey();
        $activeGateway = collect($gateways)->firstWhere('key', $activeKey);

        return Inertia::render('settings/payment-gateway', [
            'gateways' => $gateways,
            'active_key' => $activeKey,
            'active_status' => match (true) {
                $activeKey === null => 'none',
                $activeGateway === null => 'unavailable',
                $activeGateway['status'] !== 'configured' => $activeGateway['status'],
                default => 'active',
            },
            'active_payment_attempt_count' => $this->activePaymentAttemptCount(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gateway' => ['nullable', 'string'],
            'configuration' => ['nullable', 'array'],
        ]);

        $gatewayKey = $validated['gateway'] ?? null;
        $configuration = $validated['configuration'] ?? [];
        $activePaymentAttemptCount = $this->activePaymentAttemptCount();

        if ($activePaymentAttemptCount > 0 && $gatewayKey !== $this->gateways->activeKey()) {
            throw ValidationException::withMessages([
                'gateway' => trans_choice(
                    ':count active payment attempt is still in progress. Wait until it completes or expires before changing the active gateway.|:count active payment attempts are still in progress. Wait until they complete or expire before changing the active gateway.',
                    $activePaymentAttemptCount,
                    ['count' => $activePaymentAttemptCount],
                ),
            ]);
        }

        if ($gatewayKey === null || $gatewayKey === '') {
            $this->settings->set(PaymentGatewayManager::ACTIVE_KEY, null, $request->user());
            $this->dispatchUpdated($request);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment gateway deactivated.')]);

            return back();
        }

        $gateway = $this->gateways->find($gatewayKey);

        if (! $gateway) {
            throw ValidationException::withMessages([
                'gateway' => __('The selected payment gateway is unavailable.'),
            ]);
        }

        $schema = $gateway->configurationSchema();
        if (! is_array($schema)) {
            throw ValidationException::withMessages([
                'gateway' => __('The selected payment gateway has an invalid configuration schema.'),
            ]);
        }

        $this->validateConfiguration($schema, $configuration, requireRequired: false);

        $existing = $this->gateways->configuration($gatewayKey);
        $merged = $this->mergeConfiguration($schema, $existing, $configuration);
        $this->validateConfiguration($schema, $merged);

        $configurations = $this->gateways->configurations();
        $configurations[$gatewayKey] = $merged;

        DB::transaction(function () use ($configurations, $gatewayKey, $request): void {
            $this->settings->set(PaymentGatewayManager::CONFIG_KEY, $configurations, $request->user());
            $this->settings->set(PaymentGatewayManager::ACTIVE_KEY, $gatewayKey, $request->user());
        });

        $this->dispatchUpdated($request, [
            PaymentGatewayManager::CONFIG_KEY,
            PaymentGatewayManager::ACTIVE_KEY,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment gateway settings updated.')]);

        return back();
    }

    /** @param array<string, mixed> $schema */
    private function validateConfiguration(array $schema, array $configuration, bool $requireRequired = true): void
    {
        foreach ($configuration as $key => $value) {
            if (! array_key_exists($key, $schema)) {
                throw ValidationException::withMessages([
                    "configuration.{$key}" => __('This setting is not supported by the selected gateway.'),
                ]);
            }
        }

        foreach ($schema as $key => $field) {
            if (! is_array($field)) {
                throw ValidationException::withMessages([
                    'gateway' => __('The selected payment gateway has an invalid configuration schema.'),
                ]);
            }

            $value = $configuration[$key] ?? null;

            if ($requireRequired && ($field['required'] ?? false) && blank($value)) {
                throw ValidationException::withMessages([
                    "configuration.{$key}" => __('This field is required.'),
                ]);
            }

            if (blank($value)) {
                continue;
            }

            if (! is_scalar($value)) {
                throw ValidationException::withMessages([
                    "configuration.{$key}" => __('This field must be a scalar value.'),
                ]);
            }

            if (($field['type'] ?? null) === 'number' && ! is_numeric($value)) {
                throw ValidationException::withMessages([
                    "configuration.{$key}" => __('This field must be a number.'),
                ]);
            }

            if (($field['type'] ?? null) === 'select' && isset($field['options'])) {
                $options = collect($field['options'])
                    ->filter(fn (mixed $option): bool => is_array($option) && array_key_exists('value', $option))
                    ->pluck('value')
                    ->all();

                if (! in_array($value, $options, true)) {
                    throw ValidationException::withMessages([
                        "configuration.{$key}" => __('This value is not supported.'),
                    ]);
                }
            }
        }
    }

    /** @param array<string, mixed> $schema @param array<string, mixed> $existing @param array<string, mixed> $submitted */
    private function mergeConfiguration(array $schema, array $existing, array $submitted): array
    {
        $merged = $existing;

        foreach ($schema as $key => $field) {
            if (! array_key_exists($key, $submitted)) {
                continue;
            }

            if ($this->isSecretField($field) && blank($submitted[$key]) && filled($existing[$key] ?? null)) {
                continue;
            }

            $merged[$key] = $submitted[$key];
        }

        return $merged;
    }

    private function isSecretField(mixed $field): bool
    {
        return is_array($field) && (($field['secret'] ?? false) === true
            || in_array(strtolower((string) ($field['type'] ?? '')), [
                'password',
                'secret',
                'token',
                'api_key',
                'encrypted',
            ], true));
    }

    private function activePaymentAttemptCount(): int
    {
        return PaymentAttempt::query()
            ->where('status', PaymentStatus::Pending->value)
            ->where(function ($query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();
    }

    /** @param array<int, string>|null $keys */
    private function dispatchUpdated(Request $request, ?array $keys = null): void
    {
        $keys ??= [PaymentGatewayManager::ACTIVE_KEY];

        event(new SettingsUpdated(
            group: 'billing',
            keys: $keys,
            actorId: $request->user()?->getKey(),
        ));
    }
}
