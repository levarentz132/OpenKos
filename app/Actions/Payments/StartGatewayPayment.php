<?php

namespace App\Actions\Payments;

use App\Business\Payments\PaymentAttemptStatusValidator;
use App\Enums\InvoiceStatus;
use App\Exceptions\InvoiceNotPayableException;
use App\Exceptions\PaymentGatewayCreationException;
use App\Exceptions\PaymentGatewayUnavailableException;
use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Models\Setting;
use App\Models\User;
use App\Results\Payment\StartGatewayPaymentResult;
use App\Services\Payments\CheckoutInstructionsMapper;
use App\Services\Payments\MoneyConverter;
use App\Services\Payments\PaymentGatewayManager;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use OpenKOS\Core\Data\Payment\CheckoutInstructions;
use OpenKOS\Core\Data\Payment\PaymentCreationResult;
use OpenKOS\Core\Data\Payment\PaymentRequest;
use OpenKOS\Core\Enums\PaymentStatus;
use Throwable;

class StartGatewayPayment
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private MoneyConverter $money,
        private CheckoutInstructionsMapper $instructions,
        private PaymentAttemptStatusValidator $statuses,
    ) {}

    public function execute(Invoice $invoice, User $actor): StartGatewayPaymentResult
    {
        Gate::forUser($actor)->authorize('pay', $invoice);

        return $this->start($invoice);
    }

    public function executeViaSignedLink(Invoice $invoice): StartGatewayPaymentResult
    {
        return $this->start($invoice);
    }

    private function start(Invoice $invoice): StartGatewayPaymentResult
    {
        $currency = strtoupper((string) (Setting::get('currency') ?? 'IDR'));
        $state = DB::transaction(function () use ($invoice, $currency) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! in_array($locked->status, [InvoiceStatus::Pending, InvoiceStatus::Partial], true)) {
                throw new InvoiceNotPayableException('Invoice is not payable.');
            }

            $outstanding = BigDecimal::of((string) $locked->total)
                ->minus((string) $locked->amount_paid)
                ->toScale(2);

            if (! $outstanding->isPositive()) {
                throw new InvoiceNotPayableException('Invoice has no outstanding balance.');
            }

            $money = $this->money->toMoney($outstanding->toString(), $currency);

            if ($locked->paymentAttempts()
                ->where('status', PaymentStatus::Settled->value)
                ->exists()) {
                throw new InvoiceNotPayableException('Invoice already has a settled payment attempt.');
            }

            // Keep this query under the invoice lock so only one caller can
            // create and commit a pending attempt for this invoice.
            $pending = $locked->paymentAttempts()
                ->where('status', PaymentStatus::Pending->value)
                ->lockForUpdate()
                ->latest('id')
                ->get();

            foreach ($pending as $attempt) {
                if ($attempt->expires_at?->isPast()) {
                    $this->statuses->validate($attempt->status, PaymentStatus::Expired);
                    $attempt->update([
                        'status' => PaymentStatus::Expired,
                        'expired_at' => now(),
                    ]);

                    continue;
                }

                $creationState = ($attempt->metadata ?? [])['provider_creation_state'] ?? null;

                if (in_array($creationState, ['in_progress', 'uncertain'], true)) {
                    throw new PaymentGatewayCreationException(
                        'Payment checkout creation is still being confirmed.',
                        ambiguous: true,
                    );
                }

                return [
                    'attempt' => $attempt,
                    'money' => $money,
                    'reused' => true,
                ];
            }

            $gatewayKey = $this->gateways->activeKey();
            $gateway = $this->gateways->active();
            if ($gatewayKey === null || $gateway === null) {
                throw new PaymentGatewayUnavailableException('No active payment gateway is configured.');
            }

            $attempt = $locked->paymentAttempts()->create([
                'gateway_key' => $gatewayKey,
                'reference' => (string) str()->uuid(),
                'amount' => $outstanding->toString(),
                'currency' => $currency,
                'status' => PaymentStatus::Pending,
                'metadata' => [
                    'invoice_id' => $locked->id,
                    'invoice_reference' => $locked->reference,
                    'provider_creation_state' => 'in_progress',
                ],
            ]);

            return [
                'attempt' => $attempt,
                'money' => $money,
                'reused' => false,
                'gateway' => $gateway,
            ];
        });

        /** @var PaymentAttempt $attempt */
        $attempt = $state['attempt'];

        if ($state['reused']) {
            $instructions = $this->instructions->fromArray($attempt->checkout_instructions);

            if (! $this->hasUsableInstructions($instructions)) {
                $this->markFailed($attempt);

                throw new PaymentGatewayCreationException(
                    'Payment checkout instructions are unavailable.',
                );
            }

            return new StartGatewayPaymentResult(
                attempt: $attempt,
                instructions: $instructions,
                reused: true,
            );
        }

        $gateway = $state['gateway'];

        try {
            $result = $gateway->createPayment(new PaymentRequest(
                reference: $attempt->reference,
                amount: $state['money'],
                description: "Invoice {$invoice->reference}",
                metadata: [
                    'invoice_id' => $invoice->id,
                    'invoice_reference' => $invoice->reference,
                ],
            ));
        } catch (Throwable $exception) {
            $this->markCreationUncertain($attempt);
            Log::error('Payment gateway checkout creation failed.', [
                'invoice_id' => $invoice->id,
                'attempt_id' => $attempt->id,
                'gateway_key' => $attempt->gateway_key,
                'exception' => $exception,
            ]);

            throw new PaymentGatewayCreationException(
                'Payment checkout creation could not be confirmed.',
                ambiguous: true,
                previous: $exception,
            );
        }

        if (
            $result->amount->minorUnits !== $state['money']->minorUnits
            || $result->amount->currency !== $state['money']->currency
        ) {
            $this->markFailed($attempt);

            throw new PaymentGatewayCreationException(
                'Payment gateway returned an amount or currency that does not match the invoice.',
            );
        }

        if ($result->status === PaymentStatus::Pending && ! $this->hasUsableInstructions($result->instructions)) {
            $this->markFailed($attempt);

            throw new PaymentGatewayCreationException(
                'Payment gateway returned no usable checkout instructions.',
            );
        }

        try {
            $this->persistResult($attempt, $result);
        } catch (Throwable $exception) {
            throw new PaymentGatewayCreationException(
                'Payment checkout response could not be saved.',
                ambiguous: true,
                previous: $exception,
            );
        }

        return new StartGatewayPaymentResult(
            attempt: $attempt->fresh(),
            instructions: $result->instructions,
            reused: false,
        );
    }

    private function persistResult(PaymentAttempt $attempt, PaymentCreationResult $result): void
    {
        DB::transaction(function () use ($attempt, $result): void {
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $updates = [
                'provider_reference' => $result->providerReference,
                'expires_at' => $result->expiresAt,
                'checkout_instructions' => $this->instructions->toArray($result->instructions),
                'metadata' => array_merge(
                    array_diff_key($locked->metadata ?? [], [
                        'provider_creation_state' => true,
                        'provider_creation_uncertain' => true,
                    ]),
                    $result->metadata,
                ),
            ];

            if ($result->status !== PaymentStatus::Pending) {
                $this->statuses->validate($locked->status, $result->status);
                $updates['status'] = $result->status;
                $timestampColumn = match ($result->status) {
                    PaymentStatus::Pending => null,
                    PaymentStatus::Settled => 'settled_at',
                    PaymentStatus::Failed => 'failed_at',
                    PaymentStatus::Expired => 'expired_at',
                    PaymentStatus::Canceled => 'canceled_at',
                };

                if ($timestampColumn !== null) {
                    $updates[$timestampColumn] = now();
                }
            }

            $locked->update($updates);
            $attempt->refresh();
        });
    }

    private function markCreationUncertain(PaymentAttempt $attempt): void
    {
        DB::transaction(function () use ($attempt): void {
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($locked->status === PaymentStatus::Pending) {
                $locked->update([
                    'metadata' => array_merge($locked->metadata ?? [], [
                        'provider_creation_state' => 'uncertain',
                        'provider_creation_uncertain' => true,
                    ]),
                ]);
            }
        });
    }

    private function markFailed(PaymentAttempt $attempt): void
    {
        DB::transaction(function () use ($attempt): void {
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($locked->status === PaymentStatus::Pending) {
                $this->statuses->validate($locked->status, PaymentStatus::Failed);
                $locked->update([
                    'status' => PaymentStatus::Failed,
                    'failed_at' => now(),
                    'metadata' => array_merge($locked->metadata ?? [], [
                        'provider_creation_state' => 'failed',
                    ]),
                ]);
            }
        });
    }

    private function hasUsableInstructions(CheckoutInstructions $instructions): bool
    {
        return $instructions->url !== null || $instructions->entries !== [];
    }
}
