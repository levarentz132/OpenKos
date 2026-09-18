<?php

namespace App\Services\Payments\Gateways;

use App\Exceptions\PaymentGatewayCreationException;
use App\Models\Invoice;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenKOS\Core\Contracts\PaymentGateway;
use OpenKOS\Core\Contracts\PaymentGatewayStatusLookup;
use OpenKOS\Core\Data\Payment\CheckoutInstructions;
use OpenKOS\Core\Data\Payment\Money;
use OpenKOS\Core\Data\Payment\PaymentCreationResult;
use OpenKOS\Core\Data\Payment\PaymentProviderResult;
use OpenKOS\Core\Data\Payment\PaymentRequest;
use OpenKOS\Core\Data\Payment\PaymentStatusLookupRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookResult;
use OpenKOS\Core\Enums\PaymentStatus;
use OpenKOS\Core\Exceptions\PaymentWebhookPayloadException;
use OpenKOS\Core\Exceptions\PaymentWebhookVerificationException;
use Throwable;

class DokuPaymentGateway implements PaymentGateway, PaymentGatewayStatusLookup
{
    public function __construct(public array $config = []) {}

    public function key(): string
    {
        return 'doku';
    }

    public function displayName(): string
    {
        return 'DOKU Checkout (Jokul)';
    }

    public function clientId(): string
    {
        return (string) ($this->config['client_id'] ?? config('services.doku.client_id', env('DOKU_CLIENT_ID', '')));
    }

    public function secretKey(): string
    {
        return (string) ($this->config['secret_key'] ?? config('services.doku.secret_key', env('DOKU_SECRET_KEY', '')));
    }

    public function baseUrl(): string
    {
        $env = $this->config['environment'] ?? config('services.doku.environment', env('DOKU_ENVIRONMENT', 'sandbox'));

        return strtolower((string) $env) === 'production'
            ? 'https://api.doku.com'
            : 'https://api-sandbox.doku.com';
    }

