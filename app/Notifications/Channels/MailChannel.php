<?php

namespace App\Notifications\Channels;

use App\Services\MailManager;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use OpenKOS\Core\Contracts\MailChannelNotification;
use OpenKOS\Core\Data\Mail\MailAddress;
use OpenKOS\Core\Data\Mail\MailMessage;

class MailChannel
{
    public function __construct(private MailManager $mailManager) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof MailChannelNotification) {
            throw new InvalidArgumentException(sprintf(
                'Notification [%s] must implement OpenKOS\\Core\\Contracts\\MailChannelNotification.',
                $notification::class,
            ));
        }

        $route = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('mail', $notification)
            : (method_exists($notifiable, 'routeNotificationForMail')
                ? $notifiable->routeNotificationForMail($notification)
                : ($notifiable->email ?? null));

        if (! $route) {
            Log::warning('Mail notification skipped: no recipient route found.', [
                'notifiable_type' => $notifiable::class,
                'notifiable_id' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
                'notification' => $notification::class,
            ]);

            return;
        }

        $toRecipients = $this->normalizeRecipients($route);
        $content = $notification->toMailChannel($notifiable);

        $message = new MailMessage(
            to: $toRecipients,
            subject: $content->subject,
            htmlBody: $content->htmlBody,
            plainTextBody: $content->plainTextBody,
            from: $content->from,
            replyTo: $content->replyTo,
            cc: $content->cc,
            bcc: $content->bcc,
            headers: $content->headers,
            attachments: $content->attachments,
        );

        $this->mailManager->send($message);
    }

    /**
     * @param  string|array<string, string|int>  $route
     * @return list<MailAddress>
     */
    private function normalizeRecipients(string|array $route): array
    {
        if (is_string($route)) {
            return [new MailAddress($route)];
        }

        $addresses = [];
        foreach ($route as $key => $val) {
            if (is_int($key)) {
                $addresses[] = new MailAddress((string) $val);
            } else {
                $addresses[] = new MailAddress((string) $key, (string) $val);
            }
        }

        return $addresses;
    }
}
