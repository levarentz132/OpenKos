<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class MailDeliveryException extends RuntimeException
{
    public static function from(string $driver, Throwable $previous): self
    {
        return new self("Failed to send mail via driver [{$driver}]: {$previous->getMessage()}", 0, $previous);
    }
}
