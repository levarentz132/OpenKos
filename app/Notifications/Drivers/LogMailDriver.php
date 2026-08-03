<?php

namespace App\Notifications\Drivers;

use App\Contracts\MailDriver;
use App\Data\Mail\DriverHealthResult;
use App\Data\Mail\MailMessage;
use App\Data\Mail\MailSendResult;
use Illuminate\Support\Facades\Log;

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
