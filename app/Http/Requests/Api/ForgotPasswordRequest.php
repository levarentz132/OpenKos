<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'channel' => ['nullable', 'string', 'in:whatsapp,email'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Please provide your registered phone number or email address.',
            'channel.in' => 'Channel must be either "whatsapp" or "email".',
        ];
    }
}
