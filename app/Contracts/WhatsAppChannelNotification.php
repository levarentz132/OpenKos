<?php

namespace App\Contracts;

use App\Data\WhatsApp\WhatsAppContent;

interface WhatsAppChannelNotification
{
    public function toWhatsAppChannel(object $notifiable): WhatsAppContent;
}
