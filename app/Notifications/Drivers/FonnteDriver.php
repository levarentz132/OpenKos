<?php

namespace App\Notifications\Drivers;

use Illuminate\Support\Facades\Http;
use OpenKOS\Core\Contracts\WhatsAppDriver;
use OpenKOS\Core\Data\WhatsApp\DriverHealthResult;
use OpenKOS\Core\Data\WhatsApp\WhatsAppMessage;

class FonnteDriver implements WhatsAppDriver
{
    public function __construct(private array $config = []) {}

    public function send(WhatsAppMessage $message): void
    {
        $token = $this->config['token'] ?? null;

        if (! $token) {
            throw new \RuntimeException('Fonnte token is not configured.');
        }

        $request = Http::withToken($token, '');

        if ($message->attachment) {
            $request = $request->attach(
                'file',
                $message->attachment->content,
                $message->attachment->filename,
                ['Content-Type' => $message->attachment->mimeType],
            );
        }

        $response = $request->asMultipart()->post('https://api.fonnte.com/send', array_filter([
            'target' => $this->normalizePhone($message->phone),
            'message' => $message->message,
            'filename' => $message->attachment?->filename,
        ], fn ($value): bool => $value !== null));

        $body = $response->json();

        if (! ($body['status'] ?? false)) {
            throw new \RuntimeException($body['reason'] ?? 'Fonnte send failed');
        }
    }

    public function supportsAttachments(): bool
    {
        return true;
    }

    public function health(): DriverHealthResult
    {
        $token = $this->config['token'] ?? null;

        if (! $token) {
            return new DriverHealthResult(false, 'Fonnte token is not configured.');
        }

        $response = Http::withToken($token, '')->post('https://api.fonnte.com/device');

        $body = $response->json();

        if (! ($body['status'] ?? false)) {
            return new DriverHealthResult(false, $body['reason'] ?? 'Unknown error');
        }

        if (($body['device_status'] ?? null) !== 'connect') {
            return new DriverHealthResult(false, 'Device is not connected.');
        }

        return new DriverHealthResult(true);
    }

    public function supportsPairing(): bool
    {
        return false;
    }

    public function configurationSchema(): array
    {
        return [
            'token' => ['label' => 'API Token', 'type' => 'password', 'required' => true],
        ];
    }

    public function getPairingQrCode(): ?string
    {
        return null;
    }

    public function pair(): void
    {
        throw new \RuntimeException('Pairing not supported.');
    }

    public function disconnect(): void
    {
        throw new \RuntimeException('Pairing not supported.');
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        return '+'.$phone;
    }
}
