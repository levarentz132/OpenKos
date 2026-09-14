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

test('api user can log in with email and password', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'phone' => '6281234567891',
        'password' => Hash::make('secret123'),
        'is_active' => true,
    ]);

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

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => '08999888777', // Will normalize to 628999888777
        'password' => 'secret123',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.email', 'phoneuser@example.com');
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

test('authenticated user can log out and invalidate token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('logout-test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout');

    $response->assertOk()
        ->assertJsonPath('message', 'Logged out successfully.');

    expect($user->tokens()->count())->toBe(0);
});
