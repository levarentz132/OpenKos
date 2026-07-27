<?php

namespace App\Data\Mail;

final readonly class DriverHealthResult
{
    public function __construct(
        public bool $healthy,
        public ?string $message = null,
    ) {}
}
