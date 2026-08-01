<?php

namespace App\Notifications;

use App\Contracts\MailChannelNotification;
use App\Contracts\WhatsAppChannelNotification;
use App\Data\Mail\MailContent;
use App\Data\Reminder\ReminderEvent;
use App\Data\WhatsApp\WhatsAppContent;
use App\Enums\ReminderType;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\Tenant;
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
        $invoice = $this->invoice();
        $invoiceUrl = $invoice && $notifiable instanceof Tenant && $notifiable->user_id
            ? route('portal.billing.invoices.show', $invoice)
            : null;
        $htmlBody = '<div>'.nl2br(e($messageText)).'</div>';
        $plainTextBody = $messageText;

        if ($invoiceUrl) {
            $label = __('notifications.rent.view_invoice');
            $htmlBody .= '<p><a href="'.e($invoiceUrl).'">'.e($label).'</a></p>';
            $plainTextBody .= "\n\n{$label}: {$invoiceUrl}";
        }

        return new MailContent(
            subject: $subject,
            htmlBody: $htmlBody,
            plainTextBody: $plainTextBody,
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

        $message = $template
            ? str_replace(
                [':name', ':unit', ':days', ':amount', ':date'],
                [$notifiable->name, $this->event->lease->unit?->name ?? '—', $days, $amount, $date],
                $template,
            )
            : __("notifications.rent.{$this->event->type->value}", [
                'name' => $notifiable->name,
                'unit' => $this->event->lease->unit?->name ?? '—',
                'days' => $days,
                'amount' => $amount,
                'date' => $date,
            ]);

        $invoice = $this->invoice();

        if (! $invoice) {
            return $message;
        }

        return $message."\n\n".__('notifications.rent.invoice_context', [
            'reference' => $invoice->reference,
            'period' => Carbon::parse($this->event->periodStart)->format('d M Y')
                .' – '.Carbon::parse($this->event->periodEnd)->format('d M Y'),
            'date' => $date,
            'amount' => $amount,
        ]);
    }

    private function invoice(): ?Invoice
    {
        return isset($this->event->invoice) ? $this->event->invoice : null;
    }
}
