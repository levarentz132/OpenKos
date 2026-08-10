<?php

namespace App\Events\Mail;

use App\Exceptions\MailDeliveryException;
use OpenKOS\Core\Data\Mail\MailAddress;
use OpenKOS\Core\Data\Mail\MailMessage;

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
