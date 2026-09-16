<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cart_token',
    'reference',
    'unit_id',
    'tenant_id',
    'guest_name',
    'guest_phone',
    'guest_email',
    'start_date',
    'end_date',
    'duration_months',
    'amount',
    'currency',
    'status',
    'lease_id',
    'invoice_id',
    'doku_checkout_url',
    'notes',
    'expires_at',
    'paid_at',
])]
class BookingOrder extends Model
{
    use Auditable, HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_PAYMENT_CONFLICT = 'payment_conflict';

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'amount' => 'decimal:2',
            'duration_months' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForCart(Builder $query, string $cartToken): void
    {
        $query->where('cart_token', $cartToken)->where('status', self::STATUS_PENDING);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isConflict(): bool
    {
        return $this->status === self::STATUS_PAYMENT_CONFLICT;
    }
}
