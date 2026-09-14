<?php

namespace App\Notifications\Drivers;

use Illuminate\Support\Facades\Http;
use OpenKOS\Core\Contracts\WhatsAppDriver;
use OpenKOS\Core\Data\WhatsApp\DriverHealthResult;
use OpenKOS\Core\Data\WhatsApp\WhatsAppMessage;

class FonnteWhatsAppDriver implements WhatsAppDriver
{
    public function __construct(private array $config = []) {}

    public function configurationSchema(): array
    {
        return [
            'token' => [
                'label' => 'Fonnte API Token',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Enter API token from fonnte.com',
            ],
        ];
    }

    public function send(WhatsAppMessage $message): void
    {
        $token = $this->resolveToken();

        if (blank($token)) {
            throw new \RuntimeException('Fonnte API token is not configured.');
        }

        $payload = [
            'target' => $message->phone,
            'message' => $message->message,
            'countryCode' => '62',
        ];

        $response = Http::withHeaders([
            'Authorization' => $token,
        ])->withOptions($this->resolveHttpOptions())->asForm()->post('https://api.fonnte.com/send', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException("Fonnte API request failed with HTTP {$response->status()}: {$response->body()}");
        }

        $data = $response->json();
        if (is_array($data) && isset($data['status']) && $data['status'] === false) {
            $reason = $data['reason'] ?? 'Unknown error from Fonnte';
            throw new \RuntimeException("Fonnte delivery failed: {$reason}");
        }
    }

    public function supportsAttachments(): bool
    {
        return true;
    }

    public function health(): DriverHealthResult
    {
        $token = $this->resolveToken();

        if (blank($token)) {
            return new DriverHealthResult(false, 'Fonnte API token is missing or not configured.');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->withOptions($this->resolveHttpOptions())->post('https://api.fonnte.com/device');

            if (! $response->successful()) {
                return new DriverHealthResult(false, "Fonnte returned HTTP {$response->status()}");
            }

            $data = $response->json();
            $status = (bool) ($data['status'] ?? false);
            $device = $data['device'] ?? null;
            $message = $data['reason'] ?? ($status ? 'Device connected' : 'Device disconnected');

            return new DriverHealthResult(
                healthy: $status,
                message: $message,
                phone: $device,
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

    protected function resolveToken(): ?string
    {
        return $this->config['token']
            ?? config('services.whatsapp.drivers.fonnte.token')
            ?? env('FONNTE_TOKEN');
    }

    protected function resolveHttpOptions(): array
    {
        $options = [];

        if (empty(ini_get('curl.cainfo')) && empty(ini_get('openssl.cafile'))) {
            $candidates = [
                'C:\xampp\phpMyAdmin\vendor\composer\ca-bundle\res\cacert.pem',
                'C:\xampp\perl\vendor\lib\Mozilla\CA\cacert.pem',
                base_path('cacert.pem'),
            ];
            foreach ($candidates as $candidate) {
                if (file_exists($candidate)) {
                    $options['verify'] = $candidate;
                    break;
                }
            }
        }

        return $options;
    }
}
