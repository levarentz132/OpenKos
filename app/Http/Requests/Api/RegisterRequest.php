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
            'phone' => ['nullable', 'string', 'max:25', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $email = strtolower(trim((string) $this->input('email')));
            $existingUser = \App\Models\User::where('email', $email)->first();

            if ($existingUser) {
                if ($existingUser->isOwner()) {
                    $validator->errors()->add(
                        'email',
                        'This email belongs to an administrator account. Tenant accounts must use a separate email address.'
                    );
                } elseif ($existingUser->hasTenantProfile()) {
                    $validator->errors()->add(
                        'email',
                        'A tenant account with this email already exists. Please log in directly.'
                    );
                } else {
                    $validator->errors()->add(
                        'email',
                        'The email has already been taken.'
                    );
                }
            }
        });
    }
}
