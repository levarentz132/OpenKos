<?php

namespace App\Notifications\Drivers;

use Illuminate\Support\Facades\Log;
use OpenKOS\Core\Contracts\MailDriver;
use OpenKOS\Core\Data\Mail\DriverHealthResult;
use OpenKOS\Core\Data\Mail\MailMessage;
use OpenKOS\Core\Data\Mail\MailSendResult;

class LogMailDriver implements MailDriver
{
    public function __construct(private array $config = []) {}

    public function configurationSchema(): array
    {
        return [];
    }

    public function send(MailMessage $message): MailSendResult
    {
        $recipients = array_map(fn ($addr) => $addr->address, $message->to);

        $context = [
            'to' => $recipients,
            'subject' => $message->subject,
            'has_html' => $message->htmlBody !== '',
            'has_plain_text' => $message->plainTextBody !== null,
            'attachment_count' => count($message->attachments),
        ];

        if ($this->config['log_body'] ?? false) {
            $context['plain_text'] = $message->plainTextBody;
        }

        Log::channel('mail')->info('Mail message dispatched to log driver.', $context);

        return new MailSendResult(null, 'Logged to mail channel.');
    }

    public function health(): DriverHealthResult
    {
        return new DriverHealthResult(true, 'Log driver is active.');
    }
}
