<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use LogicException;
use OpenKOS\Core\Enums\PaymentStatus;

#[Fillable([
    'invoice_id',
    'payment_id',
    'gateway_key',
    'reference',
    'provider_reference',
    'amount',
    'currency',
    'status',
    'expires_at',
    'metadata',
    'initiated_at',
    'settled_at',
    'failed_at',
    'expired_at',
    'canceled_at',
])]
class PaymentAttempt extends Model
{
    use Auditable, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (PaymentAttempt $attempt): void {
            $attempt->initiated_at ??= now();
        });

        static::updating(function (PaymentAttempt $attempt): void {
            if ($attempt->isDirty(['amount', 'currency'])) {
                throw new LogicException('Payment attempt amount and currency cannot be changed after creation.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentStatus::class,
            'expires_at' => 'datetime',
            'metadata' => 'array',
            'initiated_at' => 'datetime',
            'settled_at' => 'datetime',
            'failed_at' => 'datetime',
            'expired_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function setCurrencyAttribute(string $currency): void
    {
        $currency = strtoupper($currency);

        if (! preg_match('/\A[A-Z]{3}\z/D', $currency)) {
            throw new InvalidArgumentException('Currency must be a three-letter ISO 4217 code.');
        }

        $this->attributes['currency'] = $currency;
    }

    public function setMetadataAttribute(?array $metadata): void
    {
        if ($metadata === null) {
            $this->attributes['metadata'] = null;

            return;
        }

        foreach ($metadata as $key => $value) {
            if (! is_string($key) || (! is_bool($value) && ! is_int($value) && ! is_string($value) && $value !== null)) {
                throw new InvalidArgumentException('Payment attempt metadata must contain only scalar values.');
            }
        }

        $this->attributes['metadata'] = json_encode($metadata, JSON_THROW_ON_ERROR);
    }
}
