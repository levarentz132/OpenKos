<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class TenantPortalNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private array $data) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->data;
    }

    public function databaseType(object $notifiable): string
    {
        return (string) $this->data['type'];
    }
}
