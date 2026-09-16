<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Api\v1\Concerns\FormatsUserResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ForgotPasswordRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
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

        // 1. Direct Form-Level Registration (Submit with WhatsApp OTP)
        if (! empty($validated['otp'])) {
            $regResult = $this->otpService->registerWithPhoneOtp($validated);
            /** @var Tenant $tenant */
            $tenant = $regResult['user'];

            $message = ! empty($regResult['was_converted'])
                ? 'Akun penyewa berhasil diverifikasi dan diaktifkan!'
                : 'Pendaftaran berhasil! Nomor WhatsApp Anda telah diverifikasi.';

            $response = [
                'message' => $message,
                'token' => $regResult['token'],
                'phone_verified' => true,
                'user' => $this->formatUserResponse($tenant),
            ];

            return response()->json($response, 201);
        }

        // 2. Staged Pending Registration (Pre-OTP generation)
        $otpChannel = $validated['otp_channel'] ?? (! empty($validated['phone']) ? 'whatsapp' : 'email');

        $result = $this->otpService->createPendingRegistration($validated, $otpChannel);

        $message = $result['sent']
            ? 'Verification code sent. Please submit the OTP code to complete registration.'
            : 'Registration session created, but OTP delivery failed (' . ($result['delivery_error'] ?? 'delivery error') . '). Check configuration or resend code.';

        $response = [
            'message' => $message,
            'registration_token' => $result['registration_token'],
            'otp_sent' => $result['sent'],
            'otp_channel' => $result['channel'],
            'target' => $result['target'],
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

        return response()->json($response, 201);
    }

    /**
     * Check comprehensive registration & dual-channel OTP verification status (WhatsApp & Email).
     */
    public function checkStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['nullable', 'string', 'max:255'],
            'login' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:25'],
            'registration_token' => ['nullable', 'string', 'max:255'],
        ]);

        $identifier = $request->user('sanctum')
            ?? $request->user()
            ?? $validated['registration_token']
            ?? $validated['identifier']
            ?? $validated['login']
            ?? $validated['email']
            ?? $validated['phone']
            ?? null;

        if (blank($identifier)) {
            throw ValidationException::withMessages([
                'login' => ['Please provide an email, phone number, registration token, or Bearer token.'],
            ]);
        }

        $result = $this->otpService->checkStatus($identifier);

        return response()->json($result);
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

        if (! $tenant->hasVerifiedPhone()) {
            return response()->json([
                'message' => 'Nomor WhatsApp Anda belum diverifikasi. Silakan verifikasi nomor WhatsApp Anda terlebih dahulu.',
                'phone_verified' => false,
                'phone' => $tenant->phone,
                'requires_verification' => true,
            ], 403);
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

    /**
     * Verify tenant email via signed verification link.
     */
    public function verifyEmail(Request $request)
    {
        if (! $request->hasValidSignature()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Tautan verifikasi email tidak valid atau telah kedaluwarsa.',
                    'verified' => false,
                ], 403);
            }

            return response("<div style='font-family: sans-serif; text-align: center; padding: 48px;'>
                <h2 style='color: #ef4444;'>Tautan Kedaluwarsa</h2>
                <p>Tautan verifikasi email ini tidak valid atau telah kedaluwarsa. Silakan minta tautan baru melalui aplikasi.</p>
            </div>", 403)->header('Content-Type', 'text/html');
        }

        $id = (int) $request->route('id', $request->query('id'));
        $hash = (string) $request->route('hash', $request->query('hash'));

        try {
            $tenant = $this->otpService->verifyEmailFromSignedLink($id, $hash);
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return response("<div style='font-family: sans-serif; text-align: center; padding: 48px;'>
                <h2 style='color: #ef4444;'>Verifikasi Gagal</h2>
                <p>{$e->getMessage()}</p>
            </div>", 422)->header('Content-Type', 'text/html');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Alamat email berhasil diverifikasi!',
                'verified' => true,
                'email_verified' => true,
                'user' => $this->formatUserResponse($tenant),
            ]);
        }

        $html = "<!DOCTYPE html>
        <html lang='id'>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Email Berhasil Diverifikasi - OpenKos</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 16px; box-sizing: border-box; }
                .card { background: #ffffff; max-width: 480px; width: 100%; padding: 40px 32px; border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.01); text-align: center; border: 1px solid #e2e8f0; }
                .icon { width: 64px; height: 64px; background: #dcfce7; color: #16a34a; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 32px; }
                h1 { font-size: 22px; font-weight: 700; color: #0f172a; margin: 0 0 12px; }
                p { font-size: 15px; color: #64748b; line-height: 1.6; margin: 0 0 24px; }
                .badge { display: inline-block; background: #f1f5f9; color: #334155; padding: 6px 14px; border-radius: 9999px; font-size: 13px; font-weight: 500; margin-bottom: 24px; }
            </style>
        </head>
        <body>
            <div class='card'>
                <div class='icon'>✓</div>
                <h1>Email Berhasil Diverifikasi!</h1>
                <p>Halo <strong>" . e($tenant->name) . "</strong>, alamat email Anda (<strong>" . e($tenant->email) . "</strong>) telah terverifikasi. Akun Anda kini aktif sepenuhnya.</p>
                <div class='badge'>Status Akun: Terverifikasi Penuh</div>
                <p style='font-size: 13px; color: #94a3b8; margin: 0;'>Anda dapat kembali ke aplikasi OpenKos untuk melanjutkan.</p>
            </div>
        </body>
        </html>";

        return response($html)->header('Content-Type', 'text/html');
    }

    /**
     * Resend email verification link.
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'string', 'email'],
            'login' => ['nullable', 'string'],
        ]);

        /** @var Tenant|null $tenant */
        $tenant = $request->user('sanctum') ?? $request->user();

        if (! $tenant) {
            $identifier = $validated['email'] ?? $validated['login'] ?? null;
            if (blank($identifier)) {
                throw ValidationException::withMessages([
                    'email' => ['Mohon masukkan alamat email atau login identifier.'],
                ]);
            }

            $tenant = Tenant::query()
                ->where('email', strtolower(trim($identifier)))
                ->orWhere('phone', $identifier)
                ->first();

            if (! $tenant) {
                throw ValidationException::withMessages([
                    'email' => ['Akun penyewa tidak ditemukan.'],
                ]);
            }
        }

        if ($tenant->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Alamat email ini sudah terverifikasi sebelumnya.',
                'email_verified' => true,
            ]);
        }

        $emailResult = $this->otpService->sendEmailVerificationLink($tenant);

        $response = [
            'message' => $emailResult['sent']
                ? 'Tautan verifikasi telah dikirimkan ke email Anda.'
                : 'Gagal mengirimkan email verifikasi (' . ($emailResult['error'] ?? 'delivery error') . ').',
            'sent' => $emailResult['sent'],
            'email' => $tenant->email,
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

    /**
     * Request a password reset OTP via WhatsApp or Email.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->otpService->sendPasswordResetOtp($validated['login'], $validated['channel'] ?? null);

        $channelLabel = $result['channel'] === 'whatsapp' ? 'WhatsApp' : 'email';
        $message = $result['sent']
            ? "Kode verifikasi reset password berhasil dikirimkan via {$channelLabel}."
            : "Permintaan reset password dibuat, namun pengiriman kode gagal (" . ($result['delivery_error'] ?? 'delivery error') . ').';

        $response = [
            'message' => $message,
            'reset_token' => $result['reset_token'],
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

    /**
     * Reset tenant password using the verified OTP code.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $identifier = $request->resolvedIdentifier();
        $code = $request->resolvedCode();
        $password = (string) $request->validated('password');
        $deviceName = $request->validated('device_name');

        $result = $this->otpService->resetPasswordWithOtp($identifier, $code, $password, $deviceName);

        return response()->json([
            'message' => 'Password berhasil direset! Anda telah otomatis masuk.',
            'token' => $result['token'],
            'user' => $this->formatUserResponse($result['tenant']),
        ]);
    }
}
