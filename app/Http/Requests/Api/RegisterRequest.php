<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:25'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'otp_channel' => ['nullable', 'string', 'in:whatsapp,email'],
            'otp' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate email uniqueness with specific context
            $email = strtolower(trim((string) $this->input('email')));
            $existingTenant = \App\Models\Tenant::where('email', $email)->first();
            $existingUser = \App\Models\User::where('email', $email)->first();

            if ($existingUser && $existingUser->isOwner()) {
                $validator->errors()->add(
                    'email',
                    'This email belongs to an administrator account. Tenant accounts must use a separate email address.'
                );
            } elseif ($existingTenant || ($existingUser && $existingUser->hasTenantProfile())) {
                $validator->errors()->add(
                    'email',
                    'A tenant account with this email already exists. Please log in directly.'
                );
            } elseif ($existingUser) {
                $validator->errors()->add(
                    'email',
                    'The email has already been taken.'
                );
            }

            // Validate phone requirement when WhatsApp channel is chosen or OTP is submitted
            $otpChannel = $this->input('otp_channel');
            $otpCode = $this->input('otp');
            $phone = (string) $this->input('phone');

            if (($otpChannel === 'whatsapp' || ! blank($otpCode)) && blank($phone)) {
                $validator->errors()->add(
                    'phone',
                    'A phone number is required when registering with a WhatsApp verification code.'
                );
            }

            // Validate phone uniqueness if provided
            if (! blank($phone)) {
                $cleaned = preg_replace('/[^0-9]/', '', $phone);
                if (str_starts_with($cleaned, '0')) {
                    $cleaned = '62' . substr($cleaned, 1);
                }

                $existingTenantPhone = \App\Models\Tenant::where('phone', $phone)->orWhere('phone', $cleaned)->first();
                $existingPhoneUser = \App\Models\User::where('phone', $phone)->orWhere('phone', $cleaned)->first();

                if ($existingPhoneUser && $existingPhoneUser->isOwner()) {
                    $validator->errors()->add(
                        'phone',
                        'This phone number belongs to an administrator account. Tenant accounts must use a separate phone number.'
                    );
                } elseif ($existingTenantPhone || ($existingPhoneUser && $existingPhoneUser->hasTenantProfile())) {
                    $validator->errors()->add(
                        'phone',
                        'A tenant account with this phone number already exists. Please log in directly.'
                    );
                } elseif ($existingPhoneUser) {
                    $validator->errors()->add(
                        'phone',
                        'The phone number has already been taken.'
                    );
                }
            }
        });
    }
}
