<?php

namespace App\Exceptions;

use InvalidArgumentException;

class InvalidMailAddressException extends InvalidArgumentException
{
    public static function for(string $address): self
    {
        return new self("Invalid email address [{$address}].");
    }
}
