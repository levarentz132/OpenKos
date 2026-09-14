<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Api\v1\Concerns\FormatsUserResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\User;
use App\Services\OtpVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use FormatsUserResponse;

    public function __construct(
        protected OtpVerificationService $otpService,
    ) {}

    /**
     * Staged registration via API.
     * The User and Tenant accounts are NOT created in the database until the OTP is verified.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $otpChannel = $validated['otp_channel'] ?? (! empty($validated['phone']) ? 'whatsapp' : 'email');

        $result = $this->otpService->createPendingRegistration($validated, $otpChannel);

        $response = [
            'message' => 'Verification code sent. Please submit the OTP code to complete registration.',
            'registration_token' => $result['registration_token'],
            'otp_sent' => $result['sent'],
            'otp_channel' => $result['channel'],
            'target' => $result['target'],
        ];

        if (config('app.debug') && isset($result['debug_otp'])) {
            $response['debug_otp'] = $result['debug_otp'];
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
}
