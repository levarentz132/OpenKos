<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Api\v1\Concerns\FormatsUserResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OtpVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     * Database records are NOT created until the OTP code is verified.
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
     * Authenticate tenant and issue personal access token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $login = trim($validated['login']);
        $normalizedPhone = $this->otpService->normalizePhoneNumber($login);

        // 1. First check tenants table directly
        /** @var Tenant|null $tenant */
        $tenant = Tenant::query()
            ->where('email', strtolower($login))
            ->orWhere('phone', $login)
            ->orWhere('phone', $normalizedPhone)
            ->first();

        // 2. Fallback check on users table for legacy tenant records
        if (! $tenant) {
            $user = User::query()
                ->where('email', strtolower($login))
                ->orWhere('phone', $login)
                ->orWhere('phone', $normalizedPhone)
                ->first();

            if ($user && $user->isOwner()) {
                throw ValidationException::withMessages([
                    'login' => ['This login portal is reserved for tenants only. Administrator accounts must log in via the web dashboard.'],
                ]);
            }

            if ($user && $user->hasTenantProfile()) {
                $tenant = $user->tenant;
            }
        }

        if (! $tenant || ! Hash::check($validated['password'], $tenant->password ?? $tenant->user?->password)) {
            throw ValidationException::withMessages([
                'login' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $tenant->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Your account has been deactivated. Please contact support.'],
            ]);
        }

        $tenant->forceFill(['last_login_at' => now()])->saveQuietly();

        $deviceName = $validated['device_name'] ?? 'api-client';
        $token = $tenant->createToken($deviceName)->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $this->formatUserResponse($tenant),
        ]);
    }

    /**
     * Get authenticated user profile and verification status.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Delete the authenticated tenant or user account.
     */
    public function destroy(Request $request): JsonResponse
    {
        /** @var User|Tenant $user */
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user instanceof User && $user->isOwner()) {
            $activeOwners = User::role(\App\Enums\Role::Owner->value)->where('is_active', true)->count();
            if ($activeOwners <= 1) {
                throw ValidationException::withMessages([
                    'account' => ['The last remaining administrator account cannot be deleted.'],
                ]);
            }
        }

        DB::transaction(function () use ($user) {
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }

            if ($user instanceof Tenant) {
                $user->delete();
            } elseif ($user instanceof User) {
                $user->tenant?->delete();
                $user->delete();
            }
        });

        return response()->json([
            'message' => 'Account deleted successfully.',
        ]);
    }

    /**
     * Delete any user account by ID (Administrator only).
     */
    public function deleteUser(Request $request, User $user): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        if (! $currentUser instanceof User || ! $currentUser->isOwner()) {
            abort(403, 'Only administrators can delete user accounts.');
        }

        if ($user->isOwner()) {
            $activeOwners = User::role(\App\Enums\Role::Owner->value)->where('is_active', true)->count();
            if ($activeOwners <= 1) {
                throw ValidationException::withMessages([
                    'user' => ['The last remaining administrator account cannot be deleted.'],
                ]);
            }
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->tenant?->delete();
            $user->delete();
        });

        return response()->json([
            'message' => 'User deleted successfully.',
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
