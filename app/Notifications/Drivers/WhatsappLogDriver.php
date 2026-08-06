<?php

namespace App\Notifications\Drivers;

use App\Contracts\WhatsAppDriver;
use App\Data\WhatsApp\DriverHealthResult;
use App\Data\WhatsApp\WhatsAppMessage;
use Illuminate\Support\Facades\Log;

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
