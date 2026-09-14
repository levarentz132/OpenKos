<?php

namespace App\Http\Controllers\Api\v1\Concerns;

use App\Models\Tenant;
use App\Models\User;

trait FormatsUserResponse
{
    /**
     * Format consistent user/tenant response with phone and email verification metadata.
     */
    protected function formatUserResponse(User|Tenant $subject): array
    {
        if ($subject instanceof Tenant) {
            $email = $subject->email ?? $subject->user?->email;
            $phone = $subject->phone ?? $subject->user?->phone;
            $emailVerifiedAt = $subject->email_verified_at ?? $subject->user?->email_verified_at;
            $phoneVerifiedAt = $subject->phone_verified_at ?? $subject->user?->phone_verified_at;

            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'email' => $email,
                'email_verified' => ! is_null($emailVerifiedAt),
                'email_verified_at' => $emailVerifiedAt?->toIso8601String(),
                'phone' => $phone,
                'phone_verified' => $subject->hasVerifiedPhone(),
                'phone_verified_at' => $phoneVerifiedAt?->toIso8601String(),
                'is_active' => $subject->is_active,
                'roles' => [],
                'has_tenant_profile' => true,
                'tenant' => [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'phone' => $phone,
                    'id_card_number' => $subject->id_card_number,
                ],
            ];
        }

        return [
            'id' => $subject->id,
            'name' => $subject->name,
            'email' => $subject->email,
            'email_verified' => ! is_null($subject->email_verified_at),
            'email_verified_at' => $subject->email_verified_at?->toIso8601String(),
            'phone' => $subject->phone,
            'phone_verified' => $subject->hasVerifiedPhone(),
            'phone_verified_at' => $subject->phone_verified_at?->toIso8601String(),
            'is_active' => $subject->is_active,
            'roles' => $subject->roles->pluck('name')->values()->all(),
            'has_tenant_profile' => $subject->hasTenantProfile(),
            'tenant' => $subject->tenant ? [
                'id' => $subject->tenant->id,
                'name' => $subject->tenant->name,
                'phone' => $subject->tenant->phone,
                'id_card_number' => $subject->tenant->id_card_number,
            ] : null,
        ];
    }
}
