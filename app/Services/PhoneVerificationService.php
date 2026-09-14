<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class PhoneVerificationService
{
    public function __construct(
        protected WhatsAppManager $whatsAppManager,
    ) {}

    /**
     * Send or resend an OTP code to user's phone via WhatsApp.
     *
     * @throws ValidationException
     */
    public function sendOtp(User $user, ?string $phone = null): void
    {
        $targetPhone = $phone ?? $user->phone;

        if (blank($targetPhone)) {
            throw ValidationException::withMessages([
                'phone' => ['A phone number is required to send a verification code.'],
            ]);
        }

        // Clean & normalize phone format
        $targetPhone = $this->normalizePhoneNumber($targetPhone);

        // Check rate limiting cooldown (60 seconds)
        $cooldownKey = "phone_otp_cooldown_{$user->id}";
        if (Cache::has($cooldownKey)) {
            $secondsRemaining = Cache::get($cooldownKey) - time();
            $seconds = max(1, $secondsRemaining);
            throw ValidationException::withMessages([
                'otp' => ["Please wait {$seconds} seconds before requesting a new code."],
            ]);
        }

        // Generate 6-digit OTP
        $otp = (string) random_int(100000, 999999);

        // Store in cache for 10 minutes
        $cacheKey = "phone_otp_{$user->id}";
        Cache::put($cacheKey, [
            'otp' => $otp,
            'phone' => $targetPhone,
        ], now()->addMinutes(10));

        // Cooldown for 60 seconds
        Cache::put($cooldownKey, time() + 60, now()->addSeconds(60));

        // Message body
        $message = "Your OpenKos verification code is: *{$otp}*. Valid for 10 minutes. Please do not share this code with anyone.";

        // Send via WhatsAppManager (handles log or Fonnte/provider seamlessly)
        $this->whatsAppManager->send($targetPhone, $message);
    }

    /**
     * Verify the OTP provided by the user.
     *
     * @throws ValidationException
     */
    public function verifyOtp(User $user, string $code): bool
    {
        $cacheKey = "phone_otp_{$user->id}";
        $cached = Cache::get($cacheKey);

        if (! $cached || ! isset($cached['otp'])) {
            throw ValidationException::withMessages([
                'code' => ['The verification code has expired or does not exist. Please request a new code.'],
            ]);
        }

        if (! hash_equals((string) $cached['otp'], trim($code))) {
            throw ValidationException::withMessages([
                'code' => ['The verification code is invalid.'],
            ]);
        }

        // Mark user phone verified and persist verified phone
        $verifiedPhone = $cached['phone'] ?? $user->phone;
        $user->forceFill([
            'phone' => $verifiedPhone,
            'phone_verified_at' => now(),
        ])->save();

        // Also sync tenant profile if exists
        if ($user->tenant) {
            $user->tenant->update(['phone' => $verifiedPhone]);
        }

        // Clean up OTP cache
        Cache::forget($cacheKey);
        Cache::forget("phone_otp_cooldown_{$user->id}");

        return true;
    }

    /**
     * Normalize international phone numbers (e.g., convert leading 0 to Indonesian 62 if needed, or strip non-digits).
     */
    public function normalizePhoneNumber(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($cleaned, '0')) {
            $cleaned = '62' . substr($cleaned, 1);
        }

        return $cleaned;
    }
}
