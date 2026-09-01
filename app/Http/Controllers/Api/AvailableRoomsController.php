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
     * Get all properties with their available rooms.
     */
    public function index(Request $request): JsonResponse
    {
        $this->verifyApiAccess($request);

        $propertyId = $request->query('property_id');
        $propertySlug = $request->query('property_slug') ?: $request->query('property');
        $cityId = $request->query('city_id');
        $cityName = $request->query('city');
        $propertyType = $request->query('type');
        $search = $request->query('search');
        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');
        $onlyWithAvailableRooms = $request->boolean('only_available', true);

        $propertiesQuery = Property::query()
            ->where('is_active', true)
            ->when($propertyId, fn (Builder $q) => $q->where('id', $propertyId))
            ->when($propertySlug, fn (Builder $q) => $q->where('slug', $propertySlug))
            ->when($cityId, fn (Builder $q) => $q->where('city_id', $cityId))
            ->when($cityName, fn (Builder $q) => $q->whereHas('city', fn (Builder $q) => $q->where('name', 'like', "%{$cityName}%")))
            ->when($propertyType, fn (Builder $q) => $q->where('type', $propertyType))
            ->when($search, function (Builder $q) use ($search) {
                $q->where(function (Builder $sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('city', fn (Builder $c) => $c->where('name', 'like', "%{$search}%"));
                });
            })
            ->with([
                'city:id,name',
                'region:id,name',
                'propertyType:slug,label',
                'units' => function ($q) use ($minPrice, $maxPrice, $search) {
                    $q->where('status', UnitStatus::Available->value)
                        ->whereDoesntHave('leases', fn (Builder $lq) => $lq->where('status', 'active'))
                        ->when($search, fn (Builder $sq) => $sq->where('name', 'like', "%{$search}%"))
                        ->when($minPrice || $maxPrice, function (Builder $rq) use ($minPrice, $maxPrice) {
                            $rq->whereHas('activeRates', function (Builder $subRate) use ($minPrice, $maxPrice) {
                                $subRate->when($minPrice, fn ($p) => $p->where('amount', '>=', (float) $minPrice))
                                    ->when($maxPrice, fn ($p) => $p->where('amount', '<=', (float) $maxPrice));
                            });
                        })
                        ->with('activeRates')
                        ->orderBy('name');
                },
            ])
            ->withCount([
                'units as total_units',
                'units as available_units_count' => fn (Builder $q) => $q
                    ->where('status', UnitStatus::Available->value)
                    ->whereDoesntHave('leases', fn (Builder $q) => $q->where('status', 'active')),
                'units as occupied_units_count' => fn (Builder $q) => $q
                    ->where('status', UnitStatus::Occupied->value)
                    ->orWhereHas('leases', fn (Builder $q) => $q->where('status', 'active')),
            ]);

        if ($onlyWithAvailableRooms) {
            $propertiesQuery->whereHas('units', fn (Builder $q) => $q
                ->where('status', UnitStatus::Available->value)
                ->whereDoesntHave('leases', fn (Builder $lq) => $lq->where('status', 'active'))
            );
        }

        $properties = $propertiesQuery->orderBy('name')->get();

        $allRooms = collect();

        $data = $properties->map(function (Property $property) use (&$allRooms) {
            $rooms = $property->units->map(function (Unit $unit) use ($property) {
                $primaryRate = $unit->activeRates->first();
                $primaryUnitStr = $primaryRate?->billing_unit instanceof \BackedEnum
                    ? $primaryRate->billing_unit->value
                    : (string) ($primaryRate->billing_unit ?? '');

                return [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'floor' => $unit->floor,
                    'price' => $primaryRate ? (float) $primaryRate->amount : null,
                    'price_formatted' => $primaryRate
                        ? 'Rp '.number_format((float) $primaryRate->amount, 0, ',', '.').($primaryUnitStr ? '/'.$primaryUnitStr : '')
                        : null,
                    'status' => $unit->status instanceof \BackedEnum ? $unit->status->value : (string) ($unit->status ?? 'available'),
                ];
            });

            foreach ($rooms as $r) {
                $allRooms->push([
                    'id' => $r['id'],
                    'name' => $r['name'],
                    'floor' => $r['floor'],
                    'price' => $r['price'],
                    'price_formatted' => $r['price_formatted'],
                    'status' => $r['status'],
                    'property_id' => $property->id,
                    'property_name' => $property->name,
                    'property_slug' => $property->slug,
                    'property_address' => $property->address,
                    'property_address_url' => $property->address_url,
                    'property_phone' => $property->phone,
                    'property_image_url' => $property->image_url,
                    'property_city' => $property->city?->name,
                ]);
            }

            return [
                'id' => $property->id,
                'name' => $property->name,
                'slug' => $property->slug,
                'type' => $property->type,
                'type_label' => $property->type_label,
                'description' => $property->description,
                'address' => $property->address,
                'address_url' => $property->address_url,
                'city' => $property->city?->name,
                'province' => $property->region?->name,
                'region' => $property->region?->name,
                'postal_code' => $property->postal_code,
                'phone' => $property->phone,
                'image' => $property->image,
                'image_url' => $property->image_url,
                'total_units' => (int) $property->total_units,
                'occupied_units' => (int) $property->occupied_units_count,
                'available_rooms_count' => (int) $property->available_units_count,
                'occupancy_rate' => $property->total_units > 0
                    ? round(((int) $property->occupied_units_count / (int) $property->total_units) * 100, 1)
                    : 0,
                'availability_status' => (int) $property->available_units_count > 0
                    ? "Ready {$property->available_units_count} room(s)"
                    : 'No rooms available',
                'available_rooms' => $rooms,
            ];
        });

        return response()->json([
            'success' => true,
            'total_properties' => $data->count(),
            'total_available_rooms' => $allRooms->count(),
            'properties' => $data,
        ]);
    }

    /**
     * Get property details with its available rooms for a specific property.
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
