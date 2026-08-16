<?php

namespace App\Results\Payment;

use App\Models\Payment;
use App\Models\PaymentAttempt;

final readonly class ApplyGatewayPaymentResult
{
    public const PROCESSED = 'processed';

    public const DUPLICATE = 'duplicate';

    public const UNKNOWN = 'unknown';

    public const ANOMALY = 'anomaly';

    public function __construct(
        public string $status,
        public ?PaymentAttempt $attempt = null,
        public ?Payment $payment = null,
    ) {}

    public function acknowledged(): bool
    {
        return in_array($this->status, [self::PROCESSED, self::DUPLICATE], true);
    }
}
