<?php

namespace App\Notifications;

use App\Actions\Invoices\GenerateInvoicePdf;
use App\Contracts\MailChannelNotification;
use App\Contracts\WhatsAppChannelNotification;
use App\Data\Mail\MailAttachment;
use App\Data\Mail\MailContent;
use App\Data\Reminder\ReminderEvent;
use App\Data\WhatsApp\WhatsAppAttachment;
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

    private ?string $invoicePdfContent = null;

    public function __construct(private ReminderEvent $event) {}

    public function via(object $notifiable): array
    {
        $map = [
            'database' => 'database',
            'log' => LogChannel::class,
            'whatsapp' => WhatsAppChannel::class,
            'mail' => MailChannel::class,
        ];

        $channels = Setting::get('reminder_channels') ?? ['log'];

        return array_merge(['database'], array_values(array_intersect_key($map, array_flip($channels))));
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $invoice = $this->invoice();

        if (! $invoice?->getKey()) {
            return true;
        }

        return Invoice::query()->payable()->whereKey($invoice->getKey())->exists();
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

        $attachments = [];
        if ($invoice && $notifiable instanceof Tenant && $notifiable->user?->email) {
            $reference = preg_replace(
                '/[^A-Za-z0-9_-]/',
                '-',
                $invoice->reference ?? (string) $invoice->getKey(),
            );

            $attachments[] = new MailAttachment(
                content: $this->invoicePdfContent($invoice),
                filename: 'invoice-'.($reference ?: $invoice->getKey()).'.pdf',
                mimeType: 'application/pdf',
            );
        }

        return new MailContent(
            subject: $subject,
            htmlBody: $htmlBody,
            plainTextBody: $plainTextBody,
            attachments: $attachments,
        );
    }

    public function toWhatsAppChannel(object $notifiable): WhatsAppContent
    {
        $attachment = null;
        $invoice = $this->invoice();

        if ($invoice) {
            $reference = preg_replace(
                '/[^A-Za-z0-9_-]/',
                '-',
                $invoice->reference ?? (string) $invoice->getKey(),
            );

            $attachment = new WhatsAppAttachment(
                content: $this->invoicePdfContent($invoice),
                filename: 'invoice-'.($reference ?: $invoice->getKey()).'.pdf',
                mimeType: 'application/pdf',
            );
        }

        return new WhatsAppContent(
            message: $this->renderMessage($notifiable),
            attachment: $attachment,
        );
    }

    public function toLog(object $notifiable): string
    {
        return $this->renderMessage($notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $invoice = $this->invoice();

        return [
            'type' => 'rent_reminder',
            'title' => __('Rent reminder'),
            'message' => $this->renderMessage($notifiable),
            'url' => $invoice ? route('portal.billing.invoices.show', $invoice) : route('portal.billing.index'),
        ];
    }

    public function databaseType(object $notifiable): string
    {
        return 'rent_reminder';
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

    private function invoicePdfContent(Invoice $invoice): string
    {
        return $this->invoicePdfContent ??= app(GenerateInvoicePdf::class)->execute($invoice);
    }
}
