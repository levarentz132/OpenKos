<?php

namespace App\Data\Mail;

final readonly class MailContent
{
    /**
     * @param  list<MailAddress>  $cc
     * @param  list<MailAddress>  $bcc
     * @param  array<string, string>  $headers
     * @param  list<MailAttachment>  $attachments
     */
    public function __construct(
        public string $subject,
        public string $htmlBody,
        public ?string $plainTextBody = null,
        public ?MailAddress $from = null,
        public ?MailAddress $replyTo = null,
        public array $cc = [],
        public array $bcc = [],
        public array $headers = [],
        public array $attachments = [],
    ) {}
}
