<?php

namespace App\Exceptions;

use RuntimeException;

class WhatsAppDriverNotFoundException extends RuntimeException
{
    public static function for(string $driverId): self
    {
        return new self("WhatsApp driver [{$driverId}] is not registered in NotificationRegistry.");
    }
}
