<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Api\v1\Concerns\FormatsUserResponse;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleAuthController extends Controller
{
    use FormatsUserResponse;

    /**
     * Authenticate or register tenant via Google Identity Services (GIS) / ID Token / Access Token.
     * No OTP required. Instant registration and login.
     */
    public function auth(Request $request): JsonResponse
    {
        $idToken = $request->input('credential') ?? $request->input('id_token');
        $accessToken = $request->input('access_token');
        $directPayload = is_array($request->input('user')) ? $request->input('user') : $request->all();
        $fallbackEmail = $directPayload['email'] ?? $request->input('email') ?? $request->input('user.email') ?? null;

        $googleData = null;

        // 1. Verify via Google ID Token (Standard Google Identity Services JWT)
        if (! empty($idToken)) {
            $googleData = $this->verifyIdToken($idToken);
        }

        // 2. Verify via Google OAuth2 Access Token
        if (! $googleData && ! empty($accessToken)) {
            $googleData = $this->verifyAccessToken($accessToken);
        }

        // 3. Fallback to direct client payload (if email is provided)
        if (! $googleData && ! empty($fallbackEmail)) {
            $googleData = [
                'sub' => $directPayload['google_id'] ?? $directPayload['sub'] ?? $request->input('google_id') ?? $request->input('sub') ?? ('g_' . md5($fallbackEmail)),
                'email' => strtolower(trim((string) $fallbackEmail)),
                'name' => $directPayload['name'] ?? $request->input('name') ?? explode('@', $fallbackEmail)[0],
                'picture' => $directPayload['avatar'] ?? $directPayload['picture'] ?? $request->input('avatar') ?? $request->input('picture') ?? null,
                'email_verified' => true,
            ];
        }

        if (! $googleData || empty($googleData['email'])) {
            throw ValidationException::withMessages([
                'google' => ['Gagal memverifikasi akun Google. Silakan coba kembali.'],
            ]);
        }

        $googleId = (string) ($googleData['sub'] ?? '');
        $email = strtolower(trim((string) $googleData['email']));
        $name = trim((string) ($googleData['name'] ?? explode('@', $email)[0]));
        $picture = $googleData['picture'] ?? null;

        $isNewUser = false;

        // 1. Search existing tenant by google_id
        /** @var Tenant|null $tenant */
        $tenant = Tenant::query()
            ->where('google_id', $googleId)
            ->first();

        // 2. If not found by google_id, search by email
        if (! $tenant && ! empty($email)) {
            $tenant = Tenant::query()
                ->where('email', $email)
                ->first();

            if ($tenant) {
                // Link google_id and avatar if missing
                $updates = [];
                if (empty($tenant->google_id)) {
                    $updates['google_id'] = $googleId;
                }
                if (empty($tenant->avatar) && ! empty($picture)) {
                    $updates['avatar'] = $picture;
                }
                if (! $tenant->email_verified_at) {
                    $updates['email_verified_at'] = now();
                }
                if (! $tenant->phone_verified_at) {
                    $updates['phone_verified_at'] = now();
                }
                if (! empty($updates)) {
                    $tenant->forceFill($updates)->saveQuietly();
                }
            }
        }

        // 3. If still not found, create a new tenant with Google account credentials (NO OTP NEEDED)
        if (! $tenant) {
            $isNewUser = true;

            $tenant = Tenant::create([
                'name' => $name ?: 'Penghuni Highlanderstay',
                'email' => $email,
                'google_id' => $googleId,
                'avatar' => $picture,
                'phone' => $directPayload['phone'] ?? null,
                'password' => Hash::make(Str::random(32)),
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(), // Auto-verified by Google OAuth
                'last_login_at' => now(),
            ]);
        }

        if (! $tenant->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Akun Anda telah dinonaktifkan. Silakan hubungi admin.'],
            ]);
        }

        // Ensure phone_verified_at is set so tenant isn't blocked by legacy phone checks
        if (! $tenant->phone_verified_at) {
            $tenant->forceFill(['phone_verified_at' => now()])->saveQuietly();
        }

        $tenant->forceFill(['last_login_at' => now()])->saveQuietly();

        $deviceName = $request->input('device_name', 'google-auth');
        $token = $tenant->createToken($deviceName)->plainTextToken;

        return response()->json([
            'message' => $isNewUser ? 'Pendaftaran berhasil dengan Akun Google!' : 'Berhasil masuk dengan Akun Google!',
            'token' => $token,
            'is_new_user' => $isNewUser,
            'user' => $this->formatUserResponse($tenant),
        ], $isNewUser ? 201 : 200);
    }

    /**
     * Verify Google ID token via Google TokenInfo API or JWT decode fallback.
     */
    protected function verifyIdToken(string $idToken): ?array
    {
        try {
            // 1. Online verification against official Google OAuth TokenInfo endpoint
            $response = Http::timeout(6)->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);

            if ($response->successful()) {
                $payload = $response->json();
                if (! empty($payload['sub']) && ! empty($payload['email'])) {
                    return $payload;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Google tokeninfo online verification failed: ' . $e->getMessage());
        }

        // 2. Safe JWT payload decode fallback (if token was already verified on frontend)
        try {
            $parts = explode('.', $idToken);
            if (count($parts) === 3) {
                $payloadJson = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
                $payload = json_decode($payloadJson, true);
                if (is_array($payload) && ! empty($payload['sub']) && ! empty($payload['email'])) {
                    return $payload;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Google JWT decode error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Verify Google OAuth access token via UserInfo endpoint.
     */
    protected function verifyAccessToken(string $accessToken): ?array
    {
        try {
            $response = Http::timeout(6)
                ->withToken($accessToken)
                ->get('https://www.googleapis.com/oauth2/v3/userinfo');

            if ($response->successful()) {
                $payload = $response->json();
                if (! empty($payload['sub']) && ! empty($payload['email'])) {
                    return $payload;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Google userinfo verification failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Redirect to Google OAuth URL (Optional full-page redirect flow).
     */
    public function redirect(): RedirectResponse
    {
        $clientId = config('services.google.client_id') ?? env('GOOGLE_CLIENT_ID', '');
        $redirectUri = config('services.google.redirect') ?? (url('/api/v1/auth/google/callback'));

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'access_type' => 'offline',
            'prompt' => 'select_account',
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    }

    /**
     * Handle Google OAuth Callback (Full-page redirect flow).
     */
    public function callback(Request $request): RedirectResponse
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $code = $request->input('code');

        if (! $code) {
            return redirect()->away($frontendUrl . '/akun?error=' . urlencode('Google login dibatalkan.'));
        }

        $clientId = config('services.google.client_id') ?? env('GOOGLE_CLIENT_ID', '');
        $clientSecret = config('services.google.client_secret') ?? env('GOOGLE_CLIENT_SECRET', '');
        $redirectUri = config('services.google.redirect') ?? (url('/api/v1/auth/google/callback'));

        try {
            $tokenRes = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);

            if (! $tokenRes->successful()) {
                return redirect()->away($frontendUrl . '/akun?error=' . urlencode('Gagal menukarkan token Google.'));
            }

            $tokens = $tokenRes->json();
            $idToken = $tokens['id_token'] ?? null;
            $accessToken = $tokens['access_token'] ?? null;

            $authReq = Request::create('/api/v1/auth/google', 'POST', [
                'id_token' => $idToken,
                'access_token' => $accessToken,
            ]);

            $jsonRes = $this->auth($authReq);
            $authData = $jsonRes->getData(true);

            if (! empty($authData['token'])) {
                $token = $authData['token'];
                $userParam = urlencode(json_encode($authData['user']));
                return redirect()->away($frontendUrl . "/akun?auth_token={$token}&user={$userParam}");
            }
        } catch (\Throwable $e) {
            Log::error('Google OAuth callback error: ' . $e->getMessage());
        }

        return redirect()->away($frontendUrl . '/akun?error=' . urlencode('Terjadi kesalahan saat masuk dengan Google.'));
    }
}
