<?php

namespace App\Http\Controllers\Api\v1\Tenant;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Http\Requests\Api\Tenant\SubmitPaymentProofRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentProof;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TenantInvoiceController extends TenantBaseController
{
    /**
     * List invoices for the authenticated tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->requireTenant($request);
        $leaseIds = $tenant->leases()->pluck('leases.id');

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
}
