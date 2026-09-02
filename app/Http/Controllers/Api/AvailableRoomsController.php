<?php

namespace App\Http\Controllers\Api;

use App\Enums\UnitStatus;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailableRoomsController extends Controller
{
    /**
     * Get concise properties data with available rooms, status in Indonesian, and price range.
     */
    public function index(Request $request): JsonResponse
    {
        $this->verifyApiAccess($request);

        $propertyId = $request->query('property_id');
        $propertySlug = $request->query('property_slug') ?: $request->query('property');
        $cityId = $request->query('city_id');
        $cityName = $request->query('city');
        $kecamatan = $request->query('kecamatan');
        $propertyType = $request->query('type');
        $search = $request->query('search');
        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');
        $onlyWithAvailableRooms = $request->boolean('only_available', false);

        $propertiesQuery = Property::query()
            ->where('is_active', true)
            ->when($propertyId, fn (Builder $q) => $q->where('id', $propertyId))
            ->when($propertySlug, fn (Builder $q) => $q->where('slug', $propertySlug))
            ->when($cityId, fn (Builder $q) => $q->where('city_id', $cityId))
            ->when($cityName, fn (Builder $q) => $q->whereHas('city', fn (Builder $q) => $q->where('name', 'like', "%{$cityName}%")))
            ->when($kecamatan, fn (Builder $q) => $q->where('kecamatan', 'like', "%{$kecamatan}%"))
            ->when($propertyType, fn (Builder $q) => $q->where('type', $propertyType))
            ->when($search, function (Builder $q) use ($search) {
                $q->where(function (Builder $sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('kecamatan', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('city', fn (Builder $c) => $c->where('name', 'like', "%{$search}%"));
                });
            })
            ->with([
                'units' => function ($q) use ($minPrice, $maxPrice, $search) {
                    $q->when($search, fn (Builder $sq) => $sq->where('name', 'like', "%{$search}%"))
                        ->when($minPrice || $maxPrice, function (Builder $rq) use ($minPrice, $maxPrice) {
                            $rq->whereHas('activeRates', function (Builder $subRate) use ($minPrice, $maxPrice) {
                                $subRate->when($minPrice, fn ($p) => $p->where('amount', '>=', (float) $minPrice))
                                    ->when($maxPrice, fn ($p) => $p->where('amount', '<=', (float) $maxPrice));
                            });
                        })
                        ->with('activeRates')
                        ->withExists(['leases as has_active_lease' => fn (Builder $lq) => $lq->where('status', 'active')])
                        ->orderBy('name');
                },
            ]);

        if ($onlyWithAvailableRooms) {
            $propertiesQuery->whereHas('units', fn (Builder $q) => $q
                ->where('status', UnitStatus::Available->value)
                ->whereDoesntHave('leases', fn (Builder $lq) => $lq->where('status', 'active'))
            );
        }

        $properties = $propertiesQuery->orderBy('name')->get();

        $data = $properties->map(function (Property $property) {
            $availableUnits = $property->units->filter(function (Unit $unit) {
                $isStatusAvailable = $unit->status === UnitStatus::Available
                    || (is_string($unit->status) && $unit->status === 'available');

                return $isStatusAvailable && empty($unit->has_active_lease);
            })->values();

            $availableRoomNames = $availableUnits->pluck('name')->values()->all();
            $availableCount = count($availableRoomNames);

            // Collect all active price rates (prefer available units, fallback to all units)
            $targetUnits = $availableUnits->isNotEmpty() ? $availableUnits : $property->units;
            $allPrices = $targetUnits
                ->flatMap(fn (Unit $unit) => $unit->activeRates->pluck('amount'))
                ->map(fn ($amt) => (float) $amt)
                ->filter(fn ($amt) => $amt > 0)
                ->values();

            $priceRange = null;
            if ($allPrices->isNotEmpty()) {
                $min = $allPrices->min();
                $max = $allPrices->max();

                if ($min === $max) {
                    $priceRange = 'Rp '.number_format($min, 0, ',', '.').'/bulan';
                } else {
                    $priceRange = 'Rp '.number_format($min, 0, ',', '.').' - Rp '.number_format($max, 0, ',', '.').'/bulan';
                }
            }

            $availabilityStatus = $availableCount > 0
                ? "Ready {$availableCount} kamar"
                : 'Kamar full';

            return [
                'name' => $property->name,
                'slug' => $property->slug,
                'description' => $property->description,
                'address_url' => $property->address_url,
                'kecamatan' => $property->kecamatan,
                'phone' => $property->phone,
                'image_url' => $property->image_url,
                'available_rooms' => $availableRoomNames,
                'availability_status' => $availabilityStatus,
                'price_range' => $priceRange,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get details and available rooms for a specific property.
     */
    public function forProperty(Request $request, Property $property): JsonResponse
    {
        $request->merge(['property_id' => $property->id, 'only_available' => false]);

        return $this->index($request);
    }

    private function verifyApiAccess(Request $request): void
    {
        $configuredSecret = config('services.openkos_api.secret', env('OPENKOS_API_SECRET', env('API_SECRET')));
        if (! $configuredSecret) {
            return;
        }

        $providedSecret = $request->header('X-API-Key')
            ?? $request->header('X-OpenKos-Secret')
            ?? $request->query('api_key')
            ?? $request->bearerToken();

        if ($providedSecret !== $configuredSecret) {
            abort(response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid API key or token.',
            ], 401));
        }
    }
}
