<?php

namespace App\Http\Controllers;

use App\Actions\Payments\ReconcilePaymentAttempt;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\PaymentAttempt;
use App\Results\Payment\ReconcilePaymentAttemptResult;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class PaymentAttemptController extends Controller
{
    public function recheck(
        Lease $lease,
        Invoice $invoice,
        PaymentAttempt $paymentAttempt,
        ReconcilePaymentAttempt $reconcile,
    ): RedirectResponse {
        abort_if($invoice->lease_id !== $lease->id || $paymentAttempt->invoice_id !== $invoice->id, 404);

        $this->authorize('view', $lease);
        $result = $reconcile->execute($paymentAttempt);
        $type = in_array($result->status, [
            ReconcilePaymentAttemptResult::SETTLED,
            ReconcilePaymentAttemptResult::TERMINAL,
        ], true) ? 'success' : 'error';
        $message = match ($result->status) {
            ReconcilePaymentAttemptResult::SETTLED => __('Payment attempt settled.'),
            ReconcilePaymentAttemptResult::PENDING => __('The provider still reports this payment as pending.'),
            ReconcilePaymentAttemptResult::TERMINAL => __('The payment attempt is already finalized.'),
            ReconcilePaymentAttemptResult::UNSUPPORTED => __('This payment gateway does not support status lookup.'),
            ReconcilePaymentAttemptResult::UNAVAILABLE => __('The payment gateway is unavailable.'),
            ReconcilePaymentAttemptResult::ANOMALY => __('The provider result could not be applied safely.'),
            default => __('The payment provider status could not be checked.'),
        };

        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }
}
