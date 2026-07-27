<?php

use App\Data\Mail\MailAddress;
use App\Data\Mail\MailMessage;
use App\Notifications\Drivers\SmtpMailDriver;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;

test('SmtpMailDriver composes both HTML and plain text alternative MIME parts', function () {
    $driver = new SmtpMailDriver([
        'host' => '127.0.0.1',
        'port' => 1025,
    ]);

    $message = new MailMessage(
        to: [new MailAddress('tenant@example.com', 'Tenant Name')],
        subject: 'Rent Payment Reminder',
        htmlBody: '<h1>Rent Due</h1><p>Please pay your rent.</p>',
        plainTextBody: "Rent Due\n\nPlease pay your rent.",
    );

    $capturedSymfonyEmail = null;

    Mail::shouldReceive('purge')->once()->with('openkos-smtp');
    Mail::shouldReceive('mailer')->once()->with('openkos-smtp')->andReturnSelf();
    Mail::shouldReceive('html')->once()->andReturnUsing(function ($html, $callback) use (&$capturedSymfonyEmail) {
        $symfonyEmail = new Email;
        $illuminateMessage = new Message($symfonyEmail);
        $illuminateMessage->html($html);

        $callback($illuminateMessage);

        $capturedSymfonyEmail = $symfonyEmail;
    });

    $driver->send($message);

    $this->assertNotNull($capturedSymfonyEmail);
    $this->assertStringContainsString('<h1>Rent Due</h1>', (string) $capturedSymfonyEmail->getHtmlBody());
    $this->assertStringContainsString("Rent Due\n\nPlease pay your rent.", (string) $capturedSymfonyEmail->getTextBody());
});
