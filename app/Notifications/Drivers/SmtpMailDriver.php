<?php

namespace App\Notifications\Drivers;

use App\Contracts\MailDriver;
use App\Data\Mail\DriverHealthResult;
use App\Data\Mail\MailMessage;
use App\Data\Mail\MailSendResult;
use App\Exceptions\InvalidMailConfigurationException;
use Illuminate\Support\Facades\Mail;

class SmtpMailDriver implements MailDriver
{
    public function __construct(private array $config = []) {}

    public function configurationSchema(): array
    {
        return [
            'host' => ['label' => 'SMTP Host', 'type' => 'text', 'required' => true, 'placeholder' => 'smtp.example.com'],
            'port' => ['label' => 'Port', 'type' => 'number', 'required' => true, 'placeholder' => '587'],
            'username' => ['label' => 'Username', 'type' => 'text', 'required' => false, 'placeholder' => 'user@example.com'],
            'password' => ['label' => 'Password', 'type' => 'password', 'required' => false, 'placeholder' => 'Enter SMTP password'],
            'encryption' => ['label' => 'Encryption', 'type' => 'select', 'options' => [
                ['value' => 'null', 'label' => 'None'],
                ['value' => 'tls', 'label' => 'TLS'],
                ['value' => 'ssl', 'label' => 'SSL'],
            ]],
        ];
    }

    public function send(MailMessage $message): MailSendResult
    {
        $this->validateConfig();

        $mailerName = 'openkos-smtp';

        config([
            "mail.mailers.{$mailerName}" => [
                'transport' => 'smtp',
                'host' => $this->config['host'],
                'port' => (int) $this->config['port'],
                'username' => $this->config['username'] ?? '',
                'password' => $this->config['password'] ?? '',
                'encryption' => $this->config['encryption'] ?? null,
            ],
        ]);

        Mail::purge($mailerName);

        $fromAddress = $message->from?->address ?? data_get($this->config, 'from.address');
        $fromName = $message->from?->name ?? data_get($this->config, 'from.name');

        Mail::mailer($mailerName)->html($message->htmlBody, function ($mail) use ($message, $fromAddress, $fromName): void {
            foreach ($message->to as $recipient) {
                $mail->to($recipient->address, $recipient->name ?? '');
            }

            $mail->subject($message->subject);

            if ($fromAddress) {
                $mail->from($fromAddress, $fromName ?? '');
            }

            if ($message->replyTo) {
                $mail->replyTo($message->replyTo->address, $message->replyTo->name ?? '');
            }

            foreach ($message->cc as $recipient) {
                $mail->cc($recipient->address, $recipient->name ?? '');
            }

            foreach ($message->bcc as $recipient) {
                $mail->bcc($recipient->address, $recipient->name ?? '');
            }

            if ($message->plainTextBody !== null) {
                $mail->text($message->plainTextBody);
            }

            foreach ($message->headers as $name => $value) {
                $mail->getHeaders()->addTextHeader($name, $value);
            }

            foreach ($message->attachments as $attachment) {
                $mail->attachData($attachment->content, $attachment->filename, [
                    'mime' => $attachment->mimeType,
                ]);
            }
        });

        return new MailSendResult(null, 'Sent via SMTP.');
    }

    public function health(): DriverHealthResult
    {
        try {
            $this->validateConfig();
        } catch (InvalidMailConfigurationException $e) {
            return new DriverHealthResult(false, $e->getMessage());
        }

        return new DriverHealthResult(true, 'SMTP configuration is complete.');
    }

    private function validateConfig(): void
    {
        $host = $this->config['host'] ?? null;
        $port = $this->config['port'] ?? null;
        $encryption = $this->config['encryption'] ?? null;

        if (! is_string($host) || trim($host) === '') {
            throw InvalidMailConfigurationException::missing('host');
        }

        if (filter_var($port, FILTER_VALIDATE_INT) === false || (int) $port < 1 || (int) $port > 65535) {
            throw InvalidMailConfigurationException::invalid('port', 'Port must be an integer between 1 and 65535.');
        }

        if (! in_array($encryption, [null, 'tls', 'ssl'], true)) {
            throw InvalidMailConfigurationException::invalid('encryption', 'Encryption must be tls, ssl, or null.');
        }
    }
}
