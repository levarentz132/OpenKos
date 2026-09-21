<?php

namespace App\Http\Controllers\Api\v1\Tenant;

use App\Enums\LeaseStatus;
use App\Models\Lease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantLeaseController extends TenantBaseController
{
    /**
     * List all leases for the authenticated tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->requireTenant($request);

        $leases = $tenant->leases()
            ->with(['unit.property.city', 'unit.property.propertyType'])
            ->latest('start_date')
            ->get();

        $activeLeases = $leases->filter(fn (Lease $l) => $l->status === LeaseStatus::Active)->values();
        $otherLeases = $leases->filter(fn (Lease $l) => $l->status !== LeaseStatus::Active)->values();

        return response()->json([
            'current_leases' => $activeLeases->map(fn (Lease $l) => $this->formatLease($l)),
            'lease_history' => $otherLeases->map(fn (Lease $l) => $this->formatLease($l)),
        ]);
    }

    /**
     * Show details for a specific lease owned by the tenant.
     */
    public function show(Request $request, Lease $lease): JsonResponse
    {
        $tenant = $this->requireTenant($request);

        // Security scoping: ensure lease belongs to this tenant
        $tenantLease = $tenant->leases()
            ->whereKey($lease->id)
            ->with([
                'unit.property.city',
                'unit.property.propertyType',
                'invoices' => fn ($query) => $query->latest('due_date'),
                'unitHistories.fromUnit:id,name',
                'unitHistories.toUnit:id,name',
            ])
            ->firstOrFail();

        return response()->json([
            'lease' => $this->formatLeaseDetails($tenantLease),
        ]);
    }

    /**
     * Format lightweight lease representation.
     */
    protected function formatLease(Lease $lease): array
    {
        return [
            'id' => $lease->id,
            'reference' => $lease->reference,
            'start_date' => $lease->start_date?->toDateString(),
            'end_date' => $lease->end_date?->toDateString(),
            'rent_amount' => (float) $lease->rent_amount,
            'billing_label' => $lease->billing_label,
            'billing_cycle' => $lease->billing_cycle?->value,
            'status' => $lease->status->value,
            'deposit_amount' => (float) ($lease->deposit_amount ?? 0),
            'deposit_paid_at' => $lease->deposit_paid_at?->toIso8601String(),
            'unit' => $lease->unit ? [
                'id' => $lease->unit->id,
                'name' => $lease->unit->name,
                'floor' => $lease->unit->floor,
                'status' => $lease->unit->status->value,
            ] : null,
            'property' => $lease->unit?->property ? [
                'id' => $lease->unit->property->id,
                'name' => $lease->unit->property->name,
                'address' => $lease->unit->property->address,
                'city' => $lease->unit->property->city?->name,
                'image_url' => $lease->unit->property->image_url,
            ] : null,
        ];
    }

    /**
     * Format complete lease details.
     */
    protected function formatLeaseDetails(Lease $lease): array
    {
        $data = $this->formatLease($lease);

        $data['deposit_amount'] = (float) $lease->deposit_amount;
        $data['notes'] = $lease->notes;
        $data['invoices'] = $lease->invoices->map(fn ($inv) => [
            'id' => $inv->id,
            'reference' => $inv->reference,
            'period_start' => $inv->period_start?->toDateString(),
            'period_end' => $inv->period_end?->toDateString(),
            'due_date' => $inv->due_date?->toDateString(),
            'status' => $inv->status->value,
            'total' => (float) $inv->total,
            'amount_paid' => (float) $inv->amount_paid,
            'outstanding' => max(0, (float) $inv->total - (float) $inv->amount_paid),
        ]);

        return $data;
    }
}
