<?php

namespace App\Data\Mail;

use InvalidArgumentException;

final readonly class MailMessage
{
    /**
     * @param  list<MailAddress>  $to
     * @param  list<MailAddress>  $cc
     * @param  list<MailAddress>  $bcc
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public array $to,
        public string $subject,
        public string $htmlBody,
        public ?string $plainTextBody = null,
        public ?MailAddress $from = null,
        public ?MailAddress $replyTo = null,
        public array $cc = [],
        public array $bcc = [],
        public array $headers = [],
    ) {
        if ($to === []) {
            throw new InvalidArgumentException('A mail message requires at least one recipient.');
        }
    }
}
