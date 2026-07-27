<?php

namespace App\Exceptions;

use RuntimeException;

class MailDriverNotFoundException extends RuntimeException
{
    public static function for(string $name): self
    {
        return new self("Mail driver [{$name}] is not registered in NotificationRegistry.");
    }
}
