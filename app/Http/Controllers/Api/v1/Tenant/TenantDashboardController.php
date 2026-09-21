<?php

namespace App\Http\Controllers\Api\v1\Tenant;

use App\Enums\InvoiceStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\MaintenanceTicket;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantDashboardController extends TenantBaseController
{
    /**
     * Get aggregated tenant dashboard overview for mobile/external apps.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $this->getTenant($request);

        if (! $tenant) {
            return response()->json([
                'tenant' => null,
                'active_lease' => null,
                'account_summary' => [
                    'total_unpaid_invoices' => 0,
                    'total_outstanding_amount' => 0.00,
                    'next_due_date' => null,
                    'open_maintenance_tickets' => 0,
                ],
                'recent_invoices' => [],
                'recent_tickets' => [],
                'next_action' => [
                    'type' => 'none',
                    'message' => 'No active lease or tenant profile found.',
                ],
            ]);
        }

        // Active lease
        $activeLease = $tenant->leases()
            ->active()
            ->with(['unit.property.city', 'unit.property.propertyType'])
            ->latest('start_date')
            ->first();

        // Lease IDs belonging to this tenant
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

        // Payable invoices calculation
        $payableInvoices = Invoice::query()
            ->whereIn('lease_id', $leaseIds)
            ->whereIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Partial->value])
            ->orderBy('due_date');

        $unpaidCount = (clone $payableInvoices)->count();
        $totalOutstanding = (clone $payableInvoices)->get()->sum(function (Invoice $inv) {
            return max(0, (float) $inv->total - (float) $inv->amount_paid);
        });

        $nextInvoice = (clone $payableInvoices)->first();
        $nextDueDate = $nextInvoice?->due_date?->toDateString();

        // Open maintenance tickets
        $openTicketsCount = MaintenanceTicket::query()
            ->where('created_by', $user->id)
            ->whereIn('status', [MaintenanceStatus::Reported->value, MaintenanceStatus::InProgress->value])
            ->count();

        // Recent Invoices (last 5)
        $recentInvoices = Invoice::query()
            ->whereIn('lease_id', $leaseIds)
            ->latest('due_date')
            ->limit(5)
            ->get()
            ->map(fn (Invoice $inv) => [
                'id' => $inv->id,
                'reference' => $inv->reference,
                'period_start' => $inv->period_start?->toDateString(),
                'period_end' => $inv->period_end?->toDateString(),
                'due_date' => $inv->due_date?->toDateString(),
                'status' => $inv->status->value,
                'total' => (float) $inv->total,
                'amount_paid' => (float) $inv->amount_paid,
                'outstanding' => max(0, (float) $inv->total - (float) $inv->amount_paid),
                'is_overdue' => $inv->isOverdue(),
            ]);

        // Recent Tickets (last 5)
        $recentTickets = MaintenanceTicket::query()
            ->where('created_by', $user->id)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (MaintenanceTicket $ticket) => [
                'id' => $ticket->id,
                'reference' => $ticket->reference,
                'title' => $ticket->title,
                'status' => $ticket->status->value,
                'priority' => $ticket->priority->value,
                'created_at' => $ticket->created_at->toDateString(),
            ]);

        $now = now()->startOfDay();
        $daysUntilDue = null;
        $isOverdue = false;
        $isDueSoon = false;

        if ($nextInvoice && $nextInvoice->due_date) {
            $dueDate = $nextInvoice->due_date->copy()->startOfDay();
            $daysUntilDue = (int) $now->diffInDays($dueDate, false);
            $isOverdue = $daysUntilDue < 0;
            $isDueSoon = $daysUntilDue <= 10;
        }

        // Invoices due within 10 days or already overdue
        $dueSoonInvoices = (clone $payableInvoices)
            ->whereDate('due_date', '<=', now()->addDays(10)->toDateString())
            ->get();
        $dueSoonCount = $dueSoonInvoices->count();
        $dueSoonOutstanding = $dueSoonInvoices->sum(fn (Invoice $inv) => max(0, (float) $inv->total - (float) $inv->amount_paid));

        // Next Action prompt
        $nextAction = null;
        if (! $user->hasVerifiedPhone()) {
            $nextAction = [
                'type' => 'verify_phone',
                'urgency' => 'normal',
                'is_due_soon' => false,
                'title' => 'Verifikasi Nomor WhatsApp',
                'message' => 'Verifikasi nomor WhatsApp Anda untuk notifikasi otomatis dan konfirmasi pembayaran instan.',
            ];
        } elseif ($nextInvoice) {
            if ($isOverdue) {
                $nextAction = [
                    'type' => 'pay_invoice',
                    'urgency' => 'overdue',
                    'is_due_soon' => true,
                    'title' => 'Tagihan Melewati Jatuh Tempo',
                    'message' => "Tagihan {$nextInvoice->reference} telah melewati tanggal jatuh tempo ({$nextDueDate}). Mohon segera lakukan pembayaran.",
                    'invoice_id' => $nextInvoice->id,
                    'reference' => $nextInvoice->reference,
                    'amount' => max(0, (float) $nextInvoice->total - (float) $nextInvoice->amount_paid),
                    'due_date' => $nextDueDate,
                    'days_until_due' => $daysUntilDue,
                    'allow_early_payment' => true,
                ];
            } elseif ($isDueSoon) {
                $dayLabel = $daysUntilDue === 0 ? 'hari ini' : ($daysUntilDue === 1 ? 'besok (1 hari lagi)' : "{$daysUntilDue} hari lagi");
                $nextAction = [
                    'type' => 'pay_invoice',
                    'urgency' => 'due_soon',
                    'is_due_soon' => true,
                    'title' => 'Tagihan Jatuh Tempo Segera',
                    'message' => "Tagihan sewa {$nextInvoice->reference} jatuh tempo {$dayLabel} ({$nextDueDate}).",
                    'invoice_id' => $nextInvoice->id,
                    'reference' => $nextInvoice->reference,
                    'amount' => max(0, (float) $nextInvoice->total - (float) $nextInvoice->amount_paid),
                    'due_date' => $nextDueDate,
                    'days_until_due' => $daysUntilDue,
                    'allow_early_payment' => true,
                ];
            } else {
                // More than 10 days away: Rent is fully current, calm overview with optional early payment
                $nextAction = [
                    'type' => 'early_payment_available',
                    'urgency' => 'relaxed',
                    'is_due_soon' => false,
                    'title' => 'Semua Tagihan Berjalan Lunas ✨',
                    'message' => "Tidak ada tagihan jatuh tempo dalam waktu dekat. Tagihan sewa periode berikutnya jatuh tempo pada {$nextDueDate} ({$daysUntilDue} hari lagi).",
                    'invoice_id' => $nextInvoice->id,
                    'reference' => $nextInvoice->reference,
                    'amount' => max(0, (float) $nextInvoice->total - (float) $nextInvoice->amount_paid),
                    'due_date' => $nextDueDate,
                    'days_until_due' => $daysUntilDue,
                    'allow_early_payment' => true,
                ];
            }
        } else {
            $nextAction = [
                'type' => 'none',
                'urgency' => 'relaxed',
                'is_due_soon' => false,
                'title' => 'Semua Tagihan Lunas',
                'message' => 'Semua tagihan sewa telah lunas. Anda tidak memiliki tagihan tertunda.',
                'allow_early_payment' => false,
            ];
        }

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'phone' => $tenant->phone,
                'email' => $user->email,
                'phone_verified' => $user->hasVerifiedPhone(),
            ],
            'active_lease' => $activeLease ? [
                'id' => $activeLease->id,
                'reference' => $activeLease->reference,
                'start_date' => $activeLease->start_date?->toDateString(),
                'end_date' => $activeLease->end_date?->toDateString(),
                'rent_amount' => (float) $activeLease->rent_amount,
                'deposit_amount' => (float) ($activeLease->deposit_amount ?? 0),
                'deposit_paid_at' => $activeLease->deposit_paid_at?->toIso8601String(),
                'billing_label' => $activeLease->billing_label,
                'status' => $activeLease->status->value,
                'unit' => $activeLease->unit ? [
                    'id' => $activeLease->unit->id,
                    'name' => $activeLease->unit->name,
                    'status' => $activeLease->unit->status->value,
                ] : null,
                'property' => $activeLease->unit?->property ? [
                    'id' => $activeLease->unit->property->id,
                    'name' => $activeLease->unit->property->name,
                    'address' => $activeLease->unit->property->address,
                    'city' => $activeLease->unit->property->city?->name,
                    'image_url' => $activeLease->unit->property->image_url,
                ] : null,
            ] : null,
            'account_summary' => [
                'total_unpaid_invoices' => $unpaidCount,
                'total_outstanding_amount' => round($totalOutstanding, 2),
                'due_soon_invoices_count' => $dueSoonCount,
                'due_soon_outstanding_amount' => round($dueSoonOutstanding, 2),
                'is_due_soon' => $isDueSoon,
                'days_until_due' => $daysUntilDue,
                'next_due_date' => $nextDueDate,
                'next_invoice_id' => $nextInvoice?->id,
                'open_maintenance_tickets' => $openTicketsCount,
            ],
            'next_action' => $nextAction,
            'recent_invoices' => $recentInvoices,
            'recent_tickets' => $recentTickets,
        ]);
    }
}
