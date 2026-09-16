<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MailManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenKOS\Core\Data\Mail\MailAddress;
use OpenKOS\Core\Data\Mail\MailMessage;

class OtpVerificationService
{
    public function __construct(
        protected WhatsAppManager $whatsAppManager,
        protected ?MailManager $mailManager = null,
    ) {
        $this->mailManager ??= app(MailManager::class);
    }

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
            'whatsapp_otp' => $channel === 'whatsapp' ? $otp : null,
            'email_otp' => $channel === 'email' ? $otp : null,
            'whatsapp_verified' => false,
            'whatsapp_verified_at' => null,
            'email_verified' => false,
            'email_verified_at' => null,
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
        $dispatch = $this->dispatchOtpCode($target, $channel, $otp);

        $result = [
            'registration_token' => $token,
            'channel' => $channel,
            'target' => $target,
            'sent' => $dispatch['sent'],
            'driver' => $dispatch['driver'],
            'is_mock' => $dispatch['is_mock'],
            'delivery_warning' => $dispatch['warning'],
            'delivery_error' => $dispatch['error'],
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
        if ($pending['otp_channel'] === 'whatsapp') {
            $pending['whatsapp_otp'] = $newOtp;
        } elseif ($pending['otp_channel'] === 'email') {
            $pending['email_otp'] = $newOtp;
        }

        Cache::put("pending_reg_{$token}", $pending, now()->addMinutes(10));
        Cache::put("pending_cooldown_{$token}", time() + 60, now()->addSeconds(60));
        Cache::put("pending_cooldown_target_{$pending['otp_channel']}_{$target}", time() + 60, now()->addSeconds(60));

        $dispatch = $this->dispatchOtpCode($target, $pending['otp_channel'], $newOtp);

        $result = [
            'registration_token' => $token,
            'channel' => $pending['otp_channel'],
            'target' => $target,
            'sent' => $dispatch['sent'],
            'driver' => $dispatch['driver'],
            'is_mock' => $dispatch['is_mock'],
            'delivery_warning' => $dispatch['warning'],
            'delivery_error' => $dispatch['error'],
        ];

        if (config('app.debug')) {
            $result['debug_otp'] = $newOtp;
        }

        return $result;
    }

