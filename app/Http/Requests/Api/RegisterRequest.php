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
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:25'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'otp' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'A phone number is required when registering with a WhatsApp verification code.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $email = strtolower(trim((string) $this->input('email')));
            $phone = (string) $this->input('phone');
            $cleaned = preg_replace('/[^0-9]/', '', $phone);
            if (str_starts_with($cleaned, '0')) {
                $cleaned = '62' . substr($cleaned, 1);
            }

            if (! empty($email)) {
            $existingTenantEmail = \App\Models\Tenant::where('email', $email)->first();
            
        }
            $existingTenantPhone = \App\Models\Tenant::where('phone', $phone)->orWhere('phone', $cleaned)->first();
            $existingUser = \App\Models\User::where('email', $email)->first();
            $existingPhoneUser = \App\Models\User::where('phone', $phone)->orWhere('phone', $cleaned)->first();

            // Prevent hijacking admin/owner accounts
            if ($existingUser && $existingUser->isOwner()) {
                $validator->errors()->add(
                    'email',
                    'This email belongs to an administrator account. Tenant accounts must use a separate email address.'
                );
            } elseif ($existingTenantEmail && $existingTenantEmail->hasVerifiedPhone() && (! $existingTenantPhone || $existingTenantPhone->id !== $existingTenantEmail->id)) {
                $validator->errors()->add(
                    'email',
                    'A tenant account with this email already exists and is verified. Please log in directly.'
                );
            } elseif ($existingUser && ! $existingUser->hasTenantProfile()) {
                $validator->errors()->add(
                    'email',
                    'The email has already been taken.'
                );
            }

            if ($existingPhoneUser && $existingPhoneUser->isOwner()) {
                $validator->errors()->add(
                    'phone',
                    'This phone number belongs to an administrator account. Tenant accounts must use a separate phone number.'
                );
            } elseif ($existingTenantPhone && $existingTenantPhone->hasVerifiedPhone()) {
                $validator->errors()->add(
                    'phone',
                    'A tenant account with this phone number already exists and is verified. Please log in directly.'
                );
            } elseif ($existingPhoneUser && ! $existingPhoneUser->hasTenantProfile()) {
                $validator->errors()->add(
                    'phone',
                    'The phone number has already been taken.'
                );
            }
        });
    }
}
