<?php

namespace App\Business\Payments;

use InvalidArgumentException;
use OpenKOS\Core\Enums\PaymentStatus;

class PaymentAttemptStatusValidator
{
    public function validate(PaymentStatus $current, PaymentStatus $next): void
    {
        $allowed = match ($current) {
            PaymentStatus::Pending => [
                PaymentStatus::Settled,
                PaymentStatus::Failed,
                PaymentStatus::Expired,
                PaymentStatus::Canceled,
            ],
            default => [],
        };

        if (! in_array($next, $allowed, true)) {
            throw new InvalidArgumentException("Cannot transition payment attempt from {$current->value} to {$next->value}.");
        }
    }
}
