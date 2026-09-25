<?php

namespace App\Http\Controllers\Api;

use App\Enums\UnitStatus;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            ->when($propertySlug, function (Builder $q) use ($propertySlug) {
                $clean = strtolower(str_replace(['LOC_', '_'], ['', '-'], $propertySlug));
                $nameSearch = str_replace('-', ' ', $clean);
                $q->where(function (Builder $sub) use ($propertySlug, $clean, $nameSearch) {
                    $sub->where('slug', $propertySlug)
                        ->orWhere('slug', $clean)
                        ->orWhere('name', 'like', "%{$nameSearch}%");
                });
            })
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

            $availableRoomsData = $availableUnits->map(function (Unit $unit) {
                $rate = $unit->activeRates->first()?->amount;

                return [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'slug' => $unit->slug,
                    'floor' => $unit->floor,
                    'capacity' => $unit->capacity,
                    'size_sqm' => $unit->size_sqm ? (float) $unit->size_sqm : null,
                    'monthly_rate' => $rate ? (float) $rate : null,
                    'image_url' => $unit->image_url,
                    'video_url' => $unit->video_url,
                ];
            })->values()->all();

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

            $canonicalSlug = Str::slug($property->name);
            $coords = $this->resolveCoordinatesFromUrl($property->address_url, $property->id);

            return [
                'name' => $property->name,
                'slug' => $property->slug,
                'canonical_slug' => $canonicalSlug,
                'canonical_id' => $canonicalId,
                'description' => $property->description,
                'address' => $property->address,
                'address_url' => $property->address_url,
                'latitude' => $coords['lat'] ?? null,
                'longitude' => $coords['lng'] ?? null,
                'kecamatan' => $property->kecamatan,
                'phone' => $property->phone,
                'image_url' => $property->image_url,
                'image_urls' => $property->image_urls,
                'video_url' => $property->video_url,
                'available_rooms' => $availableRoomNames,
                'available_room_details' => $availableRoomsData,
                'availability_status' => $availabilityStatus,
                'price_range' => $priceRange,
                'deposit_amount' => (float) (($property->deposit_amount && $property->deposit_amount > 0) ? $property->deposit_amount : 500000),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Resolve latitude and longitude automatically from Google Maps URL (supporting short links with cache).
     */
    private function resolveCoordinatesFromUrl(?string $url, int|string $propertyId): ?array
    {
        if (empty($url)) {
            return null;
        }

        return \Illuminate\Support\Facades\Cache::remember('prop_coords_'.$propertyId.'_'.md5($url), 86400 * 30, function () use ($url) {
            // 1. Direct regex check (!3d, @, q=)
            if (preg_match('/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $url, $m)) {
                return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
            }
            if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $m)) {
                return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
            }
            if (preg_match('/[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $m)) {
                return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
            }

            // 2. If short link (maps.app.goo.gl / goo.gl), resolve HTTP redirect
            if (str_contains($url, 'maps.app.goo.gl') || str_contains($url, 'goo.gl')) {
                try {
                    $client = new \GuzzleHttp\Client(['allow_redirects' => false, 'timeout' => 3]);
                    $res = $client->get($url);
                    $location = $res->getHeaderLine('Location');
                    if ($location) {
                        if (preg_match('/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $location, $m)) {
                            return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
                        }
                        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $location, $m)) {
                            return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore network error
                }
            }

            return null;
        });
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
