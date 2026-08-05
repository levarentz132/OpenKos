<?php

namespace App\Data\Mail;

final readonly class MailAttachment
{
    public function __construct(
        public string $content,
        public string $filename,
        public string $mimeType,
    ) {}
}
