<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OtpController extends Controller
{
    public function __construct(
        protected OtpVerificationService $otpService,
    ) {}

    /**
     * Send or resend OTP verification code via WhatsApp or Email.
     * Accessible with Bearer token OR by specifying login/email/phone.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['required', 'string', 'in:whatsapp,email'],
            'login' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

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
     * Accessible with Bearer token OR by specifying login/email/phone + code.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:10'],
            'channel' => ['nullable', 'string', 'in:whatsapp,email'],
            'login' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

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
            'user' => [
                'id' => $freshUser->id,
                'name' => $freshUser->name,
                'email' => $freshUser->email,
                'phone' => $freshUser->phone,
                'phone_verified' => $freshUser->hasVerifiedPhone(),
                'email_verified' => ! is_null($freshUser->email_verified_at),
                'is_active' => $freshUser->is_active,
            ],
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
                    'login' => ['Please provide your email or phone number, or authenticate with an API token.'],
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
                    'login' => ['No tenant account found matching these details.'],
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
