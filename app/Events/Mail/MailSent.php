<?php

namespace App\Events\Mail;

use App\Data\Mail\MailAddress;
use App\Data\Mail\MailMessage;

final readonly class MailSent
{
    /** @var list<string> */
    public array $recipients;

    public function __construct(
        public string $driver,
        public string $subject,
        MailMessage $message,
        public ?string $externalId = null,
    ) {
        $this->recipients = array_map(fn (MailAddress $addr) => $addr->address, $message->to);
    }
}
