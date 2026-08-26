<?php

namespace App\Http\Controllers\Api;

use App\Enums\UnitStatus;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class N8nRoomSyncController extends Controller
{
    /**
     * Get all vacant/empty rooms in OpenKos for n8n or external consumption.
     */
    public function getVacantRooms(Request $request): JsonResponse
    {
        $this->verifySecret($request);

        $propertyId = $request->query('property_id');

        $units = Unit::query()
            ->where('status', UnitStatus::Available->value)
            ->when($propertyId, fn ($q) => $q->where('property_id', $propertyId))
            ->with(['property:id,name,slug', 'activeRates'])
            ->get();

        $formatted = $units->map(fn (Unit $unit) => [
            'unit_id' => $unit->id,
            'unit_name' => $unit->name,
            'unit_slug' => $unit->slug,
            'floor' => $unit->floor,
            'capacity' => $unit->capacity,
            'status' => $unit->status->value ?? 'available',
            'property_id' => $unit->property_id,
            'property_name' => $unit->property?->name,
            'rates' => $unit->activeRates->map(fn ($r) => [
                'amount' => (int) $r->amount,
                'billing_unit' => $r->billing_unit,
                'billing_interval' => $r->billing_interval,
            ]),
        ]);

        $properties = Property::query()
            ->withCount([
                'units',
                'units as occupied_units_count' => fn ($q) => $q
                    ->where('status', UnitStatus::Occupied->value)
                    ->orWhereHas('leases', fn ($q) => $q->where('status', 'active')),
                'units as available_units_count' => fn ($q) => $q
                    ->where('status', UnitStatus::Available->value),
            ])
            ->when($propertyId, fn ($q) => $q->where('id', $propertyId))
            ->get();

        $propertySummaries = $properties->map(function (Property $p) {
            $avail = (int) $p->available_units_count;
            $availText = $avail > 0 ? "Ready {$avail} kamar" : 'Belum ada kamar ready';
            $idCol = 'LOC_'.strtoupper(\Illuminate\Support\Str::slug($p->name, '_'));

            return [
                'id_col' => $idCol,
                'property_id' => $p->id,
                'location_name' => $p->name,
                'display_title' => $p->name,
                'total_units' => (int) $p->units_count,
                'occupied_units' => (int) $p->occupied_units_count,
                'available_rooms' => $avail,
                'availability_text' => $availText,
            ];
        })->values()->toArray();

        return response()->json([
            'status' => 'success',
            'count' => $formatted->count(),
            'property_summaries' => $propertySummaries,
            'vacant_rooms' => $formatted,
        ]);
    }

    /**
     * Synchronize empty rooms into OpenKos from n8n / external DB payload.
     */
    public function syncEmptyRooms(Request $request): JsonResponse
    {
        $this->verifySecret($request);

        $validated = $request->validate([
            'property_id' => 'nullable|integer',
            'property_name' => 'nullable|string',
            'property_slug' => 'nullable|string',
            'rooms' => 'required|array',
            'rooms.*.name' => 'required|string',
            'rooms.*.floor' => 'nullable|string',
            'rooms.*.capacity' => 'nullable|integer',
            'rooms.*.status' => 'nullable|string',
        ]);

        $property = null;
        if (! empty($validated['property_id'])) {
            $property = Property::find($validated['property_id']);
        } elseif (! empty($validated['property_slug'])) {
            $property = Property::where('slug', $validated['property_slug'])->first();
        } elseif (! empty($validated['property_name'])) {
            $property = Property::where('name', $validated['property_name'])->first();
        }

        if (! $property) {
            $property = Property::first();
        }

        if (! $property) {
            return response()->json([
                'status' => 'error',
                'message' => 'No matching property found. Please create a property in OpenKos first or specify a valid property_id.',
            ], 422);
        }

        $synced = [];

        foreach ($validated['rooms'] as $roomData) {
            $statusStr = strtolower($roomData['status'] ?? 'available');
            $statusEnum = match ($statusStr) {
                'occupied' => UnitStatus::Occupied,
                'maintenance' => UnitStatus::Maintenance,
                'unavailable' => UnitStatus::Unavailable,
                default => UnitStatus::Available,
            };

            $unit = Unit::withTrashed()->updateOrCreate(
                [
                    'property_id' => $property->id,
                    'name' => $roomData['name'],
                ],
                [
                    'floor' => $roomData['floor'] ?? null,
                    'capacity' => $roomData['capacity'] ?? 1,
                    'status' => $statusEnum->value,
                    'deleted_at' => null,
                ]
            );

            $synced[] = [
                'id' => $unit->id,
                'name' => $unit->name,
                'status' => $unit->status->value,
                'property' => $property->name,
            ];
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Empty rooms synchronized successfully from n8n.',
            'synced_count' => count($synced),
            'synced_rooms' => $synced,
        ]);
    }

    /**
     * Manually trigger OpenKos to push all vacant rooms to n8n webhook.
     */
    public function triggerPushToN8n(Request $request, \App\Services\N8nWebhookService $service): JsonResponse
    {
        $this->verifySecret($request);

        $targetUrl = $request->input('webhook_url');
        $result = $service->pushAllVacantRooms($targetUrl);

        return response()->json($result);
    }

    private function verifySecret(Request $request): void
    {
        $configuredSecret = config('services.n8n.secret', env('N8N_SYNC_SECRET'));
        if (! $configuredSecret) {
            return;
        }

        $providedSecret = $request->header('X-OpenKos-Secret')
            ?? $request->header('X-N8N-Secret')
            ?? $request->query('secret')
            ?? $request->bearerToken();

        if ($providedSecret !== $configuredSecret) {
            abort(response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid sync secret header or token.',
            ], 401));
        }
    }
}
