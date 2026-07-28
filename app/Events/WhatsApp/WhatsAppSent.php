<?php

namespace App\Events\WhatsApp;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class WhatsAppSent
{
    use Dispatchable;

    public function __construct(
        public string $driverId,
        public string $phone,
        public ?string $mediaUrl = null,
    ) {}
}
