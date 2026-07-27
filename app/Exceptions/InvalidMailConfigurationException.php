<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidMailConfigurationException extends RuntimeException
{
    public static function missing(string $key): self
    {
        return new self("SMTP mail configuration is missing required field [{$key}].");
    }

    public static function invalid(string $key, string $reason): self
    {
        return new self("SMTP mail configuration field [{$key}] is invalid. {$reason}");
    }
}
