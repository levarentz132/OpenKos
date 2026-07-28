<?php

namespace App\Notifications;

use App\Contracts\MailChannelNotification;
use App\Contracts\WhatsAppChannelNotification;
use App\Data\Mail\MailContent;
use App\Data\Reminder\ReminderEvent;
use App\Data\WhatsApp\WhatsAppContent;
use App\Enums\ReminderType;
use App\Models\Setting;
use App\Notifications\Channels\LogChannel;
use App\Notifications\Channels\MailChannel;
use App\Notifications\Channels\WhatsAppChannel;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class RentReminder extends Notification implements MailChannelNotification, ShouldQueue, WhatsAppChannelNotification
{
    use Queueable;

    public function __construct(private ReminderEvent $event) {}

    public function via(object $notifiable): array
    {
        $map = [
            'log' => LogChannel::class,
            'whatsapp' => WhatsAppChannel::class,
            'mail' => MailChannel::class,
        ];

        $channels = Setting::get('reminder_channels') ?? ['log'];

        return array_values(array_intersect_key($map, array_flip($channels)));
    }

    public function toMailChannel(object $notifiable): MailContent
    {
        $subject = match ($this->event->type) {
            ReminderType::Upcoming => __('Rent Reminder'),
            ReminderType::DueToday => __('Rent Due Today'),
            ReminderType::Overdue => __('Rent Overdue'),
        };

        $messageText = $this->renderMessage($notifiable);

        return new MailContent(
            subject: $subject,
            htmlBody: '<div>'.e($messageText).'</div>',
            plainTextBody: $messageText,
        );
    }

    public function toWhatsAppChannel(object $notifiable): WhatsAppContent
    {
        return new WhatsAppContent(
            message: $this->renderMessage($notifiable),
        );
    }

    public function toLog(object $notifiable): string
    {
        return $this->renderMessage($notifiable);
    }

    public function toWhatsApp(object $notifiable): string
    {
        return $this->renderMessage($notifiable);
    }

    private function renderMessage(object $notifiable): string
    {
        $days = $this->event->overdueDays
            ?? (int) now()->startOfDay()->diffInDays(Carbon::parse($this->event->dueDate), false);

        $amount = number_format($this->event->amount / 100, 0);
        $date = Carbon::parse($this->event->dueDate)->format('d M Y');

        $template = Setting::get('reminder_message_template');

        if ($template) {
            return str_replace(
                [':name', ':unit:', ':days', ':amount', ':date'],
                [$notifiable->name, $this->event->lease->unit?->name ?? '—', $this->event->lease->unit?->name ?? '—', $days, $amount, $date],
                $template,
            );
        }

        return __("notifications.rent.{$this->event->type->value}", [
            'name' => $notifiable->name,
            'unit' => $this->event->lease->unit?->name ?? '—',
            'days' => $days,
            'amount' => $amount,
            'date' => $date,
        ]);
    }
}
