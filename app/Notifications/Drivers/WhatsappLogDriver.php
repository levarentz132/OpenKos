<?php

namespace App\Notifications\Drivers;

use Illuminate\Support\Facades\Log;
use OpenKOS\Core\Contracts\WhatsAppDriver;
use OpenKOS\Core\Data\WhatsApp\DriverHealthResult;
use OpenKOS\Core\Data\WhatsApp\WhatsAppMessage;

class WhatsappLogDriver implements WhatsAppDriver
{
    public function __construct(private array $config = []) {}

    public function send(WhatsAppMessage $message): void
    {
        Log::channel('reminders')->info('[WhatsApp] To: '.$message->phone.' — '.$message->message, [
            'attachment' => $message->attachment ? [
                'filename' => $message->attachment->filename,
                'mime_type' => $message->attachment->mimeType,
                'bytes' => strlen($message->attachment->content),
            ] : null,
        ]);
    }

    public function supportsAttachments(): bool
    {
        return true;
    }

    public function health(): DriverHealthResult
    {
        return new DriverHealthResult(true);
    }

    public function supportsPairing(): bool
    {
        return false;
    }

    public function configurationSchema(): array
    {
        return [];
    }

    public function getPairingQrCode(): ?string
    {
        return null;
    }

    public function pair(): void {}

    public function disconnect(): void {}
}
