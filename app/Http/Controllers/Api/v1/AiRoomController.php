<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\UnitStatus;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AiRoomController extends Controller
{
    /**
     * Level 1: Fast, token-efficient search & filtering designed for AI Assistants / LLMs.
     * Query parameters: q, location, area, min_price, max_price, only_available, type, limit, offset
     */
    public function search(Request $request): JsonResponse
    {
        $this->verifyApiAccess($request);

        $q = trim((string) $request->query('q', ''));
        $location = trim((string) ($request->query('location') ?: $request->query('area', '')));
        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');
        $propertyType = $request->query('type');
        $onlyAvailable = $request->boolean('only_available', true);
        $limit = min(50, max(1, (int) $request->query('limit', 10)));
        $offset = max(0, (int) $request->query('offset', 0));

        $propertiesQuery = Property::query()
            ->where('is_active', true)
            ->when($propertyType && $propertyType !== 'all', fn (Builder $query) => $query->where('type', $propertyType))
            ->when($location, function (Builder $query) use ($location) {
                $query->where(function (Builder $sub) use ($location) {
                    $sub->where('kecamatan', 'like', "%{$location}%")
                        ->orWhere('address', 'like', "%{$location}%")
                        ->orWhereHas('city', fn (Builder $c) => $c->where('name', 'like', "%{$location}%"));
                });
            })
            ->when($q, function (Builder $query) use ($q) {
                $query->where(function (Builder $sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('address', 'like', "%{$q}%")
                        ->orWhere('kecamatan', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%")
                        ->orWhereHas('units', fn (Builder $u) => $u->where('name', 'like', "%{$q}%"));
                });
            })
            ->with([
                'units' => function ($unitQuery) use ($minPrice, $maxPrice) {
                    $unitQuery->when($minPrice || $maxPrice, function (Builder $rq) use ($minPrice, $maxPrice) {
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

        if ($onlyAvailable) {
            $propertiesQuery->whereHas('units', fn (Builder $uq) => $uq
                ->where('status', UnitStatus::Available->value)
                ->whereDoesntHave('leases', fn (Builder $lq) => $lq->where('status', 'active'))
            );
        }

        $allProperties = $propertiesQuery->orderBy('name')->get();
        $totalCount = $allProperties->count();
        $paginatedProperties = $allProperties->slice($offset, $limit)->values();

        $frontendBase = config('app.frontend_url', env('FRONTEND_URL', 'https://highlanderstay.com'));

        $items = $paginatedProperties->map(function (Property $property) use ($frontendBase) {
            $availableUnits = $property->units->filter(function (Unit $unit) {
                $isStatusAvailable = $unit->status === UnitStatus::Available
                    || (is_string($unit->status) && $unit->status === 'available');

                return $isStatusAvailable && empty($unit->has_active_lease);
            })->values();

            $availableCount = $availableUnits->count();
            $targetUnits = $availableUnits->isNotEmpty() ? $availableUnits : $property->units;

            $allPrices = $targetUnits
                ->flatMap(fn (Unit $unit) => $unit->activeRates->pluck('amount'))
                ->map(fn ($amt) => (float) $amt)
                ->filter(fn ($amt) => $amt > 0)
                ->values();

            $minPrice = $allPrices->isNotEmpty() ? $allPrices->min() : 1500000;
            $maxPrice = $allPrices->isNotEmpty() ? $allPrices->max() : 1500000;

            $priceDisplay = $minPrice === $maxPrice
                ? 'Rp ' . number_format($minPrice, 0, ',', '.') . '/bulan'
                : 'Rp ' . number_format($minPrice, 0, ',', '.') . ' - Rp ' . number_format($maxPrice, 0, ',', '.') . '/bulan';

            $availabilityStatus = $availableCount > 0
                ? "Ready {$availableCount} kamar"
                : 'Kamar full';

            return [
                'id' => $property->id,
                'name' => $property->name,
                'slug' => $property->slug,
                'type' => $property->type,
                'category' => $property->type === 'apartment' ? 'Luxury Apartment' : 'Premium Boarding Room',
                'area' => $property->kecamatan ?: ($property->city?->name ?: 'Jakarta'),
                'address' => $property->address,
                'price_display' => $priceDisplay,
                'min_price_numeric' => (int) $minPrice,
                'available_rooms_count' => $availableCount,
                'availability_status' => $availabilityStatus,
                'thumbnail_url' => $property->image_url,
                'video_url' => $property->video_url,
                'google_maps_url' => $property->address_url,
                'booking_url' => rtrim($frontendBase, '/') . "/property/{$property->id}-{$property->slug}",
                'keeper_contact' => $property->phone,
                'detail_api_url' => url("/api/v1/ai/rooms/{$property->id}"),
            ];
        });

        return response()->json([
            'success' => true,
            'total_found' => $totalCount,
            'returned' => $items->count(),
            'limit' => $limit,
            'offset' => $offset,
            'query_params' => [
                'q' => $q ?: null,
                'location' => $location ?: null,
                'min_price' => $minPrice ? (float) $minPrice : null,
                'max_price' => $maxPrice ? (float) $maxPrice : null,
                'only_available' => $onlyAvailable,
                'type' => $propertyType ?: 'all',
            ],
            'data' => $items,
        ]);
    }

    /**
     * Level 2: Comprehensive details for a specific property & available rooms.
     * Lookup supported by ID or slug.
     */
    public function show(Request $request, string|int $id): JsonResponse
    {
        $this->verifyApiAccess($request);

        $property = Property::query()
            ->where('is_active', true)
            ->where(function (Builder $q) use ($id) {
                if (is_numeric($id)) {
                    $q->where('id', $id);
                } else {
                    $clean = strtolower(str_replace(['LOC_', '_'], ['', '-'], $id));
                    $q->where('slug', $id)->orWhere('slug', $clean);
                }
            })
            ->with([
                'units' => function ($unitQuery) {
                    $unitQuery->with('activeRates')
                        ->withExists(['leases as has_active_lease' => fn (Builder $lq) => $lq->where('status', 'active')])
                        ->orderBy('name');
                },
                'city',
            ])
            ->first();

        if (! $property) {
            return response()->json([
                'success' => false,
                'error' => "Property or room '{$id}' not found.",
            ], 404);
        }

        $availableUnits = $property->units->filter(function (Unit $unit) {
            $isStatusAvailable = $unit->status === UnitStatus::Available
                || (is_string($unit->status) && $unit->status === 'available');

            return $isStatusAvailable && empty($unit->has_active_lease);
        })->values();

        $allPrices = $property->units
            ->flatMap(fn (Unit $unit) => $unit->activeRates->pluck('amount'))
            ->map(fn ($amt) => (float) $amt)
            ->filter(fn ($amt) => $amt > 0)
            ->values();

        $minPrice = $allPrices->isNotEmpty() ? $allPrices->min() : 1500000;
        $maxPrice = $allPrices->isNotEmpty() ? $allPrices->max() : 1500000;

        $priceDisplay = $minPrice === $maxPrice
            ? 'Rp ' . number_format($minPrice, 0, ',', '.') . '/bulan'
            : 'Rp ' . number_format($minPrice, 0, ',', '.') . ' - Rp ' . number_format($maxPrice, 0, ',', '.') . '/bulan';

        $coords = $this->resolveCoordinatesFromUrl($property->address_url, $property->id);
        $frontendBase = config('app.frontend_url', env('FRONTEND_URL', 'https://highlanderstay.com'));

        $allPhotos = $property->image_urls ?: ($property->image_url ? [$property->image_url] : []);

        $roomUnitsData = $availableUnits->map(function (Unit $unit) use ($allPhotos, $property) {
            $monthlyRate = (float) ($unit->activeRates->first()?->amount ?: 1500000);

            return [
                'room_id' => $unit->id,
                'name' => $unit->name,
                'floor' => $unit->floor ? "Lantai {$unit->floor}" : null,
                'size_sqm' => $unit->size_sqm ? "{$unit->size_sqm} m²" : null,
                'bathroom' => 'Kamar Mandi Dalam',
                'monthly_rate' => $monthlyRate,
                'monthly_rate_display' => 'Rp ' . number_format($monthlyRate, 0, ',', '.') . ' / bulan',
                'photo_url' => $unit->image_url ?: ($allPhotos[0] ?? null),
                'video_url' => $unit->video_url ?: $property->video_url,
            ];
        })->values()->all();

        $cleanPhone = preg_replace('/\D/', '', (string) $property->phone);
        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '62' . substr($cleanPhone, 1);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $property->id,
                'name' => $property->name,
                'slug' => $property->slug,
                'type' => $property->type,
                'category' => $property->type === 'apartment' ? 'Luxury Apartment' : 'Premium Boarding Room',
                'description' => $property->description ?: '',
                'address' => $property->address,
                'area' => $property->kecamatan ?: ($property->city?->name ?: 'Jakarta'),
                'coordinates' => [
                    'latitude' => $coords['lat'] ?? null,
                    'longitude' => $coords['lng'] ?? null,
                ],
                'google_maps_url' => $property->address_url,
                'price_display' => $priceDisplay,
                'min_monthly_price' => (int) $minPrice,
                'deposit_amount' => (int) ($property->deposit_amount ?: 500000),
                'deposit_display' => 'Rp ' . number_format((float) ($property->deposit_amount ?: 500000), 0, ',', '.'),
                'availability_status' => $availableUnits->count() > 0 ? "Ready {$availableUnits->count()} kamar" : 'Kamar full',
                'available_rooms_count' => $availableUnits->count(),
                'keeper_phone' => $property->phone,
                'keeper_whatsapp_url' => $cleanPhone ? "https://wa.me/{$cleanPhone}" : null,
                'booking_url' => rtrim($frontendBase, '/') . "/property/{$property->id}-{$property->slug}",
                'facilities' => [
                    'AC Dingin',
                    'WiFi Fiber Cepat',
                    'Kamar Mandi Dalam',
                    'Kasur Springbed & Lemari Pakaian',
                    'Parkir Motor / Mobil',
                    'Air & Listrik Terjamin',
                ],
                'media' => [
                    'photos' => $allPhotos,
                    'video_tour' => $property->video_url,
                ],
                'available_rooms' => $roomUnitsData,
            ],
        ]);
    }

    /**
     * Level 3: OpenAPI / Function Calling JSON Schema for LangChain, OpenAI, Gemini, n8n.
     */
    public function schema(): JsonResponse
    {
        return response()->json([
            'title' => 'Highlanderstay OpenKos AI Room Search Tools',
            'description' => 'Official API Tools for AI Assistants (OpenAI, Gemini, LangChain, n8n) to search and retrieve rooms directly from OpenKos Backend.',
            'tools' => [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'search_rooms',
                        'description' => 'Mencari dan memfilter kamar kos/apartemen Highlanderstay berdasarkan lokasi/kampus, rentang harga, atau kata kunci.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'q' => [
                                    'type' => 'string',
                                    'description' => 'Kata kunci pencarian (nama kos, kampus seperti UNTAR / Trisakti / Binus, atau nama mall)',
                                ],
                                'location' => [
                                    'type' => 'string',
                                    'description' => 'Nama wilayah atau kecamatan (contoh: Kebon Jeruk, Grogol, Kemayoran, Tangerang, Palembang)',
                                ],
                                'min_price' => [
                                    'type' => 'number',
                                    'description' => 'Harga sewa minimum bulanan (contoh: 1500000)',
                                ],
                                'max_price' => [
                                    'type' => 'number',
                                    'description' => 'Harga sewa maksimum bulanan (contoh: 3000000)',
                                ],
                                'only_available' => [
                                    'type' => 'boolean',
                                    'description' => 'Hanya tampilkan unit yang masih memiliki kamar kosong (default: true)',
                                ],
                                'type' => [
                                    'type' => 'string',
                                    'enum' => ['all', 'kos', 'apartment'],
                                    'description' => 'Tipe akomodasi: "kos" atau "apartment"',
                                ],
                                'limit' => [
                                    'type' => 'integer',
                                    'description' => 'Jumlah rekomendasi (default: 5)',
                                ],
                            ],
                        ],
                    ],
                    'endpoint' => url('/api/v1/ai/search'),
                ],
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'get_room_details',
                        'description' => 'Mengambil informasi lengkap properti dan kamar (semua foto HD, link video walkthrough, rute Google Maps, fasilitas, dan detail tipe kamar).',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => [
                                    'type' => 'string',
                                    'description' => 'ID properti atau slug properti dari hasil search_rooms',
                                ],
                            ],
                            'required' => ['id'],
                        ],
                    ],
                    'endpoint' => url('/api/v1/ai/rooms/{id}'),
                ],
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'get_available_locations',
                        'description' => 'Mendapatkan ringkasan seluruh wilayah/lokasi properti Highlanderstay beserta jumlah kamar kosong dan range harga awal untuk menjawab pertanyaan user seperti "mau cari kamar kosong, lokasinya ada di mana saja" atau "lokasi apa saja yang tersedia".',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'only_available' => [
                                    'type' => 'boolean',
                                    'description' => 'Set true jika user menanyakan lokasi yang memiliki kamar kosong saja (default: true).',
                                ],
                                'type' => [
                                    'type' => 'string',
                                    'enum' => ['all', 'kos', 'apartment'],
                                    'description' => 'Tipe akomodasi: "kos" atau "apartment" (default: all)',
                                ],
                            ],
                        ],
                    ],
                    'endpoint' => url('/api/v1/ai/locations'),
                ],
            ],
        ]);
    }

    /**
     * Locations & Coverage Overview:
     * Returns all active areas, how many units/rooms are available, price range, and an AI-friendly summary text.
     */
    public function locations(Request $request): JsonResponse
    {
        $this->verifyApiAccess($request);

        $onlyAvailable = $request->boolean('only_available', false);
        $type = $request->query('type', 'all');

        $query = Property::query()
            ->where('is_active', true);

        if ($type === 'kos') {
            $query->where(fn (Builder $q) => $q->whereNull('type')->orWhere('type', 'kos'));
        } elseif ($type === 'apartment') {
            $query->where('type', 'apartment');
        }

        if ($request->filled('city')) {
            $cityParam = $request->query('city');
            $query->whereHas('city', fn (Builder $cq) => $cq->where('name', 'like', "%{$cityParam}%"));
        }

        $properties = $query
            ->with([
                'units' => function ($unitQuery) {
                    $unitQuery->with('activeRates')
                        ->withExists(['leases as has_active_lease' => fn (Builder $lq) => $lq->where('status', 'active')])
                        ->orderBy('name');
                },
                'city',
            ])
            ->orderBy('name')
            ->get();

        $areasGrouped = [];
        $totalAvailableRoomsGlobal = 0;
        $frontendBase = config('app.frontend_url', env('FRONTEND_URL', 'https://highlanderstay.com'));

        foreach ($properties as $property) {
            $areaName = trim((string) ($property->kecamatan ?: ($property->city?->name ?: 'Jakarta Barat')));
            $cityName = $property->city?->name ?: 'Jakarta';

            $availableUnits = $property->units->filter(function (Unit $unit) {
                $isStatusAvailable = $unit->status === UnitStatus::Available
                    || (is_string($unit->status) && $unit->status === 'available');

                return $isStatusAvailable && empty($unit->has_active_lease);
            })->values();

            $availCount = $availableUnits->count();
            $totalAvailableRoomsGlobal += $availCount;

            $allPrices = $property->units
                ->flatMap(fn (Unit $unit) => $unit->activeRates->pluck('amount'))
                ->map(fn ($amt) => (float) $amt)
                ->filter(fn ($amt) => $amt > 0)
                ->values();

            $minPrice = $allPrices->isNotEmpty() ? (int) $allPrices->min() : 1500000;
            $maxPrice = $allPrices->isNotEmpty() ? (int) $allPrices->max() : 1500000;

            if (! isset($areasGrouped[$areaName])) {
                $areasGrouped[$areaName] = [
                    'area' => $areaName,
                    'city' => $cityName,
                    'total_properties' => 0,
                    'total_available_rooms' => 0,
                    'min_price_numeric' => $minPrice,
                    'price_starts_from' => 'Rp ' . number_format($minPrice, 0, ',', '.') . '/bulan',
                    'properties' => [],
                ];
            }

            $areasGrouped[$areaName]['total_properties']++;
            $areasGrouped[$areaName]['total_available_rooms'] += $availCount;
            if ($minPrice < $areasGrouped[$areaName]['min_price_numeric']) {
                $areasGrouped[$areaName]['min_price_numeric'] = $minPrice;
                $areasGrouped[$areaName]['price_starts_from'] = 'Rp ' . number_format($minPrice, 0, ',', '.') . '/bulan';
            }

            $areasGrouped[$areaName]['properties'][] = [
                'id' => $property->id,
                'name' => $property->name,
                'slug' => $property->slug,
                'type' => $property->type,
                'available_rooms_count' => $availCount,
                'status' => $availCount > 0 ? "Ready {$availCount} kamar" : 'Kamar full',
                'price_display' => $minPrice === $maxPrice
                    ? 'Rp ' . number_format($minPrice, 0, ',', '.') . '/bulan'
                    : 'Rp ' . number_format($minPrice, 0, ',', '.') . ' - Rp ' . number_format($maxPrice, 0, ',', '.') . '/bulan',
                'booking_url' => rtrim($frontendBase, '/') . "/property/{$property->id}-{$property->slug}",
            ];
        }

        $areaList = array_values($areasGrouped);

        // Separate areas with available rooms vs full
        $areasWithVacant = array_values(array_filter($areaList, fn ($a) => $a['total_available_rooms'] > 0));

        // Generate natural conversational answer suggestion for AI
        $vacantAreaSummaries = array_map(
            fn ($a) => "{$a['area']} ({$a['total_available_rooms']} kamar ready, mulai {$a['price_starts_from']})",
            $areasWithVacant
        );

        if (! empty($vacantAreaSummaries)) {
            $aiAnswerSuggestion = 'Saat ini Highlanderstay menyediakan kamar kosong di area: '
                . implode(', ', $vacantAreaSummaries)
                . '. Kakak berminat cari kamar di area mana atau dekat kampus/kantor apa?';
        } else {
            $aiAnswerSuggestion = 'Mohon maaf, saat ini seluruh kamar di properti Highlanderstay sedang terisi penuh.';
        }

        $summaryParts = [];
        foreach ($areaList as $item) {
            $statusStr = $item['total_available_rooms'] > 0
                ? "{$item['total_available_rooms']} kamar ready"
                : 'kamar penuh';
            $summaryParts[] = "{$item['area']} ({$item['total_properties']} properti, {$statusStr}, mulai {$item['price_starts_from']})";
        }
        $aiTextSummary = 'Cakupan wilayah Highlanderstay: ' . implode('; ', $summaryParts) . '.';

        $outputAreas = $onlyAvailable ? $areasWithVacant : $areaList;
        if ($onlyAvailable) {
            foreach ($outputAreas as &$areaItem) {
                $areaItem['properties'] = array_values(array_filter(
                    $areaItem['properties'],
                    fn ($p) => $p['available_rooms_count'] > 0
                ));
            }
            unset($areaItem);
        }

        return response()->json([
            'success' => true,
            'total_active_properties' => $properties->count(),
            'total_available_rooms' => $totalAvailableRoomsGlobal,
            'available_areas_with_vacant_rooms' => array_column($areasWithVacant, 'area'),
            'all_coverage_areas' => array_keys($areasGrouped),
            'ai_answer_suggestion' => $aiAnswerSuggestion,
            'ai_summary_text' => $aiTextSummary,
            'areas' => $outputAreas,
        ]);
    }

    private function resolveCoordinatesFromUrl(?string $url, int|string $propertyId): ?array
    {
        if (empty($url)) {
            return null;
        }

        return Cache::remember('prop_coords_'.$propertyId.'_'.md5($url), 86400 * 30, function () use ($url) {
            if (preg_match('/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $url, $m)) {
                return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
            }
            if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $m)) {
                return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
            }
            if (preg_match('/[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $m)) {
                return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
            }

            return null;
        });
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

        // If client provides key, enforce validation; if key is provided and wrong, reject.
        if ($providedSecret && $providedSecret !== $configuredSecret) {
            abort(response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid API key or secret.',
            ], 401));
        }
    }
}
