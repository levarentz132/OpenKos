<?php

namespace App\Notifications\Drivers;

use Illuminate\Support\Facades\Http;
use OpenKOS\Core\Contracts\WhatsAppDriver;
use OpenKOS\Core\Data\WhatsApp\DriverHealthResult;
use OpenKOS\Core\Data\WhatsApp\WhatsAppMessage;

class WabaWhatsAppDriver implements WhatsAppDriver
{
    private const API_VERSION = 'v21.0';

    public function __construct(private array $config = []) {}

    public function configurationSchema(): array
    {
        return [
            'phone_number_id' => [
                'label' => 'Phone Number ID',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'e.g. 109283746501928',
            ],
            'access_token' => [
                'label' => 'Permanent Access Token',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'EAAG...',
            ],
            'template_name' => [
                'label' => 'Template Name (Optional)',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'e.g. otp_verification (leave blank for text message)',
            ],
            'template_language' => [
                'label' => 'Template Language',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'id or en (default: id)',
            ],
        ];
    }

    public function send(WhatsAppMessage $message): void
    {
        $phoneNumberId = $this->resolvePhoneNumberId();
        $accessToken = $this->resolveAccessToken();

        if (blank($phoneNumberId) || blank($accessToken)) {
            throw new \RuntimeException('WABA Phone Number ID or Access Token is not configured.');
        }

        $phone = $this->cleanPhoneNumber($message->phone);
        $templateName = $this->config['template_name']
            ?? config('services.whatsapp.drivers.waba.template_name')
            ?? env('WABA_TEMPLATE_NAME');

        $url = "https://graph.facebook.com/" . self::API_VERSION . "/{$phoneNumberId}/messages";

        if (filled($templateName)) {
            $lang = $this->config['template_language']
                ?? config('services.whatsapp.drivers.waba.template_language')
                ?? env('WABA_TEMPLATE_LANGUAGE', 'id');

            // Extract 6-digit OTP code if message contains it
            preg_match('/\b\d{6}\b/', $message->message, $matches);
            $otpCode = $matches[0] ?? $message->message;

            $components = [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $otpCode],
                    ],
                ],
            ];

            $buttonType = $this->config['button_type']
                ?? config('services.whatsapp.drivers.waba.button_type')
                ?? env('WABA_BUTTON_TYPE');

            if ($buttonType === 'copy_code') {
                $components[] = [
                    'type' => 'button',
                    'sub_type' => 'copy_code',
                    'index' => '0',
                    'parameters' => [
                        ['type' => 'coupon_code', 'coupon_code' => $otpCode],
                    ],
                ];
            } elseif ($buttonType === 'url') {
                $components[] = [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => '0',
                    'parameters' => [
                        ['type' => 'text', 'text' => $otpCode],
                    ],
                ];
            }

            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $phone,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $lang],
                    'components' => $components,
                ],
            ];
        } else {
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $phone,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message->message,
                ],
            ];
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post($url, $payload);

        if (! $response->successful()) {
            $error = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException("WABA delivery failed (HTTP {$response->status()}): {$error}");
        }
    }

    public function supportsAttachments(): bool
    {
        return true;
    }

    public function health(): DriverHealthResult
    {
        $phoneNumberId = $this->resolvePhoneNumberId();
        $accessToken = $this->resolveAccessToken();

        if (blank($phoneNumberId) || blank($accessToken)) {
            return new DriverHealthResult(false, 'WABA Phone Number ID or Access Token is missing.');
        }

        try {
            $url = "https://graph.facebook.com/" . self::API_VERSION . "/{$phoneNumberId}";
            $response = Http::withToken($accessToken)->acceptJson()->get($url);

            if (! $response->successful()) {
                $errorMsg = $response->json('error.message') ?? "HTTP {$response->status()}";
                return new DriverHealthResult(false, "WABA error: {$errorMsg}");
            }

            $data = $response->json();
            $displayName = $data['verified_name'] ?? $data['display_phone_number'] ?? 'WABA Connected';
            $phone = $data['display_phone_number'] ?? null;

            return new DriverHealthResult(
                healthy: true,
                message: "Connected to {$displayName}",
                phone: $phone,
            );
        } catch (\Throwable $e) {
            return new DriverHealthResult(false, "Connection error: {$e->getMessage()}");
        }
    }

    public function supportsPairing(): bool
    {
        return false;
    }

    public function getPairingQrCode(): ?string
    {
        return null;
    }

    public function pair(): void {}

    public function disconnect(): void {}

    protected function resolvePhoneNumberId(): ?string
    {
        return $this->config['phone_number_id']
            ?? config('services.whatsapp.drivers.waba.phone_number_id')
            ?? env('WABA_PHONE_NUMBER_ID');
    }

    protected function resolveAccessToken(): ?string
    {
        return $this->config['access_token']
            ?? config('services.whatsapp.drivers.waba.access_token')
            ?? env('WABA_ACCESS_TOKEN');
    }

    protected function cleanPhoneNumber(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($cleaned, '0')) {
            $cleaned = '62' . substr($cleaned, 1);
        }
        return $cleaned;
    }
}
