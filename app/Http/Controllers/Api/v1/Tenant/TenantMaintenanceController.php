<?php

namespace App\Http\Controllers\Api\v1\Tenant;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Http\Requests\Api\Tenant\StoreMaintenanceTicketRequest;
use App\Models\MaintenanceTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantMaintenanceController extends TenantBaseController
{
    /**
     * List all maintenance tickets created by the authenticated tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $tickets = MaintenanceTicket::query()
            ->where('created_by', $user->id)
            ->with(['property:id,name', 'unit:id,name'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'tickets' => $tickets->through(fn (MaintenanceTicket $ticket) => [
                'id' => $ticket->id,
                'reference' => $ticket->reference,
                'title' => $ticket->title,
                'description' => $ticket->description,
                'status' => $ticket->status->value,
                'priority' => $ticket->priority->value,
                'location' => $ticket->location,
                'property_name' => $ticket->property?->name,
                'unit_name' => $ticket->unit?->name,
                'created_at' => $ticket->created_at->toIso8601String(),
                'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Submit a new maintenance ticket.
     */
    public function store(StoreMaintenanceTicketRequest $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $this->requireTenant($request);
        $validated = $request->validated();

        // Infer property_id and unit_id from tenant's active lease if not explicitly supplied
        $propertyId = $validated['property_id'] ?? null;
        $unitId = $validated['unit_id'] ?? null;

        if (! $propertyId || ! $unitId) {
            $activeLease = $tenant->leases()->active()->with('unit')->latest('start_date')->first();
            if ($activeLease && $activeLease->unit) {
                $propertyId ??= $activeLease->unit->property_id;
                $unitId ??= $activeLease->unit->id;
            }
        }

        $priority = ! empty($validated['priority'])
            ? MaintenancePriority::from($validated['priority'])
            : MaintenancePriority::Medium;

        $ticket = MaintenanceTicket::create([
            'property_id' => $propertyId,
            'unit_id' => $unitId,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'location' => $validated['location'] ?? null,
            'status' => MaintenanceStatus::Reported,
            'priority' => $priority,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Maintenance ticket created successfully.',
            'ticket' => [
                'id' => $ticket->id,
                'reference' => $ticket->reference,
                'title' => $ticket->title,
                'status' => $ticket->status->value,
                'priority' => $ticket->priority->value,
                'created_at' => $ticket->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Show details of a specific maintenance ticket.
     */
    public function show(Request $request, MaintenanceTicket $ticket): JsonResponse
    {
        $user = $request->user();

        // Security check: tenant must own this ticket
        abort_unless($ticket->created_by === $user->id, 404);

        $ticket->load(['property:id,name', 'unit:id,name']);

        return response()->json([
            'ticket' => [
                'id' => $ticket->id,
                'reference' => $ticket->reference,
                'title' => $ticket->title,
                'description' => $ticket->description,
                'status' => $ticket->status->value,
                'priority' => $ticket->priority->value,
                'location' => $ticket->location,
                'property' => $ticket->property ? [
                    'id' => $ticket->property->id,
                    'name' => $ticket->property->name,
                ] : null,
                'unit' => $ticket->unit ? [
                    'id' => $ticket->unit->id,
                    'name' => $ticket->unit->name,
                ] : null,
                'cost' => (float) $ticket->cost,
                'resolution_notes' => $ticket->resolution_notes,
                'resolved_at' => $ticket->resolved_at?->toIso8601String(),
                'created_at' => $ticket->created_at->toIso8601String(),
            ],
        ]);
    }
}
