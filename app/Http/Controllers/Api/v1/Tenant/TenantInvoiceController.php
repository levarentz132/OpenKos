<?php

namespace App\Http\Controllers\Api\v1\Tenant;

use App\Actions\Payments\StartGatewayPayment;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentGatewayCreationException;
use App\Exceptions\PaymentGatewayUnavailableException;
use App\Http\Requests\Api\Tenant\SubmitPaymentProofRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentProof;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class TenantInvoiceController extends TenantBaseController
{
    /**
     * List invoices for the authenticated tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->requireTenant($request);
        $leaseIds = $tenant->leases()->pluck('leases.id');

        // Auto-reconcile any pending gateway attempts for tenant's unpaid invoices
        try {
            $pendingInvoiceIds = Invoice::whereIn('lease_id', $leaseIds)
                ->whereIn('status', [InvoiceStatus::Pending, InvoiceStatus::Partial])
                ->pluck('id');

            if ($pendingInvoiceIds->isNotEmpty()) {
                $pendingAttempts = \App\Models\PaymentAttempt::whereIn('invoice_id', $pendingInvoiceIds)
                    ->where('status', \OpenKOS\Core\Enums\PaymentStatus::Pending)
                    ->whereNotNull('provider_reference')
                    ->get();

                if ($pendingAttempts->isNotEmpty()) {
                    $reconciler = app(\App\Actions\Payments\ReconcilePaymentAttempt::class);
                    foreach ($pendingAttempts as $pendingAttempt) {
                        $reconciler->execute($pendingAttempt);
                    }
                }
            }
        } catch (\Throwable) {}

        $query = Invoice::query()
            ->whereIn('lease_id', $leaseIds)
            ->with(['lease.unit.property:id,name']);

        // Filter by lease
        if ($request->filled('lease_id')) {
            $query->where('lease_id', $request->integer('lease_id'));
        }

        // Filter by status
        $statusFilter = $request->query('status');
        if ($statusFilter === 'unpaid') {
            $query->whereIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Partial->value]);
        } elseif ($statusFilter === 'paid') {
            $query->where('status', InvoiceStatus::Paid->value);
        } elseif ($statusFilter === 'overdue') {
            $query->whereIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Partial->value])
                ->whereDate('due_date', '<', now());
        } elseif ($request->filled('status')) {
            $query->where('status', $statusFilter);
        }

        $invoices = $query->latest('due_date')->paginate(15);

        return response()->json([
            'invoices' => $invoices->through(fn (Invoice $inv) => [
                'id' => $inv->id,
                'reference' => $inv->reference,
                'lease_id' => $inv->lease_id,
                'lease_reference' => $inv->lease?->reference,
                'property_name' => $inv->lease?->unit?->property?->name,
                'unit_name' => $inv->lease?->unit?->name,
                'period_start' => $inv->period_start?->toDateString(),
                'period_end' => $inv->period_end?->toDateString(),
                'due_date' => $inv->due_date?->toDateString(),
                'status' => $inv->status->value,
                'total' => (float) $inv->total,
                'amount_paid' => (float) $inv->amount_paid,
                'outstanding' => max(0, (float) $inv->total - (float) $inv->amount_paid),
                'is_overdue' => $inv->isOverdue(),
            ]),
        ]);
    }

    /**
     * Show detailed breakdown of a single invoice.
     */
    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $tenant = $this->requireTenant($request);

        // Security check: invoice must belong to a lease owned by the tenant
        abort_unless($tenant->leases()->whereKey($invoice->lease_id)->exists(), 404);

        // Auto-reconcile pending payment attempts for this invoice
        if (in_array($invoice->status, [InvoiceStatus::Pending, InvoiceStatus::Partial], true)) {
            try {
                $pendingAttempts = $invoice->paymentAttempts()
                    ->where('status', \OpenKOS\Core\Enums\PaymentStatus::Pending)
                    ->whereNotNull('provider_reference')
                    ->get();

                if ($pendingAttempts->isNotEmpty()) {
                    $reconciler = app(\App\Actions\Payments\ReconcilePaymentAttempt::class);
                    foreach ($pendingAttempts as $pendingAttempt) {
                        $reconciler->execute($pendingAttempt);
                    }
                    $invoice->refresh();
                }
            } catch (\Throwable) {}
        }

        $invoice->load([
            'lease.unit.property.city',
            'payments.proofs',
        ]);

        return response()->json([
            'invoice' => [
                'id' => $invoice->id,
                'reference' => $invoice->reference,
                'lease_id' => $invoice->lease_id,
                'period_start' => $invoice->period_start?->toDateString(),
                'period_end' => $invoice->period_end?->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'status' => $invoice->status->value,
                'total' => (float) $invoice->total,
                'amount_paid' => (float) $invoice->amount_paid,
                'outstanding' => max(0, (float) $invoice->total - (float) $invoice->amount_paid),
                'is_overdue' => $invoice->isOverdue(),
                'lease' => [
                    'reference' => $invoice->lease?->reference,
                    'unit_name' => $invoice->lease?->unit?->name,
                    'property_name' => $invoice->lease?->unit?->property?->name,
                    'address' => $invoice->lease?->unit?->property?->address,
                ],
                'payments' => $invoice->payments->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'amount' => (float) $p->amount,
                    'payment_date' => $p->payment_date?->toDateString(),
                    'payment_method' => $p->payment_method,
                    'status' => $p->status,
                    'reference_number' => $p->reference_number,
                    'proof_urls' => $p->proofs->map(fn ($proof) => Storage::disk('public')->url($proof->path)),
                ]),
            ],
        ]);
    }

    /**
     * Submit payment proof or manual payment record for an invoice.
     */
    public function submitPayment(SubmitPaymentProofRequest $request, Invoice $invoice): JsonResponse
    {
        $tenant = $this->requireTenant($request);

        // Security check
        abort_unless($tenant->leases()->whereKey($invoice->lease_id)->exists(), 404);

        // Ensure invoice is payable
        if (! in_array($invoice->status, [InvoiceStatus::Pending, InvoiceStatus::Partial], true)) {
            throw ValidationException::withMessages([
                'invoice' => ['This invoice is already settled or cannot accept payments.'],
            ]);
        }

        $validated = $request->validated();

        $payment = DB::transaction(function () use ($validated, $request, $invoice) {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $validated['amount'],
                'payment_date' => $validated['payment_date'] ?? now()->toDateString(),
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'] ?? null,
                'status' => PaymentStatus::Pending->value,
                'recorded_by' => $request->user()->id,
            ]);

            if ($request->hasFile('proof_image')) {
                $file = $request->file('proof_image');
                $path = $file->store('payment-proofs', 'public');

                PaymentProof::create([
                    'payment_id' => $payment->id,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                ]);
            }

            return $payment;
        });

        return response()->json([
            'message' => 'Payment submitted successfully. Awaiting verification by management.',
            'payment' => [
                'id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'amount' => (float) $payment->amount,
                'status' => $payment->status,
                'payment_date' => $payment->payment_date,
            ],
        ], 201);
    }

    /**
     * Generate an online payment checkout session (e.g. DOKU Jokul Checkout).
     */
    public function checkout(
        Request $request,
        Invoice $invoice,
        StartGatewayPayment $startGatewayPayment,
    ): JsonResponse {
        $tenant = $this->requireTenant($request);

        abort_unless($tenant->leases()->whereKey($invoice->lease_id)->exists(), 404);

        if (! in_array($invoice->status, [InvoiceStatus::Pending, InvoiceStatus::Partial], true)) {
            return response()->json([
                'message' => 'This invoice is already settled or cannot accept payments.',
            ], 422);
        }

        try {
            $result = $startGatewayPayment->execute($invoice, $request->user());

            return response()->json([
                'message' => 'Checkout session created successfully.',
                'checkout_url' => $result->instructions->url,
                'reused' => $result->reused,
                'attempt' => [
                    'id' => $result->attempt->id,
                    'reference' => $result->attempt->reference,
                    'provider_reference' => $result->attempt->provider_reference,
                    'amount' => (float) $result->attempt->amount,
                    'currency' => $result->attempt->currency,
                    'status' => $result->attempt->status->value,
                    'expires_at' => $result->attempt->expires_at?->toIso8601String(),
                ],
            ]);
        } catch (PaymentGatewayUnavailableException $e) {
            return response()->json([
                'message' => 'Online payment is currently unavailable.',
                'error' => $e->getMessage(),
            ], 503);
        } catch (PaymentGatewayCreationException $e) {
            return response()->json([
                'message' => 'Failed to initialize payment gateway checkout.',
                'error' => $e->getMessage(),
            ], 502);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'An unexpected error occurred while starting checkout.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
