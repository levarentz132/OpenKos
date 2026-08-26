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

        $revenueThisMonth = Payment::where('status', 'confirmed')
            ->whereHas('invoice', fn (Builder $q) => $q
                ->whereBetween('period_start', [$periodStart, $periodEnd])
                ->whereIn('lease_id', $leaseIds))
            ->sum('amount');

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

        $paymentRecords = $accessiblePropertyIds->isNotEmpty()
            ? DB::table('payments')
                ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
                ->join('leases', 'leases.id', '=', 'invoices.lease_id')
                ->join('units', 'units.id', '=', 'leases.unit_id')
                ->where('payments.status', PaymentStatus::Confirmed->value)
                ->whereBetween('payments.payment_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->whereIn('units.property_id', $accessiblePropertyIds)
                ->select(['units.property_id', 'payments.amount', 'payments.payment_date'])
                ->get()
            : collect();

        $incomeMap = [];
        foreach ($paymentRecords as $rec) {
            $monthKey = substr((string) $rec->payment_date, 0, 7);
            $propId = (int) $rec->property_id;
            $incomeMap[$monthKey][$propId] = ($incomeMap[$monthKey][$propId] ?? 0) + (int) $rec->amount;
        }

        $properties = Property::query()
            ->whereIn('id', $accessiblePropertyIds)
            ->withCount([
                'units',
                'units as occupied_units_count' => fn (Builder $q) => $q
                    ->where(function (Builder $q) {
                        $q->whereIn('status', [UnitStatus::Occupied, UnitStatus::Vendor])
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

        while ($current->lte($end)) {
            $monthKey = $current->format('Y-m');
            $monthName = $current->format('M Y');

            $byProperty = [];
            $occupancyByProperty = [];
            $totalIncome = 0;

            foreach ($properties as $prop) {
                $amount = $incomeMap[$monthKey][$prop->id] ?? 0;
                $byProperty[$prop->id] = $amount;
                $totalIncome += $amount;

                $totalU = (int) $prop->units_count;
                $occupiedU = (int) $prop->occupied_units_count;
                $rate = $totalU > 0 ? round(($occupiedU / $totalU) * 100) : 0;

                $occupancyByProperty[$prop->id] = [
                    'total_units' => $totalU,
                    'occupied_units' => $occupiedU,
                    'available_units' => max(0, $totalU - $occupiedU - (int) $prop->maintenance_units_count - (int) $prop->unavailable_units_count),
                    'occupancy_rate' => $rate,
                ];
            }

            $months[] = [
                'month_key' => $monthKey,
                'month_name' => $monthName,
                'total_income' => $totalIncome,
                'by_property' => $byProperty,
                'occupancy_by_property' => $occupancyByProperty,
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
                'occupied_units' => $p->occupied_units_count,
                'occupancy_rate' => $p->units_count > 0 ? round(($p->occupied_units_count / $p->units_count) * 100) : 0,
            ])->values()->toArray(),
        ];
    }

    public function computeOccupancyReview(Collection $propertiesCollection): array
    {
        $totalUnits = (int) $propertiesCollection->sum('units_count');
        $occupiedUnits = (int) $propertiesCollection->sum('occupied_units_count');
        $maintenanceUnits = (int) $propertiesCollection->sum('maintenance_units_count');
        $unavailableUnits = (int) $propertiesCollection->sum('unavailable_units_count');
        $availableUnits = max(0, $totalUnits - $occupiedUnits - $maintenanceUnits - $unavailableUnits);

        $overallRate = $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100) : 0;
        $vacancyRate = $totalUnits > 0 ? round(($availableUnits / $totalUnits) * 100) : 0;
        $maintenanceRate = $totalUnits > 0 ? round(($maintenanceUnits / $totalUnits) * 100) : 0;
        $unavailableRate = $totalUnits > 0 ? round(($unavailableUnits / $totalUnits) * 100) : 0;

        $propertyReviews = $propertiesCollection->map(function (Property $p) {
            $total = (int) $p->units_count;
            $occupied = (int) $p->occupied_units_count;
            $maintenance = (int) $p->maintenance_units_count;
            $unavailable = (int) $p->unavailable_units_count;
            $available = max(0, $total - $occupied - $maintenance - $unavailable);

            $rate = $total > 0 ? round(($occupied / $total) * 100) : 0;

            $statusLabel = match (true) {
                $total === 0 => 'No Units',
                $rate >= 90 => 'Full Occupancy',
                $rate >= 70 => 'High Occupancy',
                $rate >= 40 => 'Moderate Occupancy',
                default => 'Low Occupancy',
            };

            return [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'total_units' => $total,
                'occupied_units' => $occupied,
                'available_units' => $available,
                'maintenance_units' => $maintenance,
                'unavailable_units' => $unavailable,
                'occupancy_rate' => $rate,
                'status_label' => $statusLabel,
            ];
        })->values()->toArray();

        $sortedByRate = collect($propertyReviews)->sortByDesc('occupancy_rate')->values();
        $highest = $sortedByRate->first();
        $lowest = $sortedByRate->last();

        return [
            'total_units' => $totalUnits,
            'occupied_units' => $occupiedUnits,
            'available_units' => $availableUnits,
            'maintenance_units' => $maintenanceUnits,
            'unavailable_units' => $unavailableUnits,
            'occupancy_rate' => $overallRate,
            'vacancy_rate' => $vacancyRate,
            'maintenance_rate' => $maintenanceRate,
            'unavailable_rate' => $unavailableRate,
            'property_reviews' => $propertyReviews,
            'insights' => [
                'highest_occupancy' => ($highest && $totalUnits > 0) ? ['name' => $highest['name'], 'rate' => $highest['occupancy_rate']] : null,
                'lowest_occupancy' => ($lowest && $totalUnits > 0) ? ['name' => $lowest['name'], 'rate' => $lowest['occupancy_rate']] : null,
                'vacant_units_count' => $availableUnits,
            ],
        ];
    }
}
