<?php

namespace App\Events\Mail;

use App\Data\Mail\MailAddress;
use App\Data\Mail\MailMessage;
use App\Exceptions\MailDeliveryException;

final readonly class MailFailed
{
    /** @var list<string> */
    public array $recipients;

    public function __construct(
        public string $driver,
        public string $subject,
        MailMessage $message,
        public MailDeliveryException $exception,
    ) {
        $this->recipients = array_map(fn (MailAddress $addr) => $addr->address, $message->to);
    }
}
