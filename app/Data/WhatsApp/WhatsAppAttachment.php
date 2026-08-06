<?php

namespace App\Data\WhatsApp;

final readonly class WhatsAppAttachment
{
    public function __construct(
        public string $content,
        public string $filename,
        public string $mimeType,
    ) {}
}
