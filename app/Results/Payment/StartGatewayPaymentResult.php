<?php

namespace App\Results\Payment;

use App\Models\PaymentAttempt;
use OpenKOS\Core\Data\Payment\CheckoutInstructions;

final readonly class StartGatewayPaymentResult
{
    public function __construct(
        public PaymentAttempt $attempt,
        public CheckoutInstructions $instructions,
        public bool $reused = false,
    ) {}
}
