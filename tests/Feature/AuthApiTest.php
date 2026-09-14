<?php

use App\Models\User;
use App\Services\WhatsAppManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;

beforeEach(function () {
    // Mock WhatsAppManager so tests don't send real messages
    $this->mock(WhatsAppManager::class, function (MockInterface $mock) {
        $mock->shouldReceive('send')->byDefault()->andReturnNull();
    });
});

test('api user can register with name, email, phone, and password', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '081234567890',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'mobile-test',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'message',
            'token',
            'otp_sent',
            'user' => [
                'id',
                'name',
                'email',
                'phone',
                'phone_verified',
                'phone_verified_at',
                'is_active',
                'has_tenant_profile',
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
        'phone' => '6281234567890',
    ]);

    $user = User::where('email', 'john@example.com')->first();
    expect($user->hasTenantProfile())->toBeTrue();
    expect($user->hasVerifiedPhone())->toBeFalse();
});

test('registration fails if email belongs to an admin account', function () {
    $admin = User::factory()->owner()->create([
        'email' => 'existingadmin@example.com',
    ]);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'New Tenant',
        'email' => 'existingadmin@example.com',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    $error = $response->json('errors.email.0');
    expect($error)->toContain('administrator account');
});

test('api user can log in with email and password', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'phone' => '6281234567891',
        'password' => Hash::make('secret123'),
        'is_active' => true,
    ]);
    \App\Models\Tenant::factory()->create(['user_id' => $user->id]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'jane@example.com',
        'password' => 'secret123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'token',
            'user' => ['id', 'email', 'phone', 'phone_verified'],
        ]);
});

test('api user can log in with phone number and password', function () {
    $user = User::factory()->create([
        'email' => 'phoneuser@example.com',
        'phone' => '628999888777',
        'password' => Hash::make('secret123'),
        'is_active' => true,
    ]);
    \App\Models\Tenant::factory()->create(['user_id' => $user->id]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => '08999888777', // Will normalize to 628999888777
        'password' => 'secret123',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.email', 'phoneuser@example.com');
});

test('admin or owner is rejected from logging in via tenant api', function () {
    $admin = User::factory()->owner()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('adminpassword'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'admin@example.com',
        'password' => 'adminpassword',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['login']);
});

test('api login fails with incorrect credentials', function () {
    $user = User::factory()->create([
        'email' => 'wrongpass@example.com',
        'password' => Hash::make('correctpassword'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'wrongpass@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['login']);
});

test('authenticated user can view their profile via me endpoint', function () {
    $user = User::factory()->create([
        'email' => 'profile@example.com',
        'phone' => '628111222333',
    ]);

    $token = $user->createToken('test-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('user.email', 'profile@example.com')
        ->assertJsonPath('user.phone', '628111222333');
});

test('user can send and verify phone OTP', function () {
    $user = User::factory()->create([
        'email' => 'otpuser@example.com',
        'phone' => '628555444333',
        'phone_verified_at' => null,
    ]);

    $token = $user->createToken('test-token')->plainTextToken;

    // 1. Send OTP
    $sendResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/phone/send-otp', [
            'phone' => '08555444333',
        ]);

    $sendResponse->assertOk()
        ->assertJsonPath('message', 'Verification code sent successfully.');

    // Inspect cached OTP
    $cached = Cache::get("phone_otp_{$user->id}");
    expect($cached)->not->toBeNull();
    $code = $cached['otp'];

    // 2. Verify wrong OTP fails
    $failResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/phone/verify-otp', [
            'code' => '000000',
        ]);
    $failResponse->assertStatus(422);

    // 3. Verify correct OTP succeeds
    $successResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/phone/verify-otp', [
            'code' => $code,
        ]);

    $successResponse->assertOk()
        ->assertJsonPath('phone_verified', true);

    $user->refresh();
    expect($user->hasVerifiedPhone())->toBeTrue();
    expect($user->phone_verified_at)->not->toBeNull();
});

test('user can register and choose email otp channel', function () {
    \Illuminate\Support\Facades\Mail::fake();

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Sarah Connor',
        'email' => 'sarah@example.com',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
        'otp_channel' => 'email',
    ]);

    $response->assertCreated()
        ->assertJsonPath('otp_sent', true)
        ->assertJsonPath('otp_channel', 'email');

    $user = User::where('email', 'sarah@example.com')->first();
    $cached = Cache::get("otp_{$user->id}_email");
    expect($cached)->not->toBeNull();
});