    /**
     * Create a DOKU Jokul Checkout payment session.
     */
    public function createPayment(PaymentRequest $request): PaymentCreationResult
    {
        $clientId = $this->clientId();
        $secretKey = $this->secretKey();

        if (empty($clientId) || empty($secretKey)) {
            throw new PaymentGatewayCreationException('DOKU credentials (Client ID or Secret Key) are not configured.');
        }

        $baseUrl = $this->baseUrl();
        $target = '/checkout/v1/payment';
        $requestId = 'REQ-' . uniqid() . '-' . time();
        $timestamp = $this->getSynchronizedTimestamp();

        $customer = $this->resolveCustomer($request);

        $callbackUrl = $request->metadata['callback_url']
            ?? $this->config['callback_url']
            ?? config('services.doku.callback_url')
            ?? env('FRONTEND_URL')
            ?? (config('app.url') ? rtrim((string) config('app.url'), '/') . '/portal/billing' : 'http://localhost:5173');

        $payload = [
            'order' => array_filter([
                'amount' => $request->amount->minorUnits,
                'invoice_number' => $request->reference,
                'currency' => $request->amount->currency,
                'callback_url' => $callbackUrl,
            ]),
            'payment' => [
                'payment_due_date' => 60,
            ],
        ];

        if (! empty($customer)) {
            $payload['customer'] = $customer;
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $digest = base64_encode(hash('sha256', $body, true));

        $component = "Client-Id:{$clientId}\n" .
                     "Request-Id:{$requestId}\n" .
                     "Request-Timestamp:{$timestamp}\n" .
                     "Request-Target:{$target}\n" .
                     "Digest:{$digest}";

        $signature = 'HMACSHA256=' . base64_encode(hash_hmac('sha256', $component, $secretKey, true));

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Client-Id' => $clientId,
                'Request-Id' => $requestId,
                'Request-Timestamp' => $timestamp,
                'Signature' => $signature,
            ])->withBody($body, 'application/json')
              ->timeout(15)
              ->post($baseUrl . $target);
        } catch (Throwable $e) {
            Log::error('DOKU API connection error: ' . $e->getMessage());
            throw new PaymentGatewayCreationException(
                'Could not connect to DOKU Checkout API: ' . $e->getMessage(),
                previous: $e,
            );
        }

        if (! $response->successful()) {
            $errorMsg = $response->json('message.0')
                ?? $response->json('error.message')
                ?? $response->body()
                ?? 'HTTP ' . $response->status();

            Log::error('DOKU Checkout API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new PaymentGatewayCreationException('DOKU Checkout returned error: ' . $errorMsg);
        }

        $data = $response->json();
        $checkoutUrl = $data['response']['payment']['url'] ?? $data['payment']['url'] ?? null;

        if (empty($checkoutUrl)) {
            throw new PaymentGatewayCreationException('DOKU Checkout did not return a valid payment URL.');
        }

        $tokenId = $data['response']['payment']['token_id'] ?? $data['payment']['token_id'] ?? null;
        $expiredDateStr = $data['response']['payment']['expired_date'] ?? $data['payment']['expired_date'] ?? null;
        $expiresAt = !empty($expiredDateStr)
            ? new DateTimeImmutable($expiredDateStr)
            : (new DateTimeImmutable())->modify('+60 minutes');

        return new PaymentCreationResult(
            providerReference: $request->reference,
            status: PaymentStatus::Pending,
            amount: $request->amount,
            instructions: new CheckoutInstructions(url: $checkoutUrl),
            expiresAt: $expiresAt,
            metadata: array_filter([
                'token_id' => $tokenId,
                'checkout_url' => $checkoutUrl,
                'request_id' => $requestId,
            ]),
        );
    }

    /**
     * Verify and process incoming DOKU notification callback (webhook).
     */
    public function handleCallback(PaymentWebhookRequest $request): PaymentWebhookResult
    {
        $headers = $request->headers;
        $clientId = $this->header($headers, 'client-id');
        $requestId = $this->header($headers, 'request-id');
        $timestamp = $this->header($headers, 'request-timestamp');
        $signature = $this->header($headers, 'signature');

        if (! $signature || ! $requestId || ! $timestamp) {
            throw new PaymentWebhookVerificationException('Missing required DOKU webhook headers.');
        }

        $secretKey = $this->secretKey();
        $digest = base64_encode(hash('sha256', $request->rawBody, true));

        // Candidate targets for path signature verification
        $targetCandidates = array_unique(array_filter([
            $this->header($headers, 'request-target'),
            '/api/webhooks/payment/doku',
            '/api/webhooks/payment/' . $this->key(),
            request()?->getRequestUri(),
            request()?->getPathInfo(),
        ]));

        $verified = false;
        foreach ($targetCandidates as $target) {
            $component = "Client-Id:" . ($clientId ?: $this->clientId()) . "\n" .
                         "Request-Id:" . $requestId . "\n" .
                         "Request-Timestamp:" . $timestamp . "\n" .
                         "Request-Target:" . $target . "\n" .
                         "Digest:" . $digest;

            $expectedSignature = 'HMACSHA256=' . base64_encode(hash_hmac('sha256', $component, $secretKey, true));

            if (hash_equals($expectedSignature, $signature)) {
                $verified = true;
                break;
            }
        }

        if (! $verified) {
            throw new PaymentWebhookVerificationException('DOKU webhook signature verification failed.');
        }

        $payload = json_decode($request->rawBody, true);
        if (! is_array($payload) || empty($payload['order']['invoice_number'])) {
            throw new PaymentWebhookPayloadException('DOKU webhook payload does not contain required order data.');
        }

        $invoiceNumber = (string) $payload['order']['invoice_number'];
        $rawTrxStatus = strtoupper($payload['transaction']['status'] ?? '');

        $status = match ($rawTrxStatus) {
            'SUCCESS', 'SETTLED', 'PAID' => PaymentStatus::Settled,
            'FAILED', 'REJECTED' => PaymentStatus::Failed,
            'EXPIRED' => PaymentStatus::Expired,
            'CANCELLED', 'CANCELED' => PaymentStatus::Canceled,
            default => PaymentStatus::Pending,
        };

        $amountVal = $payload['order']['amount'] ?? null;
        $amount = $amountVal !== null ? new Money((int) $amountVal, 'IDR') : null;

        $occurredAt = null;
        if (! empty($payload['transaction']['date'])) {
            try {
                $occurredAt = new DateTimeImmutable($payload['transaction']['date']);
            } catch (Throwable) {
                $occurredAt = null;
            }
        }

        $eventReference = $payload['transaction']['original_request_id']
            ?? ($requestId ? 'DOKU-' . $requestId : 'DOKU-' . $invoiceNumber . '-' . time());

        return new PaymentWebhookResult(
            eventReference: $eventReference,
            providerReference: $invoiceNumber,
            status: $status,
            reference: $invoiceNumber,
            amount: $amount,
            occurredAt: $occurredAt,
            metadata: array_filter([
                'channel_id' => $payload['channel']['id'] ?? null,
                'acquirer_id' => $payload['acquirer']['id'] ?? null,
                'service_id' => $payload['service']['id'] ?? null,
            ]),
        );
    }

    /**
     * Query DOKU API to lookup status of a transaction.
     */
    public function lookupPaymentStatus(PaymentStatusLookupRequest $request): PaymentProviderResult
    {
        $clientId = $this->clientId();
        $secretKey = $this->secretKey();

        $baseUrl = $this->baseUrl();
        $invoiceNumber = $request->providerReference ?: $request->reference;
        $target = '/orders/v1/status/' . $invoiceNumber;
        $requestId = 'REQ-' . uniqid() . '-' . time();
        $timestamp = $this->getSynchronizedTimestamp();

        $component = "Client-Id:{$clientId}\n" .
                     "Request-Id:{$requestId}\n" .
                     "Request-Timestamp:{$timestamp}\n" .
                     "Request-Target:{$target}";

        $signature = 'HMACSHA256=' . base64_encode(hash_hmac('sha256', $component, $secretKey, true));

        try {
            $response = Http::withHeaders([
                'Client-Id' => $clientId,
                'Request-Id' => $requestId,
                'Request-Timestamp' => $timestamp,
                'Signature' => $signature,
            ])->timeout(15)->get($baseUrl . $target);
        } catch (Throwable $e) {
            throw new \RuntimeException('Failed to query DOKU status: ' . $e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new \RuntimeException('DOKU status check failed with HTTP ' . $response->status());
        }

        $data = $response->json();
        $rawStatus = strtoupper($data['transaction']['status'] ?? '');
        $status = match ($rawStatus) {
            'SUCCESS', 'SETTLED', 'PAID' => PaymentStatus::Settled,
            'FAILED', 'REJECTED' => PaymentStatus::Failed,
            'EXPIRED' => PaymentStatus::Expired,
            'CANCELLED', 'CANCELED' => PaymentStatus::Canceled,
            default => PaymentStatus::Pending,
        };

        $amountVal = $data['order']['amount'] ?? null;
        $amount = $amountVal !== null ? new Money((int) $amountVal, 'IDR') : null;

        $occurredAt = null;
        if (! empty($data['transaction']['date'])) {
            try {
                $occurredAt = new DateTimeImmutable($data['transaction']['date']);
            } catch (Throwable) {
                $occurredAt = null;
            }
        }

        return new PaymentProviderResult(
            providerReference: (string) $invoiceNumber,
            status: $status,
            reference: (string) $invoiceNumber,
            amount: $amount,
            occurredAt: $occurredAt,
            metadata: array_filter([
                'channel_id' => $data['channel']['id'] ?? null,
            ]),
        );
    }

    /**
     * UI Settings Configuration Schema.
     */
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
            'client_id' => [
                'label' => 'Client ID',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'BRN-0208-1788852244810',
                'description' => 'Your DOKU Merchant / Mall ID or Client ID.',
            ],
            'secret_key' => [
                'label' => 'Secret Key',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'SK-xxxxxxxxxxxxxxxxxxxx',
                'description' => 'Your DOKU Secret Key used for HMAC-SHA256 signature generation.',
            ],
            'api_key' => [
                'label' => 'API Key',
                'type' => 'password',
                'required' => false,
                'placeholder' => 'doku_key_...',
                'description' => 'Optional API key for direct Jokul API integrations.',
            ],
            'callback_url' => [
                'label' => 'Customer Redirect URL',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'http://localhost:5173',
                'description' => 'URL website frontend tempat pelanggan akan diarahkan kembali setelah menyelesaikan pembayaran di DOKU Checkout (misal: http://localhost:5173 atau https://highlanderstay.com).',
            ],
            'webhook_setup' => [
                'label' => 'DOKU Notification Webhook URL',
                'type' => 'info',
                'instructions' => [
                    'Log into Jokul Back Office / DOKU Dashboard.',
                    'Go to Settings > Notification URL (or Payment Configuration).',
                    'Set the Notification URL to the webhook endpoint shown below.',
                ],
                'url' => '/api/webhooks/payment/doku',
            ],
        ];
    }

    private function resolveCustomer(PaymentRequest $request): ?array
    {
        if (empty($request->metadata['invoice_id'])) {
            return null;
        }

        try {
            $invoice = Invoice::with(['lease.tenant.user'])->find($request->metadata['invoice_id']);
            if (! $invoice?->lease?->tenant) {
                return null;
            }

            $tenant = $invoice->lease->tenant;
            $user = $tenant->user;

            return array_filter([
                'id' => 'CUST-' . $tenant->id,
                'name' => $tenant->name ?? $user?->name ?? 'Tenant',
                'email' => $tenant->email ?? $user?->email,
                'phone' => $tenant->phone ?? $user?->phone,
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    private function header(array $headers, string $key): ?string
    {
        $normalized = strtolower($key);
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === $normalized) {
                return is_array($value) ? ($value[0] ?? null) : (string) $value;
            }
        }

        return null;
    }

    /**
     * Get synchronized UTC ISO-8601 timestamp to guarantee compatibility with DOKU's 3600s window
     * even if the server system clock is drifted.
     */
    protected function getSynchronizedTimestamp(): string
    {
        $offset = Cache::remember('doku_time_offset_seconds', 900, function () {
            $endpoints = [
                'http://www.google.com',
                'https://api-sandbox.doku.com',
                'https://www.cloudflare.com',
            ];

            foreach ($endpoints as $url) {
                try {
                    $response = Http::withOptions(['verify' => false])->timeout(3)->head($url);
                    $dateHeader = $response->header('Date');
                    if ($dateHeader) {
                        $remoteTime = strtotime($dateHeader);
                        if ($remoteTime && $remoteTime > 1600000000) {
                            return $remoteTime - time();
                        }
                    }
                } catch (Throwable) {
                    continue;
                }
            }

            return 0;
        });

        return gmdate('Y-m-d\TH:i:s\Z', time() + (int) $offset);
    }
}
