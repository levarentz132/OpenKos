<?php

namespace App\Listeners;

use App\Events\Maintenance\MaintenanceTicketCreated;
use App\Events\Maintenance\MaintenanceTicketUpdated;
use App\Models\MaintenanceTicket;
use App\Notifications\TenantPortalNotification;

class NotifyMaintenanceTenant
{
    public function handleCreated(MaintenanceTicketCreated $event): void
    {
        $this->notify($event->ticket, 'maintenance_created');
    }

    public function handleUpdated(MaintenanceTicketUpdated $event): void
    {
        $this->notify($event->ticket, 'maintenance_updated');
    }

    private function notify(MaintenanceTicket $ticket, string $type): void
    {
        $tenant = $ticket->creator?->tenant;
        if (! $tenant) {
            return;
        }

        $tenant->notify(new TenantPortalNotification([
            'type' => $type,
            'title' => __('Maintenance update'),
            'message' => $ticket->title,
            'url' => route('portal.maintenance-tickets.show', $ticket),
        ]));
    }
}
