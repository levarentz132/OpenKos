<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reset_token' => ['nullable', 'string', 'max:255', 'required_without:login'],
            'login' => ['nullable', 'string', 'max:255', 'required_without:reset_token'],
            'code' => ['nullable', 'string', 'required_without:otp', 'max:10'],
            'otp' => ['nullable', 'string', 'required_without:code', 'max:10'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'reset_token.required_without' => 'Please provide either the reset token or your login phone/email.',
            'login.required_without' => 'Please provide either the reset token or your login phone/email.',
            'code.required_without' => 'The verification OTP code is required.',
            'otp.required_without' => 'The verification OTP code is required.',
            'password.required' => 'A new password is required.',
            'password.min' => 'The password must be at least 8 characters long.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }

    /**
     * Get resolved identifier (reset_token preferred over login).
     */
    public function resolvedIdentifier(): string
    {
        return (string) ($this->input('reset_token') ?: $this->input('login'));
    }

    /**
     * Get resolved OTP code.
     */
    public function resolvedCode(): string
    {
        return trim((string) ($this->input('code') ?: $this->input('otp')));
    }
}
