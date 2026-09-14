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

        // Next Action prompt
        $nextAction = null;
        if (! $user->hasVerifiedPhone()) {
            $nextAction = [
                'type' => 'verify_phone',
                'title' => 'Verify Phone Number',
                'message' => 'Please verify your WhatsApp phone number to enable instant notifications and quick payment confirmations.',
            ];
        } elseif ($nextInvoice) {
            $isOverdue = $nextInvoice->isOverdue();
            $nextAction = [
                'type' => 'pay_invoice',
                'title' => $isOverdue ? 'Invoice Overdue' : 'Upcoming Rent Payment',
                'message' => $isOverdue
                    ? "Invoice {$nextInvoice->reference} is overdue. Please settle payment immediately."
                    : "Invoice {$nextInvoice->reference} is due on {$nextDueDate}.",
                'invoice_id' => $nextInvoice->id,
                'reference' => $nextInvoice->reference,
                'amount' => max(0, (float) $nextInvoice->total - (float) $nextInvoice->amount_paid),
                'due_date' => $nextDueDate,
            ];
        } else {
            $nextAction = [
                'type' => 'none',
                'title' => 'All Caught Up',
                'message' => 'You have no pending payments or actions required.',
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
                'next_due_date' => $nextDueDate,
                'open_maintenance_tickets' => $openTicketsCount,
            ],
            'next_action' => $nextAction,
            'recent_invoices' => $recentInvoices,
            'recent_tickets' => $recentTickets,
        ]);
    }
}
