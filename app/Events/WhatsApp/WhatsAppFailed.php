<?php

namespace App\Events\WhatsApp;

use App\Exceptions\WhatsAppDeliveryException;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class WhatsAppFailed
{
    use Dispatchable;

    public function __construct(
        public string $driverId,
        public string $phone,
        public WhatsAppDeliveryException $exception,
    ) {}
}
