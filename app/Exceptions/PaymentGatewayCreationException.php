<?php

namespace App\Exceptions;

use RuntimeException;

final class PaymentGatewayCreationException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $ambiguous = false, ?\Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
