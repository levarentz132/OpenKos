<?php

namespace App\Http\Controllers\Api\v1\Concerns;

use App\Models\User;

trait FormatsUserResponse
{
    /**
     * Format consistent user response with phone and email verification metadata.
     */
    protected function formatUserResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => ! is_null($user->email_verified_at),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'phone' => $user->phone,
            'phone_verified' => $user->hasVerifiedPhone(),
            'phone_verified_at' => $user->phone_verified_at?->toIso8601String(),
            'is_active' => $user->is_active,
            'roles' => $user->roles->pluck('name')->values()->all(),
            'has_tenant_profile' => $user->hasTenantProfile(),
            'tenant' => $user->tenant ? [
                'id' => $user->tenant->id,
                'name' => $user->tenant->name,
                'phone' => $user->tenant->phone,
                'id_card_number' => $user->tenant->id_card_number,
            ] : null,
        ];
    }
}
