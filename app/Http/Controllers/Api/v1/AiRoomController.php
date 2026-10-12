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
                        $rq->where(function (Builder $uq) use ($minPrice, $maxPrice) {
                            $uq->whereHas('activeRates', function (Builder $subRate) use ($minPrice, $maxPrice) {
                                $subRate->where('billing_unit', 'month')
                                    ->when($minPrice, fn ($p) => $p->where('amount', '>=', (float) $minPrice))
                                    ->when($maxPrice, fn ($p) => $p->where('amount', '<=', (float) $maxPrice));
                            })->orWhere(function (Builder $uq2) use ($minPrice, $maxPrice) {
                                $uq2->whereDoesntHave('activeRates', fn ($sub) => $sub->where('billing_unit', 'month'))
                                    ->whereHas('activeRates', function (Builder $subRate) use ($minPrice, $maxPrice) {
                                        $subRate->when($minPrice, fn ($p) => $p->where('amount', '>=', (float) $minPrice))
                                            ->when($maxPrice, fn ($p) => $p->where('amount', '<=', (float) $maxPrice));
                                    });
                            });
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
                ->map(fn (Unit $unit) => $unit->getMonthlyRate())
                ->filter(fn ($amt) => $amt !== null && $amt > 0)
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
                'direct_booking_url' => rtrim($frontendBase, '/') . "/property/{$property->id}-{$property->slug}?book=true",
                'booking_message_cta' => "Untuk melihat kamar dan langsung booking di {$property->name}, silakan buka link berikut: " . rtrim($frontendBase, '/') . "/property/{$property->id}-{$property->slug}?book=true",
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

        $targetUnits = $availableUnits->isNotEmpty() ? $availableUnits : $property->units;

        $allPrices = $targetUnits
            ->map(fn (Unit $unit) => $unit->getMonthlyRate())
            ->filter(fn ($amt) => $amt !== null && $amt > 0)
            ->values();

        $minPrice = $allPrices->isNotEmpty() ? $allPrices->min() : 1500000;
        $maxPrice = $allPrices->isNotEmpty() ? $allPrices->max() : 1500000;

        $priceDisplay = $minPrice === $maxPrice
            ? 'Rp ' . number_format($minPrice, 0, ',', '.') . '/bulan'
            : 'Rp ' . number_format($minPrice, 0, ',', '.') . ' - Rp ' . number_format($maxPrice, 0, ',', '.') . '/bulan';

        $coords = $this->resolveCoordinatesFromUrl($property->address_url, $property->id);
        $frontendBase = config('app.frontend_url', env('FRONTEND_URL', 'https://highlanderstay.com'));

        $allPhotos = $property->image_urls ?: ($property->image_url ? [$property->image_url] : []);

        $roomUnitsData = $availableUnits->map(function (Unit $unit) use ($allPhotos, $property, $frontendBase) {
            $monthlyRate = (float) ($unit->getMonthlyRate() ?: 1500000);

            return [
                'room_id' => $unit->id,
                'name' => $unit->name,
                'floor' => $unit->floor ? "Lantai {$unit->floor}" : null,
                'size_sqm' => $unit->size_sqm ? "{$unit->size_sqm} m²" : null,
                'bathroom' => 'Kamar Mandi Dalam',
                'monthly_rate' => $monthlyRate,
                'monthly_rate_display' => 'Rp ' . number_format($monthlyRate, 0, ',', '.') . ' / bulan',
                'rates' => $unit->activeRates->map(fn ($r) => [
                    'id' => $r->id,
                    'amount' => (float) $r->amount,
                    'billing_interval' => (int) $r->billing_interval,
                    'billing_unit' => $r->billing_unit instanceof \App\Enums\BillingUnit ? $r->billing_unit->value : (string) $r->billing_unit,
                ])->values()->all(),
                'photo_url' => $unit->image_url ?: ($allPhotos[0] ?? null),
                'video_url' => $unit->video_url ?: $property->video_url,
                'booking_url' => rtrim($frontendBase, '/') . "/property/{$property->id}-{$property->slug}?room=" . urlencode($unit->name) . "&book=true",
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
                'direct_booking_url' => rtrim($frontendBase, '/') . "/property/{$property->id}-{$property->slug}?book=true",
                'booking_message_cta' => "Untuk langsung melakukan booking kamar di {$property->name}, silakan klik link resmi kami berikut: " . rtrim($frontendBase, '/') . "/property/{$property->id}-{$property->slug}?book=true",
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
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'get_booking_link',
                        'description' => 'Mendapatkan link/tautan halaman booking properti Highlanderstay untuk dikirimkan ke pengguna saat pengguna ingin memesan atau booking kamar.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => [
                                    'type' => 'string',
                                    'description' => 'ID properti atau slug properti yang ingin dibooking (contoh: "4" atau "mangga")',
                                ],
                                'room' => [
                                    'type' => 'string',
                                    'description' => 'Nama atau nomor kamar spesifik jika pengguna sudah memilih unit tertentu (opsional).',
                                ],
                            ],
                            'required' => ['id'],
                        ],
                    ],
                    'endpoint' => url('/api/v1/ai/booking-link/{id}'),
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

            // Harga sewa khusus untuk kamar yang statusnya READY / AVAILABLE
            $availableRates = $availableUnits
                ->flatMap(fn (Unit $unit) => $unit->activeRates->pluck('amount'))
                ->map(fn ($amt) => (float) $amt)
                ->filter(fn ($amt) => $amt > 0)
                ->values();

            // Semua harga sewa katalog properti (fallback jika semua kamar di properti ini penuh)
            $allCatalogRates = $property->units
                ->flatMap(fn (Unit $unit) => $unit->activeRates->pluck('amount'))
                ->map(fn ($amt) => (float) $amt)
                ->filter(fn ($amt) => $amt > 0)
                ->values();

            // Untuk tampilan kartu properti: prioritaskan rate kamar yang available jika ada
            $displayRates = ($availCount > 0 && $availableRates->isNotEmpty()) ? $availableRates : $allCatalogRates;
            $propMinPrice = $displayRates->isNotEmpty() ? (int) $displayRates->min() : 1500000;
            $propMaxPrice = $displayRates->isNotEmpty() ? (int) $displayRates->max() : 1500000;

            if (! isset($areasGrouped[$areaName])) {
                $areasGrouped[$areaName] = [
                    'area' => $areaName,
                    'city' => $cityName,
                    'total_properties' => 0,
                    'total_available_rooms' => 0,
                    'available_prices' => [],
                    'all_catalog_prices' => [],
                    'properties' => [],
                ];
            }

            $areasGrouped[$areaName]['total_properties']++;
            $areasGrouped[$areaName]['total_available_rooms'] += $availCount;

            // WAJIB: Hanya masukkan harga ke available_prices jika properti memiliki kamar kosong (available_rooms > 0)
            if ($availCount > 0 && $availableRates->isNotEmpty()) {
                foreach ($availableRates as $rate) {
                    $areasGrouped[$areaName]['available_prices'][] = (int) $rate;
                }
            }

            if ($allCatalogRates->isNotEmpty()) {
                foreach ($allCatalogRates as $rate) {
                    $areasGrouped[$areaName]['all_catalog_prices'][] = (int) $rate;
                }
            }

            $areasGrouped[$areaName]['properties'][] = [
                'id' => $property->id,
                'name' => $property->name,
                'slug' => $property->slug,
                'type' => $property->type,
                'available_rooms_count' => $availCount,
                'status' => $availCount > 0 ? "Ready {$availCount} kamar" : 'Kamar full',
                'min_price_numeric' => $propMinPrice,
                'price_display' => $propMinPrice === $propMaxPrice
                    ? 'Rp ' . number_format($propMinPrice, 0, ',', '.') . '/bulan'
                    : 'Rp ' . number_format($propMinPrice, 0, ',', '.') . ' - Rp ' . number_format($propMaxPrice, 0, ',', '.') . '/bulan',
                'booking_url' => rtrim($frontendBase, '/') . "/property/{$property->id}-{$property->slug}",
                'direct_booking_url' => rtrim($frontendBase, '/') . "/property/{$property->id}-{$property->slug}?book=true",
            ];
        }

        // Kalkulasi harga mulai dari (min_price_numeric) per wilayah secara akurat:
        // HANYA ambil min(price) dari kamar/properti yang memiliki available_rooms > 0
        foreach ($areasGrouped as $areaName => &$areaData) {
            if ($areaData['total_available_rooms'] > 0 && ! empty($areaData['available_prices'])) {
                $minPriceNumeric = min($areaData['available_prices']);
                $priceStartsFrom = 'Rp ' . number_format($minPriceNumeric, 0, ',', '.') . '/bulan';
            } elseif (! empty($areaData['all_catalog_prices'])) {
                $minPriceNumeric = min($areaData['all_catalog_prices']);
                $priceStartsFrom = 'Rp ' . number_format($minPriceNumeric, 0, ',', '.') . '/bulan (full)';
            } else {
                $minPriceNumeric = 1500000;
                $priceStartsFrom = 'Rp 1.500.000/bulan';
            }

            $areaData['min_price_numeric'] = $minPriceNumeric;
            $areaData['price_starts_from'] = $priceStartsFrom;
            unset($areaData['available_prices'], $areaData['all_catalog_prices']);
        }
        unset($areaData);

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

    /**
     * Dedicated Booking Link Generator for AI Assistant:
     * Generates official frontend booking link, direct booking modal link, WhatsApp fallback, and ready-to-send AI text.
     */
    public function bookingLink(Request $request, string|int $id): JsonResponse
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
            ->with(['city'])
            ->first();

        if (! $property) {
            return response()->json([
                'success' => false,
                'error' => "Property '{$id}' not found.",
            ], 404);
        }

        $frontendBase = config('app.frontend_url', env('FRONTEND_URL', 'https://highlanderstay.com'));
        $roomParam = $request->query('room');

        $basePropertyUrl = rtrim($frontendBase, '/') . "/property/{$property->id}-{$property->slug}";

        $queryParams = ['book' => 'true'];
        if ($roomParam) {
            $queryParams['room'] = $roomParam;
        }
        $directBookingUrl = $basePropertyUrl . '?' . http_build_query($queryParams);

        $cleanPhone = preg_replace('/\D/', '', (string) $property->phone);
        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '62' . substr($cleanPhone, 1);
        }

        $waText = "Halo Pengelola, saya ingin booking kamar di {$property->name}" . ($roomParam ? " ({$roomParam})" : '') . ". Apakah masih tersedia?";
        $whatsappUrl = $cleanPhone ? "https://wa.me/{$cleanPhone}?text=" . urlencode($waText) : null;

        $roomText = $roomParam ? " tipe {$roomParam}" : '';
        $suggestedReply = "Baik kak! Untuk langsung memesan kamar{$roomText} di *{$property->name}*, silakan klik link resmi kami berikut:\n\n👉 {$directBookingUrl}\n\nDi halaman tersebut Kakak bisa langsung cek foto kamar, memilih unit, dan menyelesaikan pemesanan.";

        return response()->json([
            'success' => true,
            'property_id' => $property->id,
            'property_name' => $property->name,
            'property_page_url' => $basePropertyUrl,
            'direct_booking_url' => $directBookingUrl,
            'whatsapp_booking_url' => $whatsappUrl,
            'suggested_ai_message' => $suggestedReply,
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
