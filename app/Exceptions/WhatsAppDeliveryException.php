<?php

namespace App\Exceptions;

use RuntimeException;

class WhatsAppDeliveryException extends RuntimeException
{
    public function __construct(
        public readonly string $driverId,
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function from(string $driverId, \Throwable $previous): self
    {
        return new self(
            $driverId,
            "WhatsApp delivery failed via driver [{$driverId}]: {$previous->getMessage()}",
            0,
            $previous
        );
    }
}
