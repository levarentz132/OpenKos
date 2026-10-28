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
            'otp_channel' => ['nullable', 'string', 'in:email,whatsapp'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'otp' => ['nullable', 'string', 'max:10'],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_holder' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Alamat email wajib diisi untuk pendaftaran akun.',
            'email.email' => 'Format alamat email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal harus 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $email = trim((string) $this->input('email'));
            $email = ! empty($email) ? strtolower($email) : null;
            $phone = trim((string) $this->input('phone'));

            if (! empty($phone)) {
                $cleaned = preg_replace('/[^0-9]/', '', $phone);
                $normalized62 = str_starts_with($cleaned, '0') ? '62' . substr($cleaned, 1) : $cleaned;
                $normalized08 = str_starts_with($cleaned, '62') ? '0' . substr($cleaned, 2) : $cleaned;

                $phoneVariants = array_unique(array_filter([
                    $phone,
                    $cleaned,
                    $normalized62,
                    $normalized08,
                    "+{$normalized62}",
                    "+{$cleaned}",
                ]));

                $existingTenantPhone = \App\Models\Tenant::whereIn('phone', $phoneVariants)->first();
                $existingPhoneUser = \App\Models\User::whereIn('phone', $phoneVariants)->first();

                if ($existingPhoneUser && $existingPhoneUser->isOwner()) {
                    $validator->errors()->add(
                        'phone',
                        'Nomor WhatsApp ini milik akun administrator. Silakan gunakan nomor lain.'
                    );
                } elseif ($existingTenantPhone || ($existingPhoneUser && $existingPhoneUser->hasTenantProfile())) {
                    $validator->errors()->add(
                        'phone',
                        'Nomor WhatsApp ini sudah terdaftar sebagai akun penyewa. Silakan langsung masuk / login.'
                    );
                } elseif ($existingPhoneUser) {
                    $validator->errors()->add(
                        'phone',
                        'Nomor WhatsApp ini sudah digunakan oleh akun lain.'
                    );
                }
            }

            if (! empty($email)) {
                $existingTenantEmail = \App\Models\Tenant::where('email', $email)->first();
                $existingUser = \App\Models\User::where('email', $email)->first();

                // Prevent hijacking admin/owner accounts
                if ($existingUser && $existingUser->isOwner()) {
                    $validator->errors()->add(
                        'email',
                        'Email ini milik akun administrator. Silakan gunakan email lain.'
                    );
                } elseif ($existingTenantEmail || ($existingUser && $existingUser->hasTenantProfile())) {
                    $validator->errors()->add(
                        'email',
                        'Email ini sudah terdaftar sebagai akun penyewa. Silakan langsung masuk / login.'
                    );
                } elseif ($existingUser) {
                    $validator->errors()->add(
                        'email',
                        'Email ini sudah digunakan oleh akun lain.'
                    );
                }
            }
        });
    }
}
