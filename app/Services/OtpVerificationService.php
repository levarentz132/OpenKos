<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class OtpVerificationService
{
    public function __construct(
        protected WhatsAppManager $whatsAppManager,
    ) {}

    /**
     * Send or resend an OTP code via either WhatsApp or Email.
     *
     * @throws ValidationException
     */
    public function sendOtp(User $user, string $channel = 'whatsapp', ?string $target = null): array
    {
        $channel = strtolower(trim($channel));

        if (! in_array($channel, ['whatsapp', 'email'], true)) {
            throw ValidationException::withMessages([
                'channel' => ['Unsupported OTP channel. Choose "whatsapp" or "email".'],
            ]);
        }

        if ($channel === 'whatsapp') {
            return $this->sendWhatsAppOtp($user, $target);
        }

        return $this->sendEmailOtp($user, $target);
    }

    /**
     * Send WhatsApp OTP.
     */
    protected function sendWhatsAppOtp(User $user, ?string $phone = null): array
    {
        $targetPhone = $phone ?? $user->phone;

        if (blank($targetPhone)) {
            throw ValidationException::withMessages([
                'phone' => ['A phone number is required to send a WhatsApp verification code.'],
            ]);
        }

        $targetPhone = $this->normalizePhoneNumber($targetPhone);

        // Check 60-second cooldown
        $cooldownKey = "otp_cooldown_{$user->id}_whatsapp";
        if (Cache::has($cooldownKey)) {
            $secondsRemaining = max(1, Cache::get($cooldownKey) - time());
            throw ValidationException::withMessages([
                'otp' => ["Please wait {$secondsRemaining} seconds before requesting a new WhatsApp code."],
            ]);
        }

        $otp = (string) random_int(100000, 999999);

        // Cache for 10 minutes
        $cacheKey = "otp_{$user->id}_whatsapp";
        Cache::put($cacheKey, [
            'otp' => $otp,
            'phone' => $targetPhone,
        ], now()->addMinutes(10));

        Cache::put($cooldownKey, time() + 60, now()->addSeconds(60));

        $message = "Your OpenKos verification code is: *{$otp}*. Valid for 10 minutes. Please do not share this code with anyone.";

        $sent = false;
        try {
            $this->whatsAppManager->send($targetPhone, $message);
            $sent = true;
        } catch (\Throwable $e) {
            Log::warning("Failed to send WhatsApp OTP: {$e->getMessage()}");
        }

        $result = [
            'channel' => 'whatsapp',
            'target' => $targetPhone,
            'sent' => $sent,
        ];

        if (config('app.debug')) {
            $result['debug_otp'] = $otp;
        }

        return $result;
    }

    /**
     * Send Email OTP.
     */
    protected function sendEmailOtp(User $user, ?string $email = null): array
    {
        $targetEmail = strtolower(trim($email ?? $user->email));

        if (blank($targetEmail) || ! filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => ['A valid email address is required to send an email verification code.'],
            ]);
        }

        // Check 60-second cooldown
        $cooldownKey = "otp_cooldown_{$user->id}_email";
        if (Cache::has($cooldownKey)) {
            $secondsRemaining = max(1, Cache::get($cooldownKey) - time());
            throw ValidationException::withMessages([
                'otp' => ["Please wait {$secondsRemaining} seconds before requesting a new email code."],
            ]);
        }

        $otp = (string) random_int(100000, 999999);

        // Cache for 10 minutes
        $cacheKey = "otp_{$user->id}_email";
        Cache::put($cacheKey, [
            'otp' => $otp,
            'email' => $targetEmail,
        ], now()->addMinutes(10));

        Cache::put($cooldownKey, time() + 60, now()->addSeconds(60));

        $html = "<div style='font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; max-width: 560px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px;'>
            <h2 style='color: #0f172a; margin-top: 0;'>OpenKos Verification Code</h2>
            <p style='color: #334155; font-size: 15px;'>Use the code below to verify your account:</p>
            <div style='background-color: #f1f5f9; padding: 16px; text-align: center; font-size: 32px; font-weight: 700; letter-spacing: 6px; color: #1e293b; border-radius: 8px; margin: 24px 0;'>
                {$otp}
            </div>
            <p style='color: #64748b; font-size: 13px; margin-bottom: 0;'>This code expires in 10 minutes. If you did not request this, you can safely ignore this email.</p>
        </div>";

        $sent = false;
        try {
            Mail::html($html, function ($msg) use ($targetEmail) {
                $msg->to($targetEmail)->subject('Your OpenKos Verification Code');
            });
            $sent = true;
        } catch (\Throwable $e) {
            Log::warning("Failed to send Email OTP: {$e->getMessage()}");
        }

        $result = [
            'channel' => 'email',
            'target' => $targetEmail,
            'sent' => $sent,
        ];

        if (config('app.debug')) {
            $result['debug_otp'] = $otp;
        }

        return $result;
    }

    /**
     * Verify OTP code against either WhatsApp or Email channel.
     *
     * @throws ValidationException
     */
    public function verifyOtp(User $user, string $code, ?string $channel = null): array
    {
        $code = trim($code);
        $channelsToCheck = $channel ? [strtolower(trim($channel))] : ['whatsapp', 'email'];
        $matchedChannel = null;
        $cachedPayload = null;

        foreach ($channelsToCheck as $ch) {
            $key = "otp_{$user->id}_{$ch}";
            $cached = Cache::get($key);

            // Backward compatibility with previous phone_otp key
            if (! $cached && $ch === 'whatsapp') {
                $cached = Cache::get("phone_otp_{$user->id}");
            }

            if ($cached && isset($cached['otp']) && hash_equals((string) $cached['otp'], $code)) {
                $matchedChannel = $ch;
                $cachedPayload = $cached;
                break;
            }
        }

        if (! $matchedChannel || ! $cachedPayload) {
            throw ValidationException::withMessages([
                'code' => ['The verification code is invalid or has expired. Please request a new code.'],
            ]);
        }

        if ($matchedChannel === 'whatsapp') {
            $verifiedPhone = $cachedPayload['phone'] ?? $user->phone;
            $user->forceFill([
                'phone' => $verifiedPhone,
                'phone_verified_at' => now(),
            ])->save();

            if ($user->tenant) {
                $user->tenant->update(['phone' => $verifiedPhone]);
            }

            Cache::forget("otp_{$user->id}_whatsapp");
            Cache::forget("otp_cooldown_{$user->id}_whatsapp");
            Cache::forget("phone_otp_{$user->id}");
            Cache::forget("phone_otp_cooldown_{$user->id}");
        } elseif ($matchedChannel === 'email') {
            $verifiedEmail = $cachedPayload['email'] ?? $user->email;
            $user->forceFill([
                'email' => $verifiedEmail,
                'email_verified_at' => now(),
            ])->save();

            Cache::forget("otp_{$user->id}_email");
            Cache::forget("otp_cooldown_{$user->id}_email");
        }

        return [
            'verified' => true,
            'channel' => $matchedChannel,
            'user' => $user->fresh(),
        ];
    }

    /**
     * Clean and normalize phone numbers.
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