test('user can send and verify email OTP via dual-channel endpoint', function () {
    \Illuminate\Support\Facades\Mail::fake();

    $user = User::factory()->create([
        'email' => 'emailotp@example.com',
        'email_verified_at' => null,
    ]);
    \App\Models\Tenant::factory()->create(['user_id' => $user->id]);

    $token = $user->createToken('test-token')->plainTextToken;

    // 1. Send Email OTP
    $sendResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/otp/send', [
            'channel' => 'email',
        ]);

    $sendResponse->assertOk()
        ->assertJsonPath('channel', 'email');

    $cached = Cache::get("otp_{$user->id}_email");
    expect($cached)->not->toBeNull();
    $code = $cached['otp'];

    // 2. Verify Email OTP
    $verifyResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/otp/verify', [
            'code' => $code,
            'channel' => 'email',
        ]);

    $verifyResponse->assertOk()
        ->assertJsonPath('verified', true)
        ->assertJsonPath('email_verified', true);

    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull();
});

test('user can send and verify whatsapp OTP via dual-channel endpoint', function () {
    $user = User::factory()->create([
        'email' => 'waotp@example.com',
        'phone' => '6281234567800',
        'phone_verified_at' => null,
    ]);
    \App\Models\Tenant::factory()->create(['user_id' => $user->id]);

    $token = $user->createToken('test-token')->plainTextToken;

    // 1. Send WhatsApp OTP
    $sendResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/otp/send', [
            'channel' => 'whatsapp',
        ]);

    $sendResponse->assertOk()
        ->assertJsonPath('channel', 'whatsapp');

    $cached = Cache::get("otp_{$user->id}_whatsapp");
    expect($cached)->not->toBeNull();
    $code = $cached['otp'];

    // 2. Verify WhatsApp OTP
    $verifyResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/otp/verify', [
            'code' => $code,
        ]);

    $verifyResponse->assertOk()
        ->assertJsonPath('verified', true)
        ->assertJsonPath('phone_verified', true);

    $user->refresh();
    expect($user->hasVerifiedPhone())->toBeTrue();
});

test('unauthenticated user can request and verify OTP using phone number', function () {
    $user = User::factory()->create([
        'email' => 'unauthotp@example.com',
        'phone' => '628991122334',
        'phone_verified_at' => null,
    ]);
    \App\Models\Tenant::factory()->create(['user_id' => $user->id]);

    // Send without Authorization header
    $sendResponse = $this->postJson('/api/v1/auth/otp/send', [
        'login' => '08991122334',
        'channel' => 'whatsapp',
    ]);

    $sendResponse->assertOk()
        ->assertJsonPath('channel', 'whatsapp')
        ->assertJsonPath('target', '628991122334');

    $cached = Cache::get("otp_{$user->id}_whatsapp");
    expect($cached)->not->toBeNull();
    $code = $cached['otp'];

    // Verify without Authorization header - should issue a token
    $verifyResponse = $this->postJson('/api/v1/auth/otp/verify', [
        'login' => '08991122334',
        'code' => $code,
    ]);

    $verifyResponse->assertOk()
        ->assertJsonPath('verified', true)
        ->assertJsonPath('phone_verified', true);

    expect($verifyResponse->json('token'))->not->toBeNull();
});

test('registration requires phone number when selecting whatsapp otp channel', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'No Phone Tenant',
        'email' => 'nophone@example.com',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
        'otp_channel' => 'whatsapp',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['phone']);

    expect($response->json('errors.phone.0'))->toContain('WhatsApp');
});

test('admin cannot use tenant otp verification endpoint', function () {
    $admin = User::factory()->owner()->create([
        'email' => 'owner@example.com',
        'phone' => '62811112222',
    ]);

    $response = $this->postJson('/api/v1/auth/otp/send', [
        'login' => 'owner@example.com',
        'channel' => 'email',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['login']);

    expect($response->json('errors.login.0'))->toContain('tenant accounts only');
});

test('authenticated user can log out and invalidate token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('logout-test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout');

    $response->assertOk()
        ->assertJsonPath('message', 'Logged out successfully.');

    expect($user->tokens()->count())->toBe(0);
});
