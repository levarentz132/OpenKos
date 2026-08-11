<?php

use App\Notifications\Channels\WhatsAppChannel;
use App\Services\WhatsAppManager;
use Illuminate\Notifications\Notification;
use OpenKOS\Core\Contracts\WhatsAppChannelNotification;
use OpenKOS\Core\Data\WhatsApp\WhatsAppContent;

describe('WhatsAppChannel', function () {
    it('sends notification using WhatsAppChannelNotification contract', function () {
        $manager = Mockery::mock(WhatsAppManager::class);
        $manager->shouldReceive('send')
            ->once()
            ->with('628123456789', Mockery::on(fn ($content) => $content instanceof WhatsAppContent && $content->message === 'Contract Message'));

        $channel = new WhatsAppChannel($manager);

        $notifiable = new class
        {
            public function routeNotificationForWhatsApp($notification)
            {
                return '628123456789';
            }
        };

        $notification = new class extends Notification implements WhatsAppChannelNotification
        {
            public function toWhatsAppChannel(object $notifiable): WhatsAppContent
            {
                return new WhatsAppContent('Contract Message');
            }
        };

        $channel->send($notifiable, $notification);
    });

    it('falls back to legacy toWhatsApp method when contract is not implemented', function () {
        $manager = Mockery::mock(WhatsAppManager::class);
        $manager->shouldReceive('send')
            ->once()
            ->with('628123456789', 'Legacy String Message');

        $channel = new WhatsAppChannel($manager);

        $notifiable = new class
        {
            public function routeNotificationForWhatsApp($notification)
            {
                return '628123456789';
            }
        };

        $notification = new class extends Notification
        {
            public function toWhatsApp(object $notifiable): string
            {
                return 'Legacy String Message';
            }
        };

        $channel->send($notifiable, $notification);
    });

    it('bails early if notifiable has no whatsapp route', function () {
        $manager = Mockery::mock(WhatsAppManager::class);
        $manager->shouldNotReceive('send');

        $channel = new WhatsAppChannel($manager);

        $notifiable = new class
        {
            public function routeNotificationForWhatsApp($notification)
            {
                return null;
            }
        };

        $notification = new class extends Notification
        {
            public function toWhatsApp(object $notifiable): string
            {
                return 'Message';
            }
        };

        $channel->send($notifiable, $notification);
    });
});
