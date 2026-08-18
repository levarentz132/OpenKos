<?php

namespace App\Results\Payment;

use App\Models\Payment;
use App\Models\PaymentAttempt;

final readonly class ReconcilePaymentAttemptResult
{
    public const SETTLED = 'settled';

    public const PENDING = 'pending';

    public const TERMINAL = 'terminal';

    public const UNSUPPORTED = 'unsupported';

    public const UNAVAILABLE = 'unavailable';

    public const ANOMALY = 'anomaly';

    public const FAILED = 'failed';

    public function __construct(
        public string $status,
        public ?PaymentAttempt $attempt = null,
        public ?Payment $payment = null,
        public ?string $message = null,
    ) {}
}
