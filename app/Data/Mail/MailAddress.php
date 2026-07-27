<?php

namespace App\Data\Mail;

use App\Exceptions\InvalidMailAddressException;

final readonly class MailAddress
{
    public function __construct(
        public string $address,
        public ?string $name = null,
    ) {
        if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            throw InvalidMailAddressException::for($address);
        }
    }
}
