<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidWhatsAppDriverException extends RuntimeException
{
    public static function for(string $driverId, string $class): self
    {
        return new self("WhatsApp driver [{$driverId}] class [{$class}] must implement App\\Contracts\\WhatsAppDriver.");
    }
}
