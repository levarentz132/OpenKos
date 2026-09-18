<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Api\v1\Concerns\FormatsUserResponse;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OtpVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OtpController extends Controller
{
    use FormatsUserResponse;

    public function __construct(
        protected OtpVerificationService $otpService,
    ) {}

    /**
     * Send or resend OTP verification code via WhatsApp or Email.
     * Accessible with Bearer token, registration_token, OR login/email/phone.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['required', 'string', 'in:whatsapp,email'],
            'registration_token' => ['nullable', 'string'],
            'login' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        $identifier = $validated['registration_token']
            ?? $validated['login']
            ?? $validated['phone']
            ?? $validated['email']
            ?? null;

        // 1. Resend for pending registration session
        if ($identifier && $this->otpService->hasPendingRegistration($identifier)) {
            $result = $this->otpService->resendPendingRegistrationOtp($identifier, $validated['channel']);

            $message = $result['sent']
                ? "Verification code sent successfully via {$result['channel']}."
                : "Failed to send verification code via {$result['channel']} (" . ($result['delivery_error'] ?? 'delivery error') . ').';

            $response = [
                'message' => $message,
                'registration_token' => $result['registration_token'],
                'channel' => $result['channel'],
                'target' => $result['target'],
                'sent' => $result['sent'],
                'driver' => $result['driver'] ?? null,
            ];

            if (! empty($result['delivery_warning'])) {
                $response['delivery_warning'] = $result['delivery_warning'];
            }

            if (! empty($result['delivery_error'])) {
                $response['delivery_error'] = $result['delivery_error'];
            }

            if (config('app.debug') && isset($result['debug_otp'])) {
                $response['debug_otp'] = $result['debug_otp'];
            }

            return response()->json($response);
        }

        if (! empty($validated['registration_token'])) {
            throw ValidationException::withMessages([
                'registration_token' => ['Sesi pendaftaran telah kadaluarsa. Silakan lakukan pendaftaran kembali.'],
            ]);
        }

        // 2. Direct registration form OTP request for WhatsApp (prior to account creation)
        if ($validated['channel'] === 'whatsapp' && ! empty($validated['phone']) && ! $request->user('sanctum') && ! $request->user()) {
            $result = $this->otpService->sendRegistrationPhoneOtp($validated['phone']);

            $message = $result['sent']
                ? 'Verification code sent successfully via whatsapp.'
                : 'Failed to send verification code via whatsapp (' . ($result['delivery_error'] ?? 'delivery error') . ').';

            $response = [
                'message' => $message,
                'channel' => 'whatsapp',
                'target' => $result['target'],
                'sent' => $result['sent'],
                'driver' => $result['driver'] ?? null,
            ];

            if (! empty($result['delivery_warning'])) {
                $response['delivery_warning'] = $result['delivery_warning'];
            }

            if (! empty($result['delivery_error'])) {
                $response['delivery_error'] = $result['delivery_error'];
            }

            if (config('app.debug') && isset($result['debug_otp'])) {
                $response['debug_otp'] = $result['debug_otp'];
            }

            return response()->json($response);
        }

        // 3. Email verification link dispatch for tenant accounts (verification link instead of OTP)
        if ($validated['channel'] === 'email') {
            $user = $this->resolveTenantUser($request, $validated);

            if ($user instanceof Tenant) {
                if ($user->hasVerifiedEmail()) {
                    return response()->json([
                        'message' => 'Alamat email ini sudah terverifikasi.',
                        'channel' => 'email',
                        'target' => $user->email,
                        'sent' => false,
                        'email_verified' => true,
                    ]);
                }

                $emailResult = $this->otpService->sendEmailVerificationLink($user);

                $response = [
                    'message' => $emailResult['sent']
                        ? 'Tautan verifikasi telah dikirimkan ke email Anda.'
                        : 'Gagal mengirimkan email verifikasi (' . ($emailResult['error'] ?? 'delivery error') . ').',
                    'channel' => 'email',
                    'target' => $user->email,
                    'sent' => $emailResult['sent'],
                    'driver' => $emailResult['driver'] ?? null,
                ];

                if (! empty($emailResult['warning'])) {
                    $response['delivery_warning'] = $emailResult['warning'];
                }

                if (! empty($emailResult['error'])) {
                    $response['delivery_error'] = $emailResult['error'];
                }

                if (config('app.debug') && ! empty($emailResult['url'])) {
                    $response['debug_verification_url'] = $emailResult['url'];
                }

                return response()->json($response);
            }
        }

        // 4. Resend for existing tenant account
        $user = $this->resolveTenantUser($request, $validated);

        $channel = $validated['channel'];
        $target = $channel === 'whatsapp'
            ? ($validated['phone'] ?? $user->phone)
            : ($validated['email'] ?? $user->email);

        $result = $this->otpService->sendOtp($user, $channel, $target);

        $message = $result['sent']
            ? "Verification code sent successfully via {$channel}."
            : "Failed to send verification code via {$channel} (" . ($result['delivery_error'] ?? 'delivery error') . ').';

        $response = [
            'message' => $message,
            'channel' => $result['channel'],
            'target' => $result['target'],
            'sent' => $result['sent'] ?? true,
            'driver' => $result['driver'] ?? null,
        ];

        if (! empty($result['delivery_warning'])) {
            $response['delivery_warning'] = $result['delivery_warning'];
        }

        if (! empty($result['delivery_error'])) {
            $response['delivery_error'] = $result['delivery_error'];
        }

        if (config('app.debug') && isset($result['debug_otp'])) {
            $response['debug_otp'] = $result['debug_otp'];
        }

        return response()->json($response);
    }

    /**
     * Verify OTP code submitted by the tenant.
     * Completes registration if verifying a pending registration session,
     * or updates phone_verified_at / email_verified_at for existing accounts.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:10'],
            'registration_token' => ['nullable', 'string'],
            'channel' => ['nullable', 'string', 'in:whatsapp,email'],
            'login' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $identifier = $validated['registration_token']
            ?? $validated['login']
            ?? $validated['phone']
            ?? $validated['email']
            ?? null;

        // 1. Verify pending registration session and create ONLY Tenant database record
        if ($identifier && $this->otpService->hasPendingRegistration($identifier)) {
            $result = $this->otpService->verifyPendingRegistration($identifier, $validated['code'], $validated['channel'] ?? null);
            /** @var Tenant $tenant */
            $tenant = $result['user'];
            $deviceName = $validated['device_name'] ?? 'api-client';
            $token = $tenant->createToken($deviceName)->plainTextToken;

            return response()->json([
                'message' => 'Account registered and verified successfully.',
                'verified' => true,
                'token' => $token,
                'channel' => $result['channel'],
                'phone_verified' => $tenant->hasVerifiedPhone(),
                'email_verified' => ! is_null($tenant->email_verified_at),
                'user' => $this->formatUserResponse($tenant),
            ], 201);
        }

        // If a registration token was passed but session in cache is missing / expired
        if (! empty($validated['registration_token'])) {
            throw ValidationException::withMessages([
                'code' => ['Sesi verifikasi OTP telah kadaluarsa atau tidak valid. Silakan kirim ulang OTP atau daftar kembali.'],
            ]);
        }

        // 2. Verify existing user or tenant
        $user = $this->resolveTenantUser($request, $validated);

        $result = $this->otpService->verifyOtp(
            $user,
            $validated['code'],
            $validated['channel'] ?? null
        );

        $freshUser = $result['user'];
        $deviceName = $validated['device_name'] ?? 'api-client';
        $token = $request->bearerToken() ? null : $freshUser->createToken($deviceName)->plainTextToken;

        return response()->json([
            'message' => ucfirst($result['channel']).' verified successfully.',
            'verified' => true,
            'token' => $token,
            'channel' => $result['channel'],
            'phone_verified' => $freshUser->hasVerifiedPhone(),
            'email_verified' => ! is_null($freshUser->email_verified_at),
            'user' => $this->formatUserResponse($freshUser),
        ]);
    }

    /**
     * Resolve the target tenant user from authenticated session or login identifier.
     *
     * @throws ValidationException
     */
    protected function resolveTenantUser(Request $request, array $validated): User|Tenant
    {
        /** @var User|Tenant|null $user */
        $user = $request->user('sanctum') ?? $request->user();

        if (! $user) {
            $identifier = $validated['login'] ?? $validated['email'] ?? $validated['phone'] ?? $validated['registration_token'] ?? null;

            if (blank($identifier)) {
                throw ValidationException::withMessages([
                    'login' => ['Harap masukkan email atau nomor WhatsApp akun Anda.'],
                ]);
            }

            $normalizedPhone = $this->otpService->normalizePhoneNumber($identifier);

            // Check Tenant table directly first
            $tenant = Tenant::query()
                ->where('email', strtolower(trim($identifier)))
                ->orWhere('phone', $identifier)
                ->orWhere('phone', $normalizedPhone)
                ->first();

            if ($tenant) {
                return $tenant;
            }

            // Fallback to User table for legacy users
            $user = User::query()
                ->where('email', strtolower(trim($identifier)))
                ->orWhere('phone', $identifier)
                ->orWhere('phone', $normalizedPhone)
                ->first();

            if (! $user) {
                throw ValidationException::withMessages([
                    'login' => ['Akun penyewa tidak ditemukan. Silakan periksa kembali email atau nomor WhatsApp Anda.'],
                ]);
            }
        }

        if ($user instanceof User && ($user->isOwner() || ! $user->hasTenantProfile())) {
            throw ValidationException::withMessages([
                'login' => ['Portal verifikasi OTP ini khusus untuk akun penyewa.'],
            ]);
        }

        return $user;
    }
}
