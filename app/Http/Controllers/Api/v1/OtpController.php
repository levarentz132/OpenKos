<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Api\v1\Concerns\FormatsUserResponse;
use App\Http\Controllers\Controller;
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

            $response = [
                'message' => "Verification code sent successfully via {$result['channel']}.",
                'registration_token' => $result['registration_token'],
                'channel' => $result['channel'],
                'target' => $result['target'],
                'sent' => $result['sent'],
            ];

            if (config('app.debug') && isset($result['debug_otp'])) {
                $response['debug_otp'] = $result['debug_otp'];
            }

            return response()->json($response);
        }

        // 2. Resend for existing tenant account
        $user = $this->resolveTenantUser($request, $validated);

        $channel = $validated['channel'];
        $target = $channel === 'whatsapp'
            ? ($validated['phone'] ?? $user->phone)
            : ($validated['email'] ?? $user->email);

        $result = $this->otpService->sendOtp($user, $channel, $target);

        $response = [
            'message' => "Verification code sent successfully via {$channel}.",
            'channel' => $result['channel'],
            'target' => $result['target'],
            'sent' => $result['sent'] ?? true,
        ];

        if (config('app.debug') && isset($result['debug_otp'])) {
            $response['debug_otp'] = $result['debug_otp'];
        }

        return response()->json($response);
    }

    /**
     * Verify OTP code submitted by the tenant.
     * Completes registration if verifying a pending registration session,
     * or updates phone_verified_at / email_verified_at for existing users.
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

        // 1. Verify pending registration session and create database records
        if ($identifier && $this->otpService->hasPendingRegistration($identifier)) {
            $result = $this->otpService->verifyPendingRegistration($identifier, $validated['code']);
            $freshUser = $result['user'];
            $deviceName = $validated['device_name'] ?? 'api-client';
            $token = $freshUser->createToken($deviceName)->plainTextToken;

            return response()->json([
                'message' => 'Account registered and verified successfully.',
                'verified' => true,
                'token' => $token,
                'channel' => $result['channel'],
                'phone_verified' => $freshUser->hasVerifiedPhone(),
                'email_verified' => ! is_null($freshUser->email_verified_at),
                'user' => $this->formatUserResponse($freshUser),
            ], 201);
        }

        // 2. Verify existing user
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
    protected function resolveTenantUser(Request $request, array $validated): User
    {
        /** @var User|null $user */
        $user = $request->user('sanctum') ?? $request->user();

        if (! $user) {
            $identifier = $validated['login'] ?? $validated['email'] ?? $validated['phone'] ?? null;

            if (blank($identifier)) {
                throw ValidationException::withMessages([
                    'login' => ['Please provide your email, phone number, or registration token.'],
                ]);
            }

            $normalizedPhone = $this->otpService->normalizePhoneNumber($identifier);

            $user = User::query()
                ->where('email', strtolower(trim($identifier)))
                ->orWhere('phone', $identifier)
                ->orWhere('phone', $normalizedPhone)
                ->first();

            if (! $user) {
                throw ValidationException::withMessages([
                    'login' => ['No account found with this email or phone number.'],
                ]);
            }
        }

        // Restrict OTP access strictly to tenants
        if ($user->isOwner() || ! $user->hasTenantProfile()) {
            throw ValidationException::withMessages([
                'login' => ['This OTP verification portal is reserved for tenant accounts only.'],
            ]);
        }

        return $user;
    }
}
