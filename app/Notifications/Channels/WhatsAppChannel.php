<?php

namespace App\Notifications\Channels;

use App\Services\WhatsAppManager;
use Illuminate\Notifications\Notification;
use OpenKOS\Core\Contracts\WhatsAppChannelNotification;

class WhatsAppChannel
{
    public function __construct(private WhatsAppManager $whatsapp) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->routeNotificationForWhatsApp($notification);

        if (! $phone) {
            return;
        }

        if ($notification instanceof WhatsAppChannelNotification) {
            $content = $notification->toWhatsAppChannel($notifiable);
        } elseif (method_exists($notification, 'toWhatsApp')) {
            $content = $notification->toWhatsApp($notifiable);
        } else {
            return;
        }

        $this->whatsapp->send($phone, $content);
    }
}
