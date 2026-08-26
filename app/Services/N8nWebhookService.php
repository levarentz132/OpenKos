<?php

namespace App\Services;

use App\Enums\UnitStatus;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class N8nWebhookService
{
    /**
     * Get the exact JSON payload that will be sent to n8n matching database columns.
     */
    public function getBulkPayload(): array
    {
        $units = Unit::query()
            ->where('status', UnitStatus::Available->value)
            ->with(['property:id,name,slug', 'activeRates'])
            ->get();

        $properties = Property::query()
            ->withCount([
                'units as available_units_count' => fn (Builder $q) => $q
                    ->where('status', UnitStatus::Available->value)
                    ->whereDoesntHave('leases', fn ($q) => $q->where('status', 'active')),
            ])
            ->get();

        $propertySummaries = $properties->map(function (Property $p) {
            $avail = (int) $p->available_units_count;
            $availText = $avail > 0 ? "Ready {$avail} kamar" : 'Belum ada kamar ready';
            $idCol = 'LOC_'.strtoupper(Str::slug($p->name, '_'));

            return [
                'id_col' => $idCol,
                'available_rooms' => $avail,
                'availability_text' => $availText,
            ];
        })->values()->toArray();

        return [
            'event' => 'vacant_rooms_bulk_sync',
            'timestamp' => now()->toIso8601String(),
            'count' => $units->count(),
            'property_summaries' => $propertySummaries,
            'vacant_rooms' => $units->map(fn (Unit $unit) => [
                'unit_id' => $unit->id,
                'unit_name' => $unit->name,
                'unit_slug' => $unit->slug,
                'floor' => $unit->floor,
                'capacity' => $unit->capacity,
                'status' => 'available',
                'property_id' => $unit->property_id,
                'property_name' => $unit->property?->name,
                'rates' => $unit->activeRates->map(fn ($r) => [
                    'amount' => (int) $r->amount,
                    'billing_unit' => $r->billing_unit,
                    'billing_interval' => $r->billing_interval,
                ])->values()->toArray(),
            ])->values()->toArray(),
        ];
    }

    /**
     * Push all currently vacant/empty rooms in OpenKos to n8n webhook URL.
     */
    public function pushAllVacantRooms(?string $targetWebhookUrl = null): array
    {
        $webhookUrl = $targetWebhookUrl ?: config('services.n8n.webhook_url', env('N8N_WEBHOOK_URL'));

        if (! $webhookUrl) {
            return [
                'status' => 'skipped',
                'message' => 'N8N_WEBHOOK_URL is not configured in .env or config.',
            ];
        }

        $payload = $this->getBulkPayload();

        return $this->sendWebhook($webhookUrl, $payload);
    }

    /**
     * Push a single room vacated event to n8n when a room becomes empty/available.
     */
    public function pushSingleVacantRoom(Unit $unit, string $event = 'room_vacated', ?string $targetWebhookUrl = null): array
    {
        $webhookUrl = $targetWebhookUrl ?: config('services.n8n.webhook_url', env('N8N_WEBHOOK_URL'));

        if (! $webhookUrl) {
            return [
                'status' => 'skipped',
                'message' => 'N8N_WEBHOOK_URL is not configured.',
            ];
        }

        $unit->loadMissing(['property', 'activeRates']);
        $property = $unit->property;

        $availableCount = $property
            ? Unit::where('property_id', $property->id)
                ->where('status', UnitStatus::Available->value)
                ->whereDoesntHave('leases', fn ($q) => $q->where('status', 'active'))
                ->count()
            : 0;

        $availText = $availableCount > 0 ? "Ready {$availableCount} kamar" : 'Belum ada kamar ready';
        $idCol = $property ? 'LOC_'.strtoupper(Str::slug($property->name, '_')) : '';

        $payload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'property_summary' => [
                'id_col' => $idCol,
                'available_rooms' => $availableCount,
                'availability_text' => $availText,
            ],
            'unit' => [
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
                ])->values()->toArray(),
            ],
        ];

        return $this->sendWebhook($webhookUrl, $payload);
    }

    private function sendWebhook(string $webhookUrl, array $payload): array
    {
        $secret = config('services.n8n.secret', env('N8N_SYNC_SECRET'));

        try {
            $request = Http::timeout(10)->asJson();
            if ($secret) {
                $request = $request->withHeaders([
                    'X-OpenKos-Secret' => $secret,
                ]);
            }

            $response = $request->post($webhookUrl, $payload);

            if ($response->successful()) {
                Log::info('N8n webhook push successful', ['url' => $webhookUrl, 'event' => $payload['event'] ?? '']);

                return [
                    'status' => 'success',
                    'http_code' => $response->status(),
                    'response' => $response->json() ?? $response->body(),
                ];
            }

            Log::warning('N8n webhook push returned error HTTP status', ['url' => $webhookUrl, 'status' => $response->status()]);

            return [
                'status' => 'error',
                'http_code' => $response->status(),
                'error' => $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::error('N8n webhook push failed exception', ['url' => $webhookUrl, 'error' => $e->getMessage()]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
