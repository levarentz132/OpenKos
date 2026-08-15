<?php

namespace App\Services\Payments;

use Illuminate\Contracts\Container\Container;
use OpenKOS\Core\Contracts\PaymentGateway;
use OpenKOS\Platform\Payment\PaymentRegistry;
use OpenKOS\Platform\Settings\SettingsManager;

class PaymentGatewayManager
{
    public const ACTIVE_KEY = 'payment_gateway';

    public const CONFIG_KEY = 'payment_gateway_config';

    public function __construct(
        private PaymentRegistry $registry,
        private SettingsManager $settings,
        private Container $container,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $gateways = [];

        foreach (array_keys($this->registry->gateways()) as $key) {
            $configuration = $this->configuration($key);
            $gateway = $this->find($key);

            try {
                if (! $gateway) {
                    throw new \RuntimeException('Gateway could not be resolved.');
                }

                $schema = $this->schema($gateway);
                $missing = $this->missingRequired($schema, $configuration);

                $gateways[] = [
                    'key' => $key,
                    'label' => $gateway->displayName(),
                    'configuration_schema' => $this->publicSchema($schema),
                    'configuration' => $this->publicConfiguration($schema, $configuration),
                    'secret_fields' => $this->configuredSecretFields($schema, $configuration),
                    'status' => $missing === [] ? 'configured' : 'incomplete',
                    'missing_fields' => $missing,
                    'error' => null,
                ];
            } catch (\Throwable) {
                $gateways[] = [
                    'key' => $key,
                    'label' => $key,
                    'configuration_schema' => [],
                    'configuration' => [],
                    'secret_fields' => [],
                    'status' => 'unavailable',
                    'missing_fields' => [],
                    'error' => 'This payment gateway is unavailable.',
                ];
            }
        }

        return $gateways;
    }

    public function find(string $key): ?PaymentGateway
    {
        $registered = $this->registry->gateways()[$key] ?? null;

        if ($registered instanceof PaymentGateway) {
            return $registered;
        }

        if (! is_string($registered)) {
            return null;
        }

        try {
            $gateway = $this->container->make($registered, [
                'config' => $this->configuration($key),
            ]);
        } catch (\Throwable) {
            return null;
        }

        return $gateway instanceof PaymentGateway ? $gateway : null;
    }

    public function active(): ?PaymentGateway
    {
        $key = $this->activeKey();

        if ($key === null) {
            return null;
        }

        $gateway = $this->find($key);

        if (! $gateway) {
            return null;
        }

        try {
            return $this->missingRequired(
                $this->schema($gateway),
                $this->configuration($key),
            ) === [] ? $gateway : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function activeKey(): ?string
    {
        try {
            $key = $this->settings->get(self::ACTIVE_KEY);
        } catch (\Throwable) {
            return null;
        }

        return is_string($key) && $key !== '' ? $key : null;
    }

    /** @return array<string, mixed> */
    public function configuration(string $key): array
    {
        try {
            $configurations = $this->settings->get(self::CONFIG_KEY);
        } catch (\Throwable) {
            return [];
        }

        $configuration = is_array($configurations) ? ($configurations[$key] ?? []) : [];

        return is_array($configuration) ? $configuration : [];
    }

    /** @return array<string, array<string, mixed>> */
    public function configurations(): array
    {
        try {
            $configurations = $this->settings->get(self::CONFIG_KEY);
        } catch (\Throwable) {
            return [];
        }

        return is_array($configurations) ? $configurations : [];
    }

    /** @return array<string, array<string, mixed>> */
    private function schema(PaymentGateway $gateway): array
    {
        $schema = $gateway->configurationSchema();

        if (! is_array($schema)) {
            throw new \RuntimeException('Gateway configuration schema must be an array.');
        }

        foreach ($schema as $field => $definition) {
            if (! is_string($field) || ! is_array($definition)) {
                throw new \RuntimeException('Gateway configuration schema is invalid.');
            }
        }

        return $schema;
    }

    /** @return array<string, array<string, mixed>> */
    private function publicSchema(array $schema): array
    {
        $public = [];

        foreach ($schema as $key => $field) {
            $public[$key] = array_filter([
                'label' => $field['label'] ?? $key,
                'type' => $field['type'] ?? 'text',
                'required' => (bool) ($field['required'] ?? false),
                'placeholder' => $field['placeholder'] ?? null,
                'options' => $field['options'] ?? null,
                'secret' => $this->isSecretField($field),
            ], static fn (mixed $value): bool => $value !== null);
        }

        return $public;
    }

    /** @return array<string, mixed> */
    private function publicConfiguration(array $schema, array $configuration): array
    {
        $public = [];

        foreach ($schema as $key => $field) {
            if (! $this->isSecretField($field) && array_key_exists($key, $configuration)) {
                $public[$key] = $configuration[$key];
            }
        }

        return $public;
    }

    /** @return array<int, string> */
    private function configuredSecretFields(array $schema, array $configuration): array
    {
        $fields = [];

        foreach ($schema as $key => $field) {
            if ($this->isSecretField($field) && filled($configuration[$key] ?? null)) {
                $fields[] = $key;
            }
        }

        return $fields;
    }

    /** @return array<int, string> */
    private function missingRequired(array $schema, array $configuration): array
    {
        $missing = [];

        foreach ($schema as $key => $field) {
            if (($field['required'] ?? false) && blank($configuration[$key] ?? null)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    private function isSecretField(array $field): bool
    {
        return ($field['secret'] ?? false) === true
            || in_array(strtolower((string) ($field['type'] ?? '')), [
                'password',
                'secret',
                'token',
                'api_key',
                'encrypted',
            ], true);
    }
}
