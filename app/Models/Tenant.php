<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'user_id',
    'name',
    'phone',
    'email',
    'password',
    'phone_verified_at',
    'email_verified_at',
    'last_login_at',
    'id_card_number',
    'emergency_contact_name',
    'emergency_contact_phone',
    'notes',
    'is_active',
])]
#[Hidden(['password', 'remember_token'])]
class Tenant extends Model implements AuthenticatableContract
{
    use Auditable, Authenticatable, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected array $auditableMask = ['phone', 'id_card_number', 'emergency_contact_phone'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function hasVerifiedPhone(): bool
    {
        return ! is_null($this->phone_verified_at) || ($this->user?->hasVerifiedPhone() ?? false);
    }

    public function hasVerifiedEmail(): bool
    {
        return ! is_null($this->email_verified_at) || ($this->user?->hasVerifiedEmail() ?? false);
    }

    public function isOwner(): bool
    {
        return false;
    }

    public function hasTenantProfile(): bool
    {
        return true;
    }

    public function routeNotificationForWhatsApp(Notification $notification): string
    {
        return $this->phone ?? '';
    }

    public function routeNotificationForMail(Notification $notification): array
    {
        $email = $this->email ?? $this->user?->email;

        return $email ? [$email => $this->name] : [];
    }

    public function hasReminderRoute(array $channels): bool
    {
        $hasPhoneRoute = $this->phone
            && (in_array('whatsapp', $channels, true) || in_array('log', $channels, true));
        $hasMailRoute = ($this->email ?? $this->user?->email) && in_array('mail', $channels, true);

        return (bool) ($hasPhoneRoute || $hasMailRoute);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leases(): BelongsToMany
    {
        return $this->belongsToMany(Lease::class)
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TenantDocument::class);
    }
}
