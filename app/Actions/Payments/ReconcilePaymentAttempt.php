<?php

namespace App\Actions\Payments;

use App\Models\PaymentAttempt;
use App\Results\Payment\ApplyGatewayPaymentResult as ApplyGatewayPaymentResultData;
use App\Results\Payment\ReconcilePaymentAttemptResult;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\Log;
use OpenKOS\Core\Contracts\PaymentGatewayStatusLookup;
use OpenKOS\Core\Data\Payment\PaymentStatusLookupRequest;
use OpenKOS\Core\Enums\PaymentStatus;
use Throwable;

final class ReconcilePaymentAttempt
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private ApplyGatewayPaymentResult $apply,
    ) {}

    public function execute(PaymentAttempt $attempt): ReconcilePaymentAttemptResult
    {
        $attempt->refresh();

        if ($attempt->status !== PaymentStatus::Pending) {
            return new ReconcilePaymentAttemptResult(
                status: ReconcilePaymentAttemptResult::TERMINAL,
                attempt: $attempt,
            );
        }

        if ($attempt->provider_reference === null) {
            return new ReconcilePaymentAttemptResult(
                status: ReconcilePaymentAttemptResult::UNSUPPORTED,
                attempt: $attempt,
                message: 'The provider reference is unavailable for status lookup.',
            );
        }

        $gateway = $this->gateways->find($attempt->gateway_key);

        if ($gateway === null) {
            return new ReconcilePaymentAttemptResult(
                status: ReconcilePaymentAttemptResult::UNAVAILABLE,
                attempt: $attempt,
                message: 'The payment gateway is unavailable.',
            );
        }

        if (! $gateway instanceof PaymentGatewayStatusLookup) {
            return new ReconcilePaymentAttemptResult(
                status: ReconcilePaymentAttemptResult::UNSUPPORTED,
                attempt: $attempt,
                message: 'This payment gateway does not support status lookup.',
            );
        }

        try {
            $providerResult = $gateway->lookupPaymentStatus(new PaymentStatusLookupRequest(
                providerReference: $attempt->provider_reference,
                reference: $attempt->reference,
                metadata: $attempt->metadata ?? [],
            ));
            $applied = $this->apply->execute(
                $attempt->gateway_key,
                $providerResult,
                source: 'reconciliation',
            );
        } catch (Throwable $exception) {
            Log::warning('Payment attempt reconciliation failed.', [
                'attempt_id' => $attempt->id,
                'attempt_reference' => $attempt->reference,
                'gateway_key' => $attempt->gateway_key,
                'provider_reference' => $attempt->provider_reference,
                'exception_class' => $exception::class,
            ]);

            return new ReconcilePaymentAttemptResult(
                status: ReconcilePaymentAttemptResult::FAILED,
                attempt: $attempt->fresh(),
                message: 'The payment provider status could not be checked.',
            );
        }

        $freshAttempt = $applied->attempt?->fresh() ?? $attempt->fresh();

        $status = match (true) {
            $applied->status === ApplyGatewayPaymentResultData::ANOMALY => ReconcilePaymentAttemptResult::ANOMALY,
            $freshAttempt?->status === PaymentStatus::Settled => ReconcilePaymentAttemptResult::SETTLED,
            $freshAttempt?->status === PaymentStatus::Pending => ReconcilePaymentAttemptResult::PENDING,
            default => ReconcilePaymentAttemptResult::TERMINAL,
        };

        Log::info('Payment attempt reconciliation completed.', [
            'attempt_id' => $freshAttempt?->id ?? $attempt->id,
            'attempt_reference' => $attempt->reference,
            'gateway_key' => $attempt->gateway_key,
            'provider_reference' => $attempt->provider_reference,
            'result' => $status,
        ]);

        return new ReconcilePaymentAttemptResult(
            status: $status,
            attempt: $freshAttempt,
            payment: $applied->payment,
        );
    }
}
