<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidMailDriverException extends RuntimeException
{
    public static function for(string $name, string $class): self
    {
        return new self("Mail driver [{$name}] using class [{$class}] must implement App\\Contracts\\MailDriver.");
    }
}
