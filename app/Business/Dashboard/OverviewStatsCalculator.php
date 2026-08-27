<?php

namespace App\Business\Dashboard;

use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Enums\PaymentStatus;
use App\Enums\UnitStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OverviewStatsCalculator
{
    public function computeFinance(Builder $activeLeasesQuery): array
    {
        $monthlyPotential = (clone $activeLeasesQuery)->sum('rent_amount');

        $now = now();
        $currentMonth = (int) $now->month;
        $currentYear = (int) $now->year;

        $leaseIds = (clone $activeLeasesQuery)->pluck('id');

        $periodStart = Carbon::create($currentYear, $currentMonth, 1)->startOfDay();
        $periodEnd = Carbon::create($currentYear, $currentMonth, 1)->endOfMonth()->endOfDay();

        $additionalIncomeThisMonth = (float) DB::table('additional_incomes')
            ->whereBetween('income_date', [$periodStart->format('Y-m-d'), $periodEnd->format('Y-m-d')])
            ->sum('amount');

        $revenueThisMonth = Payment::where('status', 'confirmed')
            ->whereHas('invoice', fn (Builder $q) => $q
                ->whereBetween('period_start', [$periodStart, $periodEnd])
                ->whereIn('lease_id', $leaseIds))
            ->sum('amount') + $additionalIncomeThisMonth;

        $outstanding = (float) Invoice::whereIn('lease_id', $leaseIds)
            ->whereBetween('period_start', [$periodStart, $periodEnd])
            ->whereIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Partial->value])
            ->sum(DB::raw('total - amount_paid'));

        $collectionRate = $monthlyPotential > 0
            ? round(($revenueThisMonth / $monthlyPotential) * 100)
            : 0;

        return [
            'revenue_this_month' => (int) $revenueThisMonth,
            'monthly_potential' => (int) $monthlyPotential,
            'outstanding' => (int) $outstanding,
            'collection_rate' => $collectionRate,
        ];
    }

    public function computeMonthlyPropertyIncome(
        Collection $accessiblePropertyIds,
        ?CarbonInterface $startDate = null,
        ?CarbonInterface $endDate = null
    ): array {
        $start = ($startDate ? $startDate->copy() : now()->subMonths(5))->startOfMonth();
        $end = ($endDate ? $endDate->copy() : now())->endOfMonth();

        if ($start->gt($end)) {
            $temp = $start;
            $start = $end->copy()->startOfMonth();
            $end = $temp->copy()->endOfMonth();
        }

        // Cap maximum date range to 36 months to guarantee instant execution
        if ($start->diffInMonths($end) > 36) {
            $start = $end->copy()->subMonths(35)->startOfMonth();
        }

        $propIds = $accessiblePropertyIds->map(fn ($v) => is_object($v) ? $v->id : (is_array($v) ? $v['id'] : (int) $v))->values()->all();

        $paymentRecords = ! empty($propIds)
            ? DB::table('payments')
                ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
                ->join('leases', 'leases.id', '=', 'invoices.lease_id')
                ->join('units', 'units.id', '=', 'leases.unit_id')
                ->leftJoin('tenants', 'tenants.id', '=', 'leases.primary_tenant_id')
                ->where('payments.status', PaymentStatus::Confirmed->value)
                ->whereBetween('invoices.period_start', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->whereIn('units.property_id', $propIds)
                ->select([
                    'payments.id as payment_id',
                    'payments.amount',
                    'payments.payment_date',
                    'payments.payment_method',
                    'payments.reference_number',
                    'invoices.id as invoice_id',
                    'invoices.reference as invoice_reference',
                    'invoices.period_start',
                    'leases.id as lease_id',
                    'tenants.name as tenant_name',
                    'units.name as unit_name',
                    'units.property_id',
                ])
                ->get()
            : collect();

        $incomeMap = [];
        $paymentDetailsMap = [];
        foreach ($paymentRecords as $rec) {
            $monthKey = substr((string) $rec->period_start, 0, 7);
            $propId = (int) $rec->property_id;
            $incomeMap[$monthKey][$propId] = ($incomeMap[$monthKey][$propId] ?? 0) + (int) $rec->amount;
            $paymentDetailsMap[$monthKey][$propId][] = [
                'id' => (int) $rec->payment_id,
                'amount' => (int) $rec->amount,
                'payment_date' => (string) $rec->payment_date,
                'payment_method' => $rec->payment_method ?? 'cash',
                'reference_number' => $rec->reference_number,
                'invoice_id' => (int) $rec->invoice_id,
                'invoice_reference' => $rec->invoice_reference,
                'lease_id' => (int) $rec->lease_id,
                'tenant_name' => $rec->tenant_name ?? 'Vendor / Staff / Direct',
                'unit_name' => $rec->unit_name,
            ];
        }

        // Fetch non-property additional incomes
        $additionalIncomeRecords = DB::table('additional_incomes')
            ->whereBetween('income_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->select(['id', 'title', 'category', 'amount', 'income_date', 'notes'])
            ->get();

        $additionalIncomeMap = [];
        $additionalIncomeDetailsMap = [];
        foreach ($additionalIncomeRecords as $addRec) {
            $monthKey = substr((string) $addRec->income_date, 0, 7);
            $additionalIncomeMap[$monthKey] = ($additionalIncomeMap[$monthKey] ?? 0) + (int) $addRec->amount;
            $additionalIncomeDetailsMap[$monthKey][] = [
                'id' => (int) $addRec->id,
                'title' => $addRec->title,
                'category' => $addRec->category,
                'amount' => (int) $addRec->amount,
                'income_date' => (string) $addRec->income_date,
                'notes' => $addRec->notes,
            ];
        }

        // Fetch all historical and active leases overlapping with the requested date range
        $leaseRecords = ! empty($propIds)
            ? DB::table('leases')
                ->join('units', 'units.id', '=', 'leases.unit_id')
                ->whereIn('units.property_id', $propIds)
                ->whereNull('leases.deleted_at')
                ->where('leases.start_date', '<=', $end->format('Y-m-d'))
                ->where(function ($q) use ($start) {
                    $q->whereNull('leases.end_date')
                        ->orWhere('leases.end_date', '>=', $start->format('Y-m-d'));
                })
                ->select([
                    'units.property_id',
                    'leases.unit_id',
                    'leases.start_date',
                    'leases.end_date',
                    'leases.termination_date',
                    'leases.status',
                ])
                ->get()
            : collect();

        $properties = Property::query()
            ->whereIn('id', $propIds)
            ->withCount([
                'units',
                'units as occupied_units_count' => fn (Builder $q) => $q
                    ->where(function (Builder $q) {
                        $q->whereIn('status', [UnitStatus::Occupied->value, UnitStatus::Vendor->value])
                            ->orWhereHas('leases', fn (Builder $q) => $q->where('status', LeaseStatus::Active->value));
                    }),
                'units as maintenance_units_count' => fn (Builder $q) => $q
                    ->where('status', UnitStatus::Maintenance)
                    ->whereDoesntHave('leases', fn (Builder $q) => $q->where('status', LeaseStatus::Active->value)),
                'units as unavailable_units_count' => fn (Builder $q) => $q
                    ->where('status', UnitStatus::Unavailable)
                    ->whereDoesntHave('leases', fn (Builder $q) => $q->where('status', LeaseStatus::Active->value)),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $months = [];
        $current = $start->copy()->startOfMonth();
        $currentMonthKey = now()->format('Y-m');

        while ($current->lte($end)) {
            $monthKey = $current->format('Y-m');
            $monthName = $current->format('M Y');
            $mStartStr = $current->copy()->startOfMonth()->format('Y-m-d');
            $mEndStr = $current->copy()->endOfMonth()->format('Y-m-d');

            $byProperty = [];
            $occupancyByProperty = [];
            $totalPropertyIncome = 0;

            $paymentsByProperty = [];
            foreach ($properties as $prop) {
                $amount = $incomeMap[$monthKey][$prop->id] ?? 0;
                $byProperty[$prop->id] = $amount;
                $totalPropertyIncome += $amount;
                $paymentsByProperty[$prop->id] = $paymentDetailsMap[$monthKey][$prop->id] ?? [];

                $totalU = (int) $prop->units_count;

                // Find all units occupied by leases in this specific month
                $occupiedUnitIds = [];
                foreach ($leaseRecords as $l) {
                    if ((int) $l->property_id !== (int) $prop->id) {
                        continue;
                    }

                    $lStart = substr((string) $l->start_date, 0, 10);
                    $lEnd = $l->end_date ? substr((string) $l->end_date, 0, 10) : null;
                    $lTerm = $l->termination_date ? substr((string) $l->termination_date, 0, 10) : null;
                    $effectiveEnd = $lTerm ?? $lEnd;

                    if ($lStart <= $mEndStr && ($effectiveEnd === null || $effectiveEnd >= $mStartStr)) {
                        $occupiedUnitIds[(int) $l->unit_id] = true;
                    }
                }

                $occupiedU = count($occupiedUnitIds);

                // For the current month, fallback to current snapshot if higher
                if ($monthKey === $currentMonthKey) {
                    $occupiedU = max($occupiedU, (int) $prop->occupied_units_count);
                }

                $rate = $totalU > 0 ? round(($occupiedU / $totalU) * 100) : 0;

                $occupancyByProperty[$prop->id] = [
                    'total_units' => $totalU,
                    'occupied_units' => $occupiedU,
                    'available_units' => max(0, $totalU - $occupiedU - (int) $prop->maintenance_units_count - (int) $prop->unavailable_units_count),
                    'occupancy_rate' => $rate,
                ];
            }

            $additionalIncomeTotal = $additionalIncomeMap[$monthKey] ?? 0;
            $additionalIncomesList = $additionalIncomeDetailsMap[$monthKey] ?? [];

            $months[] = [
                'month_key' => $monthKey,
                'month_name' => $monthName,
                'total_income' => $totalPropertyIncome + $additionalIncomeTotal,
                'property_income' => $totalPropertyIncome,
                'additional_income_total' => $additionalIncomeTotal,
                'additional_incomes' => $additionalIncomesList,
                'by_property' => $byProperty,
                'occupancy_by_property' => $occupancyByProperty,
                'payments_by_property' => $paymentsByProperty,
            ];

            $current = $current->startOfMonth()->addMonthNoOverflow()->startOfMonth();
        }

        return [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'months' => $months,
            'properties' => $properties->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'total_units' => $p->units_count,
            ])->values()->all(),
        ];
    }

    public function computeOccupancyReview(Collection $accessiblePropertyIds): array
    {
        $propIds = $accessiblePropertyIds->map(fn ($v) => is_object($v) ? $v->id : (is_array($v) ? $v['id'] : (int) $v))->values()->all();

        $properties = Property::query()
            ->whereIn('id', $propIds)
            ->withCount([
                'units',
                'units as occupied_units_count' => fn (Builder $q) => $q
                    ->where(function (Builder $q) {
                        $q->whereIn('status', [UnitStatus::Occupied->value, UnitStatus::Vendor->value])
                            ->orWhereHas('leases', fn (Builder $q) => $q->where('status', LeaseStatus::Active->value));
                    }),
                'units as maintenance_units_count' => fn (Builder $q) => $q
                    ->where('status', UnitStatus::Maintenance)
                    ->whereDoesntHave('leases', fn (Builder $q) => $q->where('status', LeaseStatus::Active->value)),
                'units as unavailable_units_count' => fn (Builder $q) => $q
                    ->where('status', UnitStatus::Unavailable)
                    ->whereDoesntHave('leases', fn (Builder $q) => $q->where('status', LeaseStatus::Active->value)),
            ])
            ->orderBy('name')
            ->get();

        $totalUnits = $properties->sum('units_count');
        $totalOccupied = $properties->sum('occupied_units_count');
        $totalMaintenance = $properties->sum('maintenance_units_count');
        $totalUnavailable = $properties->sum('unavailable_units_count');
        $totalAvailable = max(0, $totalUnits - $totalOccupied - $totalMaintenance - $totalUnavailable);
        $occupancyRate = $totalUnits > 0 ? round(($totalOccupied / $totalUnits) * 100) : 0;

        return [
            'total_units' => (int) $totalUnits,
            'occupied_units' => (int) $totalOccupied,
            'available_units' => (int) $totalAvailable,
            'maintenance_units' => (int) $totalMaintenance,
            'unavailable_units' => (int) $totalUnavailable,
            'occupancy_rate' => $occupancyRate,
            'property_reviews' => $properties->map(function (Property $p) {
                $total = (int) $p->units_count;
                $occupied = (int) $p->occupied_units_count;
                $maint = (int) $p->maintenance_units_count;
                $unavail = (int) $p->unavailable_units_count;
                $avail = max(0, $total - $occupied - $maint - $unavail);
                $rate = $total > 0 ? round(($occupied / $total) * 100) : 0;

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'total_units' => $total,
                    'occupied_units' => $occupied,
                    'available_units' => $avail,
                    'maintenance_units' => $maint,
                    'unavailable_units' => $unavail,
                    'occupancy_rate' => $rate,
                ];
            })->values()->all(),
            'insights' => [
                'occupancy_rate' => $occupancyRate,
                'total_units' => (int) $totalUnits,
            ],
        ];
    }
}
