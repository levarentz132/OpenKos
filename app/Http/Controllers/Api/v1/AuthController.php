<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OtpVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected OtpVerificationService $otpService,
    ) {}

    /**
     * Register a new account via API.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $phone = ! empty($validated['phone'])
            ? $this->otpService->normalizePhoneNumber($validated['phone'])
            : null;

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'phone' => $phone,
            'password' => $validated['password'],
            'is_active' => true,
        ]);

        // Automatically create a tenant profile for mobile/app users
        Tenant::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'phone' => $phone ?? '',
            'is_active' => true,
        ]);

        $deviceName = $validated['device_name'] ?? 'api-client';
        $token = $user->createToken($deviceName)->plainTextToken;

        $otpChannel = $validated['otp_channel'] ?? (! empty($phone) ? 'whatsapp' : 'email');
        $otpSent = false;
        $otpResult = null;
        try {
            $otpResult = $this->otpService->sendOtp($user, $otpChannel);
            $otpSent = $otpResult['sent'] ?? true;
        } catch (\Throwable) {
            // If notification fails in dev/test, registration still succeeds
            $otpSent = false;
        }

        $response = [
            'message' => 'Account registered successfully. Please verify your OTP code to complete registration.',
            'token' => $token,
            'otp_sent' => $otpSent,
            'otp_channel' => $otpChannel,
            'user' => $this->formatUserResponse($user),
        ];

        if (config('app.debug') && isset($otpResult['debug_otp'])) {
            $response['debug_otp'] = $otpResult['debug_otp'];
        }

        return response()->json($response, 201);
    }

    /**
     * Authenticate user and issue personal access token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $login = trim($validated['login']);

        // Check if login identifier is email or phone
        $normalizedPhone = $this->otpService->normalizePhoneNumber($login);

        $user = User::query()
            ->where('email', strtolower($login))
            ->orWhere('phone', $login)
            ->orWhere('phone', $normalizedPhone)
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Your account has been deactivated. Please contact support.'],
            ]);
        }

        // Restrict API login to tenants only (reject owners/admins)
        if ($user->isOwner() || ! $user->hasTenantProfile()) {
            throw ValidationException::withMessages([
                'login' => ['This login portal is reserved for tenants only. Administrator accounts must log in via the web dashboard.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $deviceName = $validated['device_name'] ?? 'api-client';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Get authenticated user profile and verification status.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => $this->formatUserResponse($user->fresh(['tenant'])),
        ]);
    }

    /**
     * Revoke current API token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Format consistent user response with phone and email verification metadata.
     */
    protected function formatUserResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => ! is_null($user->email_verified_at),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'phone' => $user->phone,
            'phone_verified' => $user->hasVerifiedPhone(),
            'phone_verified_at' => $user->phone_verified_at?->toIso8601String(),
            'is_active' => $user->is_active,
            'roles' => $user->roles->pluck('name')->values()->all(),
            'has_tenant_profile' => $user->hasTenantProfile(),
            'tenant' => $user->tenant ? [
                'id' => $user->tenant->id,
                'name' => $user->tenant->name,
                'phone' => $user->tenant->phone,
                'id_card_number' => $user->tenant->id_card_number,
            ] : null,
        ];
    }
}
