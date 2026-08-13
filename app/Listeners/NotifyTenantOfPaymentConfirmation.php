<?php

namespace App\Listeners;

use App\Enums\PaymentStatus;
use App\Events\Payment\PaymentStatusChanged;
use App\Notifications\TenantPortalNotification;

class NotifyTenantOfPaymentConfirmation
{
    public function handle(PaymentStatusChanged $event): void
    {
        if ($event->to !== PaymentStatus::Confirmed) {
            return;
        }

        $invoice = $event->payment->invoice()->with('lease.primaryTenant')->first();
        $tenant = $invoice?->lease?->primaryTenant;
        if (! $tenant) {
            return;
        }

        $tenant->notify(new TenantPortalNotification([
            'type' => 'payment_confirmed',
            'title' => __('Payment confirmed'),
            'message' => __('Your payment has been confirmed.'),
            'url' => route('portal.billing.invoices.show', $invoice),
        ]));
    }
}
