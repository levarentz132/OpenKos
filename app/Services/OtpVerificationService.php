<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OtpVerificationService
{
    public function __construct(
        protected WhatsAppManager $whatsAppManager,
    ) {}

    /**
     * Create a staged pending registration in cache.
     * Database records are NOT created until OTP is verified.
     *
     * @throws ValidationException
     */
    public function createPendingRegistration(array $data, string $channel = 'whatsapp'): array
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, ['whatsapp', 'email'], true)) {
            $channel = 'whatsapp';
        }

        $email = strtolower(trim($data['email']));
        $phone = ! empty($data['phone']) ? $this->normalizePhoneNumber($data['phone']) : null;

        if ($channel === 'whatsapp' && blank($phone)) {
            throw ValidationException::withMessages([
                'phone' => ['A phone number is required when selecting WhatsApp as the OTP verification channel.'],
            ]);
        }

        $target = $channel === 'whatsapp' ? $phone : $email;

        // Rate limit: Cooldown per target to prevent spamming
        $targetCooldownKey = "pending_cooldown_target_{$channel}_{$target}";
        if (Cache::has($targetCooldownKey)) {
            $secondsRemaining = max(1, Cache::get($targetCooldownKey) - time());
            throw ValidationException::withMessages([
                'otp' => ["Please wait {$secondsRemaining} seconds before requesting a new registration code."],
            ]);
        }

        $token = 'reg_' . Str::random(32);
        $otp = (string) random_int(100000, 999999);

        $payload = [
            'token' => $token,
            'name' => trim($data['name']),
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make($data['password']),
            'otp' => $otp,
            'otp_channel' => $channel,
            'target' => $target,
            'device_name' => $data['device_name'] ?? 'api-client',
            'created_at' => now()->timestamp,
        ];

        // Store for 10 minutes
        Cache::put("pending_reg_{$token}", $payload, now()->addMinutes(10));
        Cache::put("pending_reg_email_{$email}", $token, now()->addMinutes(10));
        if ($phone) {
            Cache::put("pending_reg_phone_{$phone}", $token, now()->addMinutes(10));
        }

        Cache::put("pending_cooldown_{$token}", time() + 60, now()->addSeconds(60));
        Cache::put($targetCooldownKey, time() + 60, now()->addSeconds(60));

        // Dispatch OTP code
        $sent = $this->dispatchOtpCode($target, $channel, $otp);

        $result = [
            'registration_token' => $token,
            'channel' => $channel,
            'target' => $target,
            'sent' => $sent,
        ];

        if (config('app.debug')) {
            $result['debug_otp'] = $otp;
        }

        return $result;
    }

    /**
     * Check if a pending registration session exists for token or login identifier.
     */
    public function hasPendingRegistration(string $identifier): bool
    {
        return ! is_null($this->getPendingRegistration($identifier));
    }

    /**
     * Retrieve pending registration payload.
     */
    public function getPendingRegistration(string $identifier): ?array
    {
        $identifier = trim($identifier);

        if (str_starts_with($identifier, 'reg_')) {
            return Cache::get("pending_reg_{$identifier}");
        }

        // Try lookup by email
        $token = Cache::get("pending_reg_email_" . strtolower($identifier));

        // Try lookup by normalized phone
        if (! $token) {
            $normalized = $this->normalizePhoneNumber($identifier);
            $token = Cache::get("pending_reg_phone_{$normalized}") ?? Cache::get("pending_reg_phone_{$identifier}");
        }

        if ($token) {
            return Cache::get("pending_reg_{$token}");
        }

        return null;
    }

    /**
     * Resend OTP for a pending registration session.
     *
     * @throws ValidationException
     */
    public function resendPendingRegistrationOtp(string $identifier, ?string $channel = null): array
    {
        $pending = $this->getPendingRegistration($identifier);

        if (! $pending) {
            throw ValidationException::withMessages([
                'registration_token' => ['The registration session has expired. Please register again.'],
            ]);
        }

        $token = $pending['token'];
        $target = $pending['target'];

        if (Cache::has("pending_cooldown_{$token}")) {
            $secondsRemaining = max(1, Cache::get("pending_cooldown_{$token}") - time());
            throw ValidationException::withMessages([
                'otp' => ["Please wait {$secondsRemaining} seconds before requesting a new code."],
            ]);
        }

        if ($channel && in_array(strtolower($channel), ['whatsapp', 'email'], true)) {
            $pending['otp_channel'] = strtolower($channel);
            $target = $pending['otp_channel'] === 'whatsapp' ? $pending['phone'] : $pending['email'];

            if ($pending['otp_channel'] === 'whatsapp' && blank($target)) {
                throw ValidationException::withMessages([
                    'channel' => ['No phone number provided for WhatsApp verification.'],
                ]);
            }

            $pending['target'] = $target;
        }

        $newOtp = (string) random_int(100000, 999999);
        $pending['otp'] = $newOtp;

        Cache::put("pending_reg_{$token}", $pending, now()->addMinutes(10));
        Cache::put("pending_cooldown_{$token}", time() + 60, now()->addSeconds(60));
        Cache::put("pending_cooldown_target_{$pending['otp_channel']}_{$target}", time() + 60, now()->addSeconds(60));

        $sent = $this->dispatchOtpCode($target, $pending['otp_channel'], $newOtp);

        $result = [
            'registration_token' => $token,
            'channel' => $pending['otp_channel'],
            'target' => $target,
            'sent' => $sent,
        ];

        if (config('app.debug')) {
            $result['debug_otp'] = $newOtp;
        }

        return $result;
    }

    /**
     * Verify OTP and create ONLY Tenant record in the database.
     * Users table is completely untouched!
     *
     * @throws ValidationException
     */
    public function verifyPendingRegistration(string $identifier, string $code): array
    {
        $pending = $this->getPendingRegistration($identifier);

        if (! $pending) {
            throw ValidationException::withMessages([
                'code' => ['The registration session has expired or was not found. Please register again.'],
            ]);
        }

        if (! hash_equals((string) $pending['otp'], trim($code))) {
            throw ValidationException::withMessages([
                'code' => ['The verification code is invalid.'],
            ]);
        }

        // Concurrency safeguard: ensure email or phone was not created while pending
        $existing = Tenant::query()
            ->where('email', $pending['email'])
            ->when(! empty($pending['phone']), fn ($q) => $q->orWhere('phone', $pending['phone']))
            ->first();

        if ($existing) {
            $this->clearPendingRegistration($pending);
            throw ValidationException::withMessages([
                'email' => ['A tenant account with this email or phone number already exists.'],
            ]);
        }

        /** @var Tenant $tenant */
        $tenant = DB::transaction(function () use ($pending) {
            return Tenant::create([
                'name' => $pending['name'],
                'email' => $pending['email'],
                'phone' => $pending['phone'],
                'password' => $pending['password'], // Pre-hashed
                'email_verified_at' => $pending['otp_channel'] === 'email' ? now() : null,
                'phone_verified_at' => $pending['otp_channel'] === 'whatsapp' ? now() : null,
                'is_active' => true,
            ]);
        });

        $this->clearPendingRegistration($pending);

        return [
            'user' => $tenant,
            'channel' => $pending['otp_channel'],
        ];
    }

    /**
     * Clear all pending registration cache keys.
     */
    protected function clearPendingRegistration(array $pending): void
    {
        Cache::forget("pending_reg_{$pending['token']}");
        Cache::forget("pending_reg_email_{$pending['email']}");
        if (! empty($pending['phone'])) {
            Cache::forget("pending_reg_phone_{$pending['phone']}");
        }
        Cache::forget("pending_cooldown_{$pending['token']}");
        Cache::forget("pending_cooldown_target_{$pending['otp_channel']}_{$pending['target']}");
    }

    /**
     * Dispatch OTP code to target via channel.
     */
    protected function dispatchOtpCode(string $target, string $channel, string $otp): bool
    {
        if ($channel === 'whatsapp') {
            $message = "Your OpenKos verification code is: *{$otp}*. Valid for 10 minutes. Please do not share this code with anyone.";
            try {
                $this->whatsAppManager->send($target, $message);
                return true;
            } catch (\Throwable $e) {
                Log::warning("Failed to send WhatsApp OTP: {$e->getMessage()}");
                return false;
            }
        }

        $html = "<div style='font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; max-width: 560px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px;'>
            <h2 style='color: #0f172a; margin-top: 0;'>OpenKos Verification Code</h2>
            <p style='color: #334155; font-size: 15px;'>Use the code below to verify and complete your registration:</p>
            <div style='background-color: #f1f5f9; padding: 16px; text-align: center; font-size: 32px; font-weight: 700; letter-spacing: 6px; color: #1e293b; border-radius: 8px; margin: 24px 0;'>
                {$otp}
            </div>
            <p style='color: #64748b; font-size: 13px; margin-bottom: 0;'>This code expires in 10 minutes. If you did not request this, you can safely ignore this email.</p>
        </div>";

        try {
            Mail::html($html, function ($msg) use ($target) {
                $msg->to($target)->subject('Your OpenKos Verification Code');
            });
            return true;
        } catch (\Throwable $e) {
            Log::warning("Failed to send Email OTP: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Send or resend an OTP code for an existing user or tenant via WhatsApp or Email.
     *
     * @throws ValidationException
     */
    public function sendOtp(User|Tenant $user, string $channel = 'whatsapp', ?string $target = null): array
    {
        $channel = strtolower(trim($channel));

        if (! in_array($channel, ['whatsapp', 'email'], true)) {
            throw ValidationException::withMessages([
                'channel' => ['Unsupported OTP channel. Choose "whatsapp" or "email".'],
            ]);
        }

        $typePrefix = $user instanceof Tenant ? 'tenant' : 'user';

        if ($channel === 'whatsapp') {
            $targetPhone = $this->normalizePhoneNumber($target ?? $user->phone ?? '');
            if (blank($targetPhone)) {
                throw ValidationException::withMessages([
                    'phone' => ['A phone number is required to send a WhatsApp verification code.'],
                ]);
            }

            $cooldownKey = "otp_cooldown_{$typePrefix}_{$user->id}_whatsapp";
            if (Cache::has($cooldownKey)) {
                $secondsRemaining = max(1, Cache::get($cooldownKey) - time());
                throw ValidationException::withMessages([
                    'otp' => ["Please wait {$secondsRemaining} seconds before requesting a new WhatsApp code."],
                ]);
            }

            $otp = (string) random_int(100000, 999999);
            Cache::put("otp_{$typePrefix}_{$user->id}_whatsapp", ['otp' => $otp, 'phone' => $targetPhone], now()->addMinutes(10));
            Cache::put("otp_{$user->id}_whatsapp", ['otp' => $otp, 'phone' => $targetPhone], now()->addMinutes(10));
            Cache::put("phone_otp_{$user->id}", ['otp' => $otp, 'phone' => $targetPhone], now()->addMinutes(10));
            Cache::put($cooldownKey, time() + 60, now()->addSeconds(60));

            $sent = $this->dispatchOtpCode($targetPhone, 'whatsapp', $otp);

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

        $targetEmail = strtolower(trim($target ?? $user->email ?? ''));
        if (blank($targetEmail) || ! filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => ['A valid email address is required to send an email verification code.'],
            ]);
        }

        $cooldownKey = "otp_cooldown_{$typePrefix}_{$user->id}_email";
        if (Cache::has($cooldownKey)) {
            $secondsRemaining = max(1, Cache::get($cooldownKey) - time());
            throw ValidationException::withMessages([
                'otp' => ["Please wait {$secondsRemaining} seconds before requesting a new email code."],
            ]);
        }

        $otp = (string) random_int(100000, 999999);
        Cache::put("otp_{$typePrefix}_{$user->id}_email", ['otp' => $otp, 'email' => $targetEmail], now()->addMinutes(10));
        Cache::put("otp_{$user->id}_email", ['otp' => $otp, 'email' => $targetEmail], now()->addMinutes(10));
        Cache::put($cooldownKey, time() + 60, now()->addSeconds(60));

        $sent = $this->dispatchOtpCode($targetEmail, 'email', $otp);

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
     * Verify OTP code for an existing user or tenant against WhatsApp or Email.
     *
     * @throws ValidationException
     */
    public function verifyOtp(User|Tenant $user, string $code, ?string $channel = null): array
    {
        $code = trim($code);
        $channelsToCheck = $channel ? [strtolower(trim($channel))] : ['whatsapp', 'email'];
        $matchedChannel = null;
        $cachedPayload = null;
        $typePrefix = $user instanceof Tenant ? 'tenant' : 'user';

        foreach ($channelsToCheck as $ch) {
            $key = "otp_{$typePrefix}_{$user->id}_{$ch}";
            $cached = Cache::get($key);

            // Backward compatibility checks
            if (! $cached) {
                $cached = Cache::get("otp_{$user->id}_{$ch}");
            }
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

            if ($user instanceof User && $user->tenant) {
                $user->tenant->update(['phone' => $verifiedPhone]);
            }

            Cache::forget("otp_{$typePrefix}_{$user->id}_whatsapp");
            Cache::forget("otp_cooldown_{$typePrefix}_{$user->id}_whatsapp");
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

            Cache::forget("otp_{$typePrefix}_{$user->id}_email");
            Cache::forget("otp_cooldown_{$typePrefix}_{$user->id}_email");
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
