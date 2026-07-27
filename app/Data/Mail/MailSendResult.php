<?php

namespace App\Data\Mail;

final readonly class MailSendResult
{
    public function __construct(
        public ?string $externalId = null,
        public ?string $message = null,
    ) {}
}
