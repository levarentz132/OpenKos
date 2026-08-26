<?php

namespace App\Http\Controllers;

use App\Business\Dashboard\OverviewStatsCalculator;
use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __invoke(Request $request, OverviewStatsCalculator $calculator): Response
    {
        $startDateParam = $request->query('start_date');
        $endDateParam = $request->query('end_date');
        $selectedPropertyId = $request->query('property_id');

        $startDate = $startDateParam ? Carbon::parse($startDateParam)->startOfMonth() : now()->subMonths(5)->startOfMonth();
        $endDate = $endDateParam ? Carbon::parse($endDateParam)->endOfMonth() : now()->endOfMonth();

        $accessiblePropertiesQuery = Property::query()
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ));

        $accessibleProperties = (clone $accessiblePropertiesQuery)->pluck('id');

        $incomeReport = $calculator->computeMonthlyPropertyIncome(
            $accessibleProperties,
            $startDate,
            $endDate
        );

        $propertiesList = Property::query()
            ->whereIn('id', $accessibleProperties)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $occupancyReview = $calculator->computeOccupancyReview(
            Property::query()
                ->whereIn('id', $accessibleProperties)
                ->withCount([
                    'units',
                    'units as occupied_units_count' => fn (Builder $q) => $q
                        ->where(function (Builder $q) {
                            $q->where('status', UnitStatus::Occupied)
                                ->orWhereHas('leases', fn (Builder $q) => $q->where('status', LeaseStatus::Active->value));
                        }),
                    'units as maintenance_units_count' => fn (Builder $q) => $q
                        ->where('status', UnitStatus::Maintenance)
                        ->whereDoesntHave('leases', fn (Builder $q) => $q->where('status', LeaseStatus::Active->value)),
                    'units as unavailable_units_count' => fn (Builder $q) => $q
                        ->where('status', UnitStatus::Unavailable)
                        ->whereDoesntHave('leases', fn (Builder $q) => $q->where('status', LeaseStatus::Active->value)),
                ])
                ->get(['id', 'name', 'slug'])
        );

        return Inertia::render('reports/index', [
            'income_report' => $incomeReport,
            'occupancy_review' => $occupancyReview,
            'properties' => $propertiesList,
            'filters' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'property_id' => $selectedPropertyId ?? 'all',
            ],
        ]);
    }
}
