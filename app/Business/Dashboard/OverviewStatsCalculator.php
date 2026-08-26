<?php

namespace App\Business\Dashboard;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use Carbon\Carbon;
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

    public function computeMonthlyPropertyIncome(Collection $accessiblePropertyIds, int $monthsCount = 6): array
    {
        $months = [];
        $startDate = now()->subMonths($monthsCount - 1)->startOfMonth();
        $endDate = now()->endOfMonth();

        $payments = Payment::query()
            ->where('status', PaymentStatus::Confirmed->value)
            ->whereBetween('payment_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->whereHas('invoice.lease.unit', fn (Builder $q) => $q->whereIn('property_id', $accessiblePropertyIds))
            ->with(['invoice.lease.unit' => fn ($q) => $q->select('id', 'property_id')])
            ->get(['id', 'invoice_id', 'amount', 'payment_date']);

        $properties = Property::query()
            ->whereIn('id', $accessiblePropertyIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        for ($i = $monthsCount - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthKey = $date->format('Y-m');
            $monthName = $date->format('M Y');

            $byProperty = [];
            foreach ($properties as $prop) {
                $byProperty[$prop->id] = 0;
            }

            $totalIncome = 0;

            foreach ($payments as $payment) {
                if ($payment->payment_date && $payment->payment_date->format('Y-m') === $monthKey) {
                    $propId = $payment->invoice?->lease?->unit?->property_id;
                    if ($propId && isset($byProperty[$propId])) {
                        $amount = (int) $payment->amount;
                        $byProperty[$propId] += $amount;
                        $totalIncome += $amount;
                    }
                }
            }

            $months[] = [
                'month_key' => $monthKey,
                'month_name' => $monthName,
                'total_income' => $totalIncome,
                'by_property' => $byProperty,
            ];
        }

        return [
            'months' => $months,
            'properties' => $properties->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()->toArray(),
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