    /**
     * Verify OTP and create ONLY Tenant record in the database.
     * Users table is completely untouched! Supports both WhatsApp and Email verification.
     *
     * @throws ValidationException
     */
    public function verifyPendingRegistration(string $identifier, string $code, ?string $channel = null): array
    {
        $pending = $this->getPendingRegistration($identifier);

        if (! $pending) {
            throw ValidationException::withMessages([
                'code' => ['The registration session has expired or was not found. Please register again.'],
            ]);
        }

        $code = trim($code);
        $channelToCheck = $channel ? strtolower(trim($channel)) : null;

        $matchedChannel = null;
        if ($channelToCheck === 'whatsapp' && isset($pending['whatsapp_otp']) && hash_equals((string) $pending['whatsapp_otp'], $code)) {
            $matchedChannel = 'whatsapp';
        } elseif ($channelToCheck === 'email' && isset($pending['email_otp']) && hash_equals((string) $pending['email_otp'], $code)) {
            $matchedChannel = 'email';
        } elseif (hash_equals((string) $pending['otp'], $code)) {
            $matchedChannel = $pending['otp_channel'];
        } elseif (isset($pending['whatsapp_otp']) && hash_equals((string) $pending['whatsapp_otp'], $code)) {
            $matchedChannel = 'whatsapp';
        } elseif (isset($pending['email_otp']) && hash_equals((string) $pending['email_otp'], $code)) {
            $matchedChannel = 'email';
        }

        if (! $matchedChannel) {
            throw ValidationException::withMessages([
                'code' => ['The verification code is invalid.'],
            ]);
        }

        if ($matchedChannel === 'whatsapp') {
            $pending['whatsapp_verified'] = true;
            $pending['whatsapp_verified_at'] = now()->toIso8601String();
        } elseif ($matchedChannel === 'email') {
            $pending['email_verified'] = true;
            $pending['email_verified_at'] = now()->toIso8601String();
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

        $isPhoneVerified = ! empty($pending['whatsapp_verified']) || $matchedChannel === 'whatsapp';
        $isEmailVerified = ! empty($pending['email_verified']) || $matchedChannel === 'email';

        /** @var Tenant $tenant */
        $tenant = DB::transaction(function () use ($pending, $isPhoneVerified, $isEmailVerified) {
            return Tenant::create([
                'name' => $pending['name'],
                'email' => $pending['email'],
                'phone' => $pending['phone'],
                'password' => $pending['password'], // Pre-hashed
                'email_verified_at' => $isEmailVerified ? now() : null,
                'phone_verified_at' => $isPhoneVerified ? now() : null,
                'is_active' => true,
            ]);
        });

        $this->clearPendingRegistration($pending);

        return [
            'user' => $tenant,
            'channel' => $matchedChannel,
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
     *
     * @return array{sent: bool, driver: string, is_mock: bool, warning: ?string, error: ?string}
     */
    protected function dispatchOtpCode(string $target, string $channel, string $otp): array
    {
        if ($channel === 'whatsapp') {
            $message = "Your OpenKos verification code is: *{$otp}*. Valid for 10 minutes. Please do not share this code with anyone.";
            $driverName = Setting::get('whatsapp_driver') ?? config('services.whatsapp.default', 'log');
            $isMock = in_array($driverName, ['log', 'openkos/whatsapp-log'], true);

            try {
                $this->whatsAppManager->send($target, $message);

                return [
                    'sent' => true,
                    'driver' => $driverName,
                    'is_mock' => $isMock,
                    'warning' => $isMock
                        ? 'WhatsApp driver is set to [log]. The message was written to server logs, not sent to a physical phone.'
                        : null,
                    'error' => null,
                ];
            } catch (\Throwable $e) {
                Log::warning("Failed to send WhatsApp OTP to {$target}: {$e->getMessage()}");

                return [
                    'sent' => false,
                    'driver' => $driverName,
                    'is_mock' => false,
                    'warning' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $effectiveMailConfig = Setting::effectiveMailConfig();
        $mailDriverName = $effectiveMailConfig['driver'] ?? config('mail.default', 'smtp');
        $isMock = in_array($mailDriverName, ['log', 'openkos/log', 'array'], true);
        $html = "<div style='font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; max-width: 560px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px;'>
            <h2 style='color: #0f172a; margin-top: 0;'>OpenKos Verification Code</h2>
            <p style='color: #334155; font-size: 15px;'>Use the code below to verify and complete your registration:</p>
            <div style='background-color: #f1f5f9; padding: 16px; text-align: center; font-size: 32px; font-weight: 700; letter-spacing: 6px; color: #1e293b; border-radius: 8px; margin: 24px 0;'>
                {$otp}
            </div>
            <p style='color: #64748b; font-size: 13px; margin-bottom: 0;'>This code expires in 10 minutes. If you did not request this, you can safely ignore this email.</p>
        </div>";

        try {
            if ($this->mailManager) {
                $mailMessage = new MailMessage(
                    to: [new MailAddress($target)],
                    subject: 'Your OpenKos Verification Code',
                    htmlBody: $html,
                    plainTextBody: "Your OpenKos verification code is: {$otp}. Valid for 10 minutes.",
                );
                $this->mailManager->send($mailMessage);
            } else {
                Mail::html($html, function ($msg) use ($target) {
                    $msg->to($target)->subject('Your OpenKos Verification Code');
                });
            }

            return [
                'sent' => true,
                'driver' => $mailDriverName,
                'is_mock' => $isMock,
                'warning' => $isMock
                    ? "Mail driver is set to [{$mailDriverName}]. The email was written to server logs/memory, not delivered to an inbox."
                    : null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning("Failed to send Email OTP to {$target}: {$e->getMessage()}");

            return [
                'sent' => false,
                'driver' => $mailDriverName,
                'is_mock' => false,
                'warning' => null,
                'error' => $e->getMessage(),
            ];
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

            $dispatch = $this->dispatchOtpCode($targetPhone, 'whatsapp', $otp);

            $result = [
                'channel' => 'whatsapp',
                'target' => $targetPhone,
                'sent' => $dispatch['sent'],
                'driver' => $dispatch['driver'],
                'is_mock' => $dispatch['is_mock'],
                'delivery_warning' => $dispatch['warning'],
                'delivery_error' => $dispatch['error'],
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

        if ($user instanceof Tenant) {
            $cooldownKey = "otp_cooldown_{$typePrefix}_{$user->id}_email";
            if (Cache::has($cooldownKey)) {
                $secondsRemaining = max(1, Cache::get($cooldownKey) - time());
                throw ValidationException::withMessages([
                    'otp' => ["Please wait {$secondsRemaining} seconds before requesting a new email verification link."],
                ]);
            }
            Cache::put($cooldownKey, time() + 60, now()->addSeconds(60));

            $emailResult = $this->sendEmailVerificationLink($user);

            return [
                'channel' => 'email',
                'target' => $targetEmail,
                'sent' => $emailResult['sent'],
                'driver' => $emailResult['driver'] ?? null,
                'is_mock' => $emailResult['is_mock'] ?? false,
                'delivery_warning' => $emailResult['warning'] ?? null,
                'delivery_error' => $emailResult['error'] ?? null,
            ];
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

        $dispatch = $this->dispatchOtpCode($targetEmail, 'email', $otp);

        $result = [
            'channel' => 'email',
            'target' => $targetEmail,
            'sent' => $dispatch['sent'],
            'driver' => $dispatch['driver'],
            'is_mock' => $dispatch['is_mock'],
            'delivery_warning' => $dispatch['warning'],
            'delivery_error' => $dispatch['error'],
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
     * Check comprehensive registration and dual-channel verification status (WhatsApp & Email).
     */
    public function checkStatus(string|User|Tenant $identifier): array
    {
        if ($identifier instanceof Tenant || $identifier instanceof User) {
            return $this->formatModelStatus($identifier);
        }

        $cleanIdentifier = trim((string) $identifier);

        // 1. Check pending registration in cache
        $pending = $this->getPendingRegistration($cleanIdentifier);
        if ($pending) {
            $hasPhone = ! empty($pending['phone']);
            $hasEmail = ! empty($pending['email']);
            $whatsappVerified = ! empty($pending['whatsapp_verified']);
            $emailVerified = ! empty($pending['email_verified']);

            return [
                'status' => 'pending_registration',
                'registered' => false,
                'pending_registration' => true,
                'registration_token' => $pending['token'],
                'user' => [
                    'name' => $pending['name'],
                    'email' => $pending['email'],
                    'phone' => $pending['phone'],
                ],
                'verifications' => [
                    'whatsapp' => [
                        'available' => $hasPhone,
                        'target' => $pending['phone'],
                        'verified' => $whatsappVerified,
                        'verified_at' => $pending['whatsapp_verified_at'] ?? null,
                        'status' => $whatsappVerified ? 'verified' : ($hasPhone ? 'pending' : 'unconfigured'),
                    ],
                    'email' => [
                        'available' => $hasEmail,
                        'target' => $pending['email'],
                        'verified' => $emailVerified,
                        'verified_at' => $pending['email_verified_at'] ?? null,
                        'status' => $emailVerified ? 'verified' : 'pending',
                    ],
                ],
                'is_fully_verified' => ($whatsappVerified || ! $hasPhone) && $emailVerified,
            ];
        }

        // 2. Search in tenants table
        $normalized = $this->normalizePhoneNumber($cleanIdentifier);
        /** @var Tenant|null $tenant */
        $tenant = Tenant::query()
            ->where('email', strtolower($cleanIdentifier))
            ->orWhere('phone', $cleanIdentifier)
            ->orWhere('phone', $normalized)
            ->first();

        if ($tenant) {
            return $this->formatModelStatus($tenant);
        }

        // 3. Fallback search in users table
        /** @var User|null $user */
        $user = User::query()
            ->where('email', strtolower($cleanIdentifier))
            ->orWhere('phone', $cleanIdentifier)
            ->orWhere('phone', $normalized)
            ->first();

        if ($user) {
            if ($user->hasTenantProfile()) {
                return $this->formatModelStatus($user->tenant);
            }

            if ($user->isOwner()) {
                return [
                    'status' => 'admin_account',
                    'registered' => true,
                    'pending_registration' => false,
                    'message' => 'This account is an administrator account, not a tenant.',
                ];
            }

            return $this->formatModelStatus($user);
        }

        // 4. Not found
        return [
            'status' => 'unregistered',
            'registered' => false,
            'pending_registration' => false,
            'message' => 'No account or pending registration found for this identifier.',
        ];
    }

    /**
     * Format verification status from Tenant or User model.
     */
    protected function formatModelStatus(Tenant|User $subject): array
    {
        $phone = $subject instanceof Tenant ? ($subject->phone ?? $subject->user?->phone) : $subject->phone;
        $email = $subject instanceof Tenant ? ($subject->email ?? $subject->user?->email) : $subject->email;
        $hasPhone = ! empty($phone);
        $hasEmail = ! empty($email);

        $phoneVerified = $subject->hasVerifiedPhone();
        $emailVerified = $subject->hasVerifiedEmail();

        $phoneVerifiedAt = $subject instanceof Tenant
            ? ($subject->phone_verified_at ?? $subject->user?->phone_verified_at)
            : $subject->phone_verified_at;

        $emailVerifiedAt = $subject instanceof Tenant
            ? ($subject->email_verified_at ?? $subject->user?->email_verified_at)
            : $subject->email_verified_at;

        return [
            'status' => 'registered',
            'registered' => true,
            'pending_registration' => false,
            'id' => $subject->id,
            'name' => $subject->name,
            'email' => $email,
            'phone' => $phone,
            'is_active' => (bool) $subject->is_active,
            'verifications' => [
                'whatsapp' => [
                    'available' => $hasPhone,
                    'target' => $phone,
                    'verified' => $phoneVerified,
                    'verified_at' => $phoneVerifiedAt?->toIso8601String(),
                    'status' => $phoneVerified ? 'verified' : ($hasPhone ? 'unverified' : 'unconfigured'),
                ],
                'email' => [
                    'available' => $hasEmail,
                    'target' => $email,
                    'verified' => $emailVerified,
                    'verified_at' => $emailVerifiedAt?->toIso8601String(),
                    'status' => $emailVerified ? 'verified' : ($hasEmail ? 'unverified' : 'unconfigured'),
                ],
            ],
            'phone_verified' => $phoneVerified,
            'is_fully_verified' => $phoneVerified,
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

    /**
     * Send WhatsApp OTP to a phone number directly from the registration form.
     * Allows unverified existing tenants to receive OTP to complete verification.
     *
     * @throws ValidationException
     */
    public function sendRegistrationPhoneOtp(string $phone): array
    {
        $normalizedPhone = $this->normalizePhoneNumber($phone);

        if (blank($normalizedPhone)) {
            throw ValidationException::withMessages([
                'phone' => ['A valid phone number is required to receive the WhatsApp verification code.'],
            ]);
        }

        // Check existing in tenants table
        $existing = Tenant::query()
            ->where('phone', $phone)
            ->orWhere('phone', $normalizedPhone)
            ->first();

        if ($existing && $existing->hasVerifiedPhone()) {
            throw ValidationException::withMessages([
                'phone' => ['A tenant account with this phone number is already verified. Please log in directly.'],
            ]);
        }

        $cooldownKey = "reg_phone_otp_cooldown_{$normalizedPhone}";
        if (Cache::has($cooldownKey)) {
            $secondsRemaining = max(1, Cache::get($cooldownKey) - time());
            throw ValidationException::withMessages([
                'otp' => ["Please wait {$secondsRemaining} seconds before requesting a new WhatsApp code."],
            ]);
        }

        $otp = (string) random_int(100000, 999999);
        Cache::put("reg_phone_otp_{$normalizedPhone}", ['otp' => $otp, 'phone' => $normalizedPhone], now()->addMinutes(10));
        Cache::put("reg_phone_otp_{$phone}", ['otp' => $otp, 'phone' => $normalizedPhone], now()->addMinutes(10));
        Cache::put($cooldownKey, time() + 60, now()->addSeconds(60));

        $dispatch = $this->dispatchOtpCode($normalizedPhone, 'whatsapp', $otp);

        $result = [
            'channel' => 'whatsapp',
            'target' => $normalizedPhone,
            'sent' => $dispatch['sent'],
            'driver' => $dispatch['driver'],
            'is_mock' => $dispatch['is_mock'],
            'delivery_warning' => $dispatch['warning'],
            'delivery_error' => $dispatch['error'],
            'existing_account' => (bool) $existing,
        ];

        if (config('app.debug')) {
            $result['debug_otp'] = $otp;
        }

        return $result;
    }

    /**
     * Complete registration using the WhatsApp OTP entered directly in the registration form.
     * Creates new Tenant record or converts unverified existing Tenant with phone_verified_at = now().
     *
     * @throws ValidationException
     */
    public function registerWithPhoneOtp(array $data): array
    {
        $phone = trim((string) ($data['phone'] ?? ''));
        $normalizedPhone = $this->normalizePhoneNumber($phone);
        $code = trim((string) ($data['otp'] ?? ''));

        if (blank($code)) {
            throw ValidationException::withMessages([
                'otp' => ['The WhatsApp verification code is required to complete registration.'],
            ]);
        }

        // 1. Verify OTP code against cached registration phone OTP
        $cached = Cache::get("reg_phone_otp_{$normalizedPhone}") ?? Cache::get("reg_phone_otp_{$phone}");
        $isValid = false;

        if ($cached && isset($cached['otp']) && hash_equals((string) $cached['otp'], $code)) {
            $isValid = true;
        } else {
            // Check if staged pending registration session exists for this phone
            $pendingToken = Cache::get("pending_reg_phone_{$normalizedPhone}") ?? Cache::get("pending_reg_phone_{$phone}");
            if ($pendingToken) {
                $pending = $this->getPendingRegistration($pendingToken);
                if ($pending && (hash_equals((string) ($pending['whatsapp_otp'] ?? ''), $code) || hash_equals((string) ($pending['otp'] ?? ''), $code))) {
                    $isValid = true;
                }
            }
        }

        if (! $isValid) {
            throw ValidationException::withMessages([
                'otp' => ['The WhatsApp verification code is invalid or has expired. Please request a new code.'],
            ]);
        }

        $email = ! empty($data['email']) ? strtolower(trim((string) $data['email'])) : null;

        // 2. Check existing tenant
        $existingQuery = Tenant::query()
            ->where(function ($q) use ($normalizedPhone, $phone) {
                $q->where('phone', $normalizedPhone)
                  ->orWhere('phone', $phone);
            });
        if (! empty($email)) {
            $existingQuery->orWhere('email', $email);
        }
        $existing = $existingQuery->first();

        if ($existing && $existing->hasVerifiedPhone()) {
            throw ValidationException::withMessages([
                'phone' => ['A tenant account with this phone number or email is already verified. Please log in directly.'],
            ]);
        }

        // 3. Create new or convert unverified Tenant in database (phone verified)
        /** @var Tenant $tenant */
        $tenant = DB::transaction(function () use ($data, $email, $normalizedPhone, $existing) {
            if ($existing) {
                $existing->forceFill([
                    'name' => trim((string) $data['name']) ?: $existing->name,
                    'email' => $email,
                    'phone' => $normalizedPhone,
                    'password' => Hash::make($data['password']),
                    'phone_verified_at' => now(),
                    'is_active' => true,
                ])->save();

                return $existing->fresh();
            }

            return Tenant::create([
                'name' => trim((string) $data['name']),
                'email' => $email,
                'phone' => $normalizedPhone,
                'password' => Hash::make($data['password']),
                'phone_verified_at' => now(),
                'email_verified_at' => null,
                'is_active' => true,
            ]);
        });

        // 4. Clear cached OTP
        Cache::forget("reg_phone_otp_{$normalizedPhone}");
        Cache::forget("reg_phone_otp_{$phone}");
        Cache::forget("reg_phone_otp_cooldown_{$normalizedPhone}");

        $pendingToken = Cache::get("pending_reg_phone_{$normalizedPhone}");
        if ($pendingToken) {
            $pending = $this->getPendingRegistration($pendingToken);
            if ($pending) {
                $this->clearPendingRegistration($pending);
            }
        }

        // 5. Generate Sanctum Personal Access Token
        $deviceName = $data['device_name'] ?? 'api-client';
        $token = $tenant->createToken($deviceName)->plainTextToken;

        return [
            'user' => $tenant,
            'token' => $token,
            'phone_verified' => true,
            'was_converted' => (bool) $existing,
        ];
    }

    /**
     * Dispatch an email containing a signed verification link to the tenant.
     */
    public function sendEmailVerificationLink(Tenant $tenant): array
    {
        $verificationUrl = URL::temporarySignedRoute(
            'api.v1.auth.verify-email',
            now()->addHours(24),
            [
                'id' => $tenant->id,
                'hash' => sha1($tenant->email),
            ]
        );

        $effectiveMailConfig = Setting::effectiveMailConfig();
        $mailDriverName = $effectiveMailConfig['driver'] ?? config('mail.default', 'smtp');
        $isMock = in_array($mailDriverName, ['log', 'openkos/log', 'array'], true);

        $html = "<div style='font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; max-width: 560px; margin: 0 auto; padding: 32px 24px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;'>
            <div style='text-align: center; margin-bottom: 24px;'>
                <h2 style='color: #0f172a; margin: 0 0 8px; font-size: 22px; font-weight: 700;'>Verifikasi Alamat Email Anda</h2>
                <p style='color: #64748b; font-size: 14px; margin: 0;'>Halo <strong>" . e($tenant->name) . "</strong>, terima kasih telah mendaftar di OpenKos.</p>
            </div>
            <p style='color: #334155; font-size: 15px; line-height: 1.6;'>Nomor WhatsApp Anda telah berhasil diverifikasi. Untuk menyelesaikan proses pendaftaran akun Anda, silakan klik tombol di bawah ini untuk memverifikasi email Anda:</p>
            <div style='text-align: center; margin: 32px 0;'>
                <a href='{$verificationUrl}' style='background-color: #2563eb; color: #ffffff; padding: 14px 28px; text-decoration: none; font-size: 15px; font-weight: 600; border-radius: 8px; display: inline-block; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);'>Verifikasi Email Saya</a>
            </div>
            <p style='color: #64748b; font-size: 13px; line-height: 1.5; margin-bottom: 8px;'>Jika tombol di atas tidak berfungsi, salin dan buka tautan berikut di peramban (browser) Anda:</p>
            <p style='word-break: break-all; font-size: 12px; color: #2563eb; background: #f8fafc; padding: 10px; border-radius: 6px; margin-bottom: 24px;'>{$verificationUrl}</p>
            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;' />
            <p style='color: #94a3b8; font-size: 12px; margin: 0;'>Tautan verifikasi ini berlaku selama 24 jam. Jika Anda tidak merasa mendaftar di OpenKos, Anda dapat mengabaikan email ini.</p>
        </div>";

        try {
            if ($this->mailManager) {
                $mailMessage = new MailMessage(
                    to: [new MailAddress($tenant->email, $tenant->name)],
                    subject: 'Verifikasi Alamat Email Anda - OpenKos',
                    htmlBody: $html,
                    plainTextBody: "Halo {$tenant->name},\n\nSilakan verifikasi email Anda dengan mengunjungi tautan berikut:\n{$verificationUrl}\n\nTautan ini berlaku selama 24 jam.",
                );
                $this->mailManager->send($mailMessage);
            } else {
                Mail::html($html, function ($msg) use ($tenant) {
                    $msg->to($tenant->email, $tenant->name)->subject('Verifikasi Alamat Email Anda - OpenKos');
                });
            }

            return [
                'sent' => true,
                'driver' => $mailDriverName,
                'is_mock' => $isMock,
                'url' => $verificationUrl,
                'warning' => $isMock
                    ? "Mail driver is set to [{$mailDriverName}]. The verification link was written to server logs."
                    : null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning("Failed to send Email Verification link to {$tenant->email}: {$e->getMessage()}");

            return [
                'sent' => false,
                'driver' => $mailDriverName,
                'is_mock' => false,
                'url' => $verificationUrl,
                'warning' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify email from signed link.
     *
     * @throws ValidationException
     */
    public function verifyEmailFromSignedLink(int $tenantId, string $hash): Tenant
    {
        /** @var Tenant|null $tenant */
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            throw ValidationException::withMessages([
                'email' => ['Akun penyewa tidak ditemukan.'],
            ]);
        }

        if (! hash_equals(sha1($tenant->email), $hash)) {
            throw ValidationException::withMessages([
                'email' => ['Tautan verifikasi email tidak valid untuk akun ini.'],
            ]);
        }

        if (! $tenant->hasVerifiedEmail()) {
            $tenant->forceFill(['email_verified_at' => now()])->save();
        }

        return $tenant->fresh();
    }

    /**
     * Send OTP for password reset via WhatsApp or Email.
     *
     * @throws ValidationException
     */
    public function sendPasswordResetOtp(string $login, ?string $channel = null): array
    {
        $login = trim($login);
        $normalizedPhone = $this->normalizePhoneNumber($login);

        // 1. Locate tenant directly or via linked user
        /** @var Tenant|null $tenant */
        $tenant = Tenant::query()
            ->where('email', strtolower($login))
            ->orWhere('phone', $login)
            ->orWhere('phone', $normalizedPhone)
            ->first();

        if (! $tenant) {
            $user = User::query()
                ->where('email', strtolower($login))
                ->orWhere('phone', $login)
                ->orWhere('phone', $normalizedPhone)
                ->first();

            if ($user && $user->isOwner()) {
                throw ValidationException::withMessages([
                    'login' => ['Administrator accounts must reset their password via the web dashboard.'],
                ]);
            }

            if ($user && $user->hasTenantProfile()) {
                $tenant = $user->tenant;
            }
        }

        if (! $tenant) {
            throw ValidationException::withMessages([
                'login' => ['No tenant account found matching this phone number or email address.'],
            ]);
        }

        if (! $tenant->is_active) {
            throw ValidationException::withMessages([
                'login' => ['This account has been deactivated. Please contact support.'],
            ]);
        }

        // Determine delivery channel if not explicitly specified
        if (blank($channel)) {
            $channel = str_contains($login, '@') ? 'email' : 'whatsapp';
        } else {
            $channel = strtolower(trim($channel));
        }

        if (! in_array($channel, ['whatsapp', 'email'], true)) {
            $channel = 'whatsapp';
        }

        if ($channel === 'whatsapp') {
            $target = $this->normalizePhoneNumber($tenant->phone ?? '');
            if (blank($target)) {
                throw ValidationException::withMessages([
                    'phone' => ['This account does not have a registered phone number for WhatsApp verification.'],
                ]);
            }
        } else {
            $target = strtolower(trim($tenant->email ?? $tenant->user?->email ?? ''));
            if (blank($target) || ! filter_var($target, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([
                    'email' => ['This account does not have a valid registered email address.'],
                ]);
            }
        }

        // Rate limit: 60 seconds cooldown per target
        $cooldownKey = "pw_reset_cooldown_{$channel}_{$target}";
        if (Cache::has($cooldownKey)) {
            $secondsRemaining = max(1, Cache::get($cooldownKey) - time());
            throw ValidationException::withMessages([
                'otp' => ["Please wait {$secondsRemaining} seconds before requesting a new password reset code."],
            ]);
        }

        $resetToken = 'pw_reset_' . Str::random(32);
        $otp = (string) random_int(100000, 999999);

        $payload = [
            'reset_token' => $resetToken,
            'tenant_id' => $tenant->id,
            'target' => $target,
            'channel' => $channel,
            'otp' => $otp,
            'created_at' => now()->timestamp,
        ];

        // Store session for 10 minutes
        Cache::put("pw_reset_{$resetToken}", $payload, now()->addMinutes(10));
        Cache::put("pw_reset_target_{$target}", $resetToken, now()->addMinutes(10));
        Cache::put("pw_reset_target_{$login}", $resetToken, now()->addMinutes(10));
        if ($normalizedPhone) {
            Cache::put("pw_reset_target_{$normalizedPhone}", $resetToken, now()->addMinutes(10));
        }
        Cache::put($cooldownKey, time() + 60, now()->addSeconds(60));

        // Dispatch OTP code
        $dispatch = $this->dispatchOtpCode($target, $channel, $otp);

        $result = [
            'reset_token' => $resetToken,
            'channel' => $channel,
            'target' => $target,
            'sent' => $dispatch['sent'],
            'driver' => $dispatch['driver'],
            'is_mock' => $dispatch['is_mock'],
            'delivery_warning' => $dispatch['warning'],
            'delivery_error' => $dispatch['error'],
        ];

        if (config('app.debug')) {
            $result['debug_otp'] = $otp;
        }

        return $result;
    }

    /**
     * Reset tenant password with verified OTP code.
     *
     * @throws ValidationException
     */
    public function resetPasswordWithOtp(string $identifier, string $code, string $newPassword, ?string $deviceName = null): array
    {
        $identifier = trim($identifier);
        $code = trim($code);
        $normalized = $this->normalizePhoneNumber($identifier);

        $token = Cache::get("pw_reset_target_{$identifier}")
            ?? Cache::get("pw_reset_target_{$normalized}")
            ?? $identifier;

        $session = Cache::get("pw_reset_{$token}");

        if (! $session || empty($session['tenant_id'])) {
            throw ValidationException::withMessages([
                'otp' => ['No active password reset request found, or the verification code has expired.'],
            ]);
        }

        if ($code !== (string) $session['otp']) {
            throw ValidationException::withMessages([
                'otp' => ['The verification code is invalid.'],
            ]);
        }

        /** @var Tenant|null $tenant */
        $tenant = Tenant::query()->find($session['tenant_id']);
        if (! $tenant) {
            throw ValidationException::withMessages([
                'otp' => ['The account associated with this request could not be found.'],
            ]);
        }

        // Update password on Tenant and linked User
        $hashedPassword = Hash::make($newPassword);
        $tenant->forceFill(['password' => $hashedPassword])->saveQuietly();

        if ($tenant->user) {
            $tenant->user->forceFill(['password' => $hashedPassword])->saveQuietly();
        }

        // Revoke all previous Sanctum API tokens for security
        if (method_exists($tenant, 'tokens')) {
            $tenant->tokens()->delete();
        }
        if ($tenant->user && method_exists($tenant->user, 'tokens')) {
            $tenant->user->tokens()->delete();
        }

        // Invalidate OTP cache
        Cache::forget("pw_reset_{$token}");
        Cache::forget("pw_reset_target_{$session['target']}");
        Cache::forget("pw_reset_target_{$identifier}");

        // Generate fresh Sanctum bearer token
        $deviceName = $deviceName ?: 'api-client';
        $newToken = $tenant->createToken($deviceName)->plainTextToken;

        return [
            'tenant' => $tenant->fresh(),
            'token' => $newToken,
            'channel' => $session['channel'],
            'target' => $session['target'],
        ];
    }
}
