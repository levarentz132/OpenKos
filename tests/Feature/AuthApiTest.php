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

test('api registration sends OTP and does not create database records until verified', function () {
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
            'registration_token',
            'otp_sent',
            'otp_channel',
            'target',
        ]);

    $regToken = $response->json('registration_token');
    expect($regToken)->toStartWith('reg_');

    // CRITICAL: Ensure NO user and NO tenant were created in the database yet
    $this->assertDatabaseMissing('users', [
        'email' => 'john@example.com',
    ]);
    $this->assertDatabaseMissing('tenants', [
        'name' => 'John Doe',
    ]);

    // Inspect cached pending registration
    $pending = \Illuminate\Support\Facades\Cache::get("pending_reg_{$regToken}");
    expect($pending)->not->toBeNull();
    $code = $pending['otp'];

    // 1. Verifying with wrong OTP fails and still leaves database empty
    $failResponse = $this->postJson('/api/v1/auth/otp/verify', [
        'registration_token' => $regToken,
        'code' => '000000',
    ]);
    $failResponse->assertStatus(422);
    $this->assertDatabaseMissing('users', ['email' => 'john@example.com']);

    // 2. Verifying with correct OTP creates the user and tenant in database
    $verifyResponse = $this->postJson('/api/v1/auth/otp/verify', [
        'registration_token' => $regToken,
        'code' => $code,
    ]);

    $verifyResponse->assertCreated()
        ->assertJsonPath('verified', true)
        ->assertJsonPath('phone_verified', true)
        ->assertJsonPath('user.email', 'john@example.com');

    expect($verifyResponse->json('token'))->not->toBeNull();

    // Now records exist in the database only in tenants table!
    $this->assertDatabaseMissing('users', [
        'email' => 'john@example.com',
    ]);
    $this->assertDatabaseHas('tenants', [
        'email' => 'john@example.com',
        'phone' => '6281234567890',
    ]);

    $tenant = \App\Models\Tenant::where('email', 'john@example.com')->first();
    expect($tenant->hasVerifiedPhone())->toBeTrue();
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
    \App\Models\Tenant::factory()->create(['user_id' => $user->id, 'phone_verified_at' => now()]);

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
    \App\Models\Tenant::factory()->create(['user_id' => $user->id, 'phone_verified_at' => now()]);

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

test('user can register and staged registration uses whatsapp otp channel', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Sarah Connor',
        'email' => 'sarah@example.com',
        'phone' => '081234567888',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
    ]);

    $response->assertCreated()
        ->assertJsonPath('otp_sent', true)
        ->assertJsonPath('otp_channel', 'whatsapp');

    // Database is empty before verification
    $this->assertDatabaseMissing('users', ['email' => 'sarah@example.com']);

    $regToken = $response->json('registration_token');
    $pending = Cache::get("pending_reg_{$regToken}");
    expect($pending)->not->toBeNull();
    $code = $pending['otp'];

    // Verify OTP creates the user with phone_verified_at set
    $verifyResponse = $this->postJson('/api/v1/auth/otp/verify', [
        'registration_token' => $regToken,
        'code' => $code,
    ]);

    $verifyResponse->assertCreated()
        ->assertJsonPath('verified', true)
        ->assertJsonPath('phone_verified', true);

    $this->assertDatabaseMissing('users', ['email' => 'sarah@example.com']);
    $this->assertDatabaseHas('tenants', ['email' => 'sarah@example.com']);
    $tenant = \App\Models\Tenant::where('email', 'sarah@example.com')->first();
    expect($tenant->hasVerifiedPhone())->toBeTrue();
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

test('authenticated tenant can delete their account via delete me endpoint', function () {
    $tenant = \App\Models\Tenant::create([
        'name' => 'Tenant To Delete',
        'email' => 'tenantdelete@example.com',
        'phone' => '6281999888777',
        'password' => Hash::make('secret123'),
        'is_active' => true,
    ]);

    $token = $tenant->createToken('delete-test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('message', 'Account deleted successfully.');

    $this->assertDatabaseMissing('tenants', [
        'id' => $tenant->id,
        'deleted_at' => null,
    ]);
});

test('administrator can delete a user account via delete users endpoint', function () {
    $admin = User::factory()->owner()->create([
        'email' => 'adminowner@example.com',
    ]);
    // Create another owner so the last owner constraint is satisfied
    User::factory()->owner()->create([
        'email' => 'secondowner@example.com',
    ]);

    $targetUser = User::factory()->create([
        'email' => 'targetuser@example.com',
    ]);

    $adminToken = $admin->createToken('admin-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->deleteJson("/api/v1/auth/users/{$targetUser->id}");

    $response->assertOk()
        ->assertJsonPath('message', 'User deleted successfully.');

    $this->assertDatabaseMissing('users', [
        'id' => $targetUser->id,
    ]);
});

test('non-admin tenant cannot delete another user account', function () {
    $tenant = \App\Models\Tenant::create([
        'name' => 'Regular Tenant',
        'email' => 'regular@example.com',
        'password' => Hash::make('secret123'),
        'is_active' => true,
    ]);

    $targetUser = User::factory()->create();
    $tenantToken = $tenant->createToken('tenant-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$tenantToken}")
        ->deleteJson("/api/v1/auth/users/{$targetUser->id}");

    $response->assertStatus(403);
});

test('cannot delete the last remaining owner account', function () {
    $admin = User::factory()->owner()->create([
        'email' => 'soleowner@example.com',
    ]);

    $adminToken = $admin->createToken('admin-token')->plainTextToken;

    // Admin tries to self-delete via /me
    $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->deleteJson('/api/v1/auth/me');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['account']);
});

test('check-status detects pending registration and shows dual verification details', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Pending Tenant',
        'email' => 'pending@example.com',
        'phone' => '08777888999',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
        'otp_channel' => 'whatsapp',
    ]);

    $response->assertCreated();
    $regToken = $response->json('registration_token');

    // Check status via phone
    $statusRes = $this->postJson('/api/v1/auth/check-status', [
        'login' => '08777888999',
    ]);

    $statusRes->assertOk()
        ->assertJsonPath('registered', false)
        ->assertJsonPath('pending_registration', true)
        ->assertJsonPath('status', 'pending_registration')
        ->assertJsonPath('registration_token', $regToken)
        ->assertJsonPath('verifications.whatsapp.status', 'pending')
        ->assertJsonPath('verifications.whatsapp.verified', false)
        ->assertJsonPath('verifications.email.status', 'pending')
        ->assertJsonPath('verifications.email.verified', false);

    // Check status via email
    $emailCheck = $this->postJson('/api/v1/auth/check-status', [
        'login' => 'pending@example.com',
    ]);
    $emailCheck->assertOk()
        ->assertJsonPath('registration_token', $regToken);
});

test('check-status returns registered and dual verification status for existing tenant', function () {
    $tenant = \App\Models\Tenant::create([
        'name' => 'Verified Tenant',
        'email' => 'verified@example.com',
        'phone' => '62811223344',
        'password' => Hash::make('secret123'),
        'phone_verified_at' => now(),
        'email_verified_at' => null,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/check-status', [
        'login' => '0811223344',
    ]);

    $response->assertOk()
        ->assertJsonPath('registered', true)
        ->assertJsonPath('pending_registration', false)
        ->assertJsonPath('status', 'registered')
        ->assertJsonPath('verifications.whatsapp.verified', true)
        ->assertJsonPath('verifications.whatsapp.status', 'verified')
        ->assertJsonPath('verifications.email.verified', false)
        ->assertJsonPath('verifications.email.status', 'unverified')
        ->assertJsonPath('is_fully_verified', true);
});

test('check-status returns unregistered for unknown credentials', function () {
    $response = $this->postJson('/api/v1/auth/check-status', [
        'login' => 'nonexistent@example.com',
    ]);

    $response->assertOk()
        ->assertJsonPath('registered', false)
        ->assertJsonPath('pending_registration', false)
        ->assertJsonPath('status', 'unregistered');
});

test('registration returns delivery_warning when whatsapp driver is log', function () {
    \App\Models\Setting::set('whatsapp_driver', 'log');

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Log Test User',
        'email' => 'loguser@example.com',
        'phone' => '081299998888',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'otp_channel' => 'whatsapp',
    ]);

    $response->assertCreated()
        ->assertJsonPath('otp_sent', true)
        ->assertJsonPath('driver', 'log')
        ->assertJsonStructure(['delivery_warning']);

    expect($response->json('delivery_warning'))->toContain('WhatsApp driver is set to [log]');
});

test('registration gracefully reports delivery_error when dispatch fails', function () {
    $this->mock(WhatsAppManager::class, function (MockInterface $mock) {
        $mock->shouldReceive('send')->andThrow(new \RuntimeException('WABA connection timeout or invalid token'));
    });

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Failing Test User',
        'email' => 'failuser@example.com',
        'phone' => '081277776666',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'otp_channel' => 'whatsapp',
    ]);

    $response->assertCreated()
        ->assertJsonPath('otp_sent', false)
        ->assertJsonPath('delivery_error', 'WABA connection timeout or invalid token');
});

test('user can request WhatsApp OTP on registration form prior to account creation', function () {
    $response = $this->postJson('/api/v1/auth/otp/send', [
        'channel' => 'whatsapp',
        'phone' => '081234567899',
    ]);

    $response->assertOk()
        ->assertJsonPath('sent', true)
        ->assertJsonPath('channel', 'whatsapp')
        ->assertJsonPath('target', '6281234567899');

    // Code is stored in cache for registration
    $cached = \Illuminate\Support\Facades\Cache::get('reg_phone_otp_6281234567899');
    expect($cached)->not->toBeNull();
    expect($cached['otp'])->toMatch('/^\d{6}$/');
});

test('submitting registration with valid WhatsApp OTP creates tenant and marks phone verified', function () {
    // 1. Request OTP on form
    $this->postJson('/api/v1/auth/otp/send', [
        'channel' => 'whatsapp',
        'phone' => '081288889999',
    ])->assertOk();

    $cached = \Illuminate\Support\Facades\Cache::get('reg_phone_otp_6281288889999');
    $otpCode = $cached['otp'];

    // 2. Submit registration form with the received OTP code
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Form Registered User',
        'email' => 'formuser@example.com',
        'phone' => '081288889999',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'otp' => $otpCode,
        'device_name' => 'test-device',
    ]);

    $response->assertCreated()
        ->assertJsonPath('phone_verified', true)
        ->assertJsonStructure(['token', 'user']);

    // Check database
    $tenant = \App\Models\Tenant::where('email', 'formuser@example.com')->first();
    expect($tenant)->not->toBeNull();
    expect($tenant->hasVerifiedPhone())->toBeTrue();
});

test('submitting registration with invalid WhatsApp OTP fails with 422 and creates no database records', function () {
    $this->postJson('/api/v1/auth/otp/send', [
        'channel' => 'whatsapp',
        'phone' => '081255554444',
    ])->assertOk();

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Invalid OTP User',
        'email' => 'invalidotp@example.com',
        'phone' => '081255554444',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'otp' => '000000',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['otp']);

    $this->assertDatabaseMissing('tenants', [
        'email' => 'invalidotp@example.com',
    ]);
});

test('clicking signed email verification link verifies tenant email', function () {
    $tenant = \App\Models\Tenant::create([
        'name' => 'Email Verify User',
        'email' => 'verifytenant@example.com',
        'phone' => '6281233334444',
        'password' => \Illuminate\Support\Facades\Hash::make('password123'),
        'phone_verified_at' => now(),
        'email_verified_at' => null,
        'is_active' => true,
    ]);

    $signedUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
        'api.v1.auth.verify-email',
        now()->addHours(24),
        [
            'id' => $tenant->id,
            'hash' => sha1($tenant->email),
        ]
    );

    // Call signed URL with Accept: application/json
    $response = $this->getJson($signedUrl);
    $response->assertOk()
        ->assertJsonPath('verified', true)
        ->assertJsonPath('email_verified', true);

    expect($tenant->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('accessing email verification link with invalid signature returns 403', function () {
    $tenant = \App\Models\Tenant::create([
        'name' => 'Tamper Test User',
        'email' => 'tamper@example.com',
        'phone' => '6281211112222',
        'password' => \Illuminate\Support\Facades\Hash::make('password123'),
        'phone_verified_at' => now(),
        'email_verified_at' => null,
        'is_active' => true,
    ]);

    $tamperedUrl = "/api/v1/auth/verify-email?id={$tenant->id}&hash=" . sha1($tenant->email) . "&signature=invalid_sig";

    $response = $this->getJson($tamperedUrl);
    $response->assertStatus(403);

    expect($tenant->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('tenant can resend email verification link', function () {
    $tenant = \App\Models\Tenant::create([
        'name' => 'Resend Email User',
        'email' => 'resendmail@example.com',
        'phone' => '6281277778888',
        'password' => \Illuminate\Support\Facades\Hash::make('password123'),
        'phone_verified_at' => now(),
        'email_verified_at' => null,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/email/resend', [
        'email' => 'resendmail@example.com',
    ]);

    $response->assertOk()
        ->assertJsonPath('sent', true)
        ->assertJsonPath('email', 'resendmail@example.com');
});

test('requesting otp with channel email for existing tenant dispatches verification link instead of numeric otp', function () {
    $tenant = \App\Models\Tenant::create([
        'name' => 'Email Channel User',
        'email' => 'emailchannel@example.com',
        'phone' => '6281233335555',
        'password' => \Illuminate\Support\Facades\Hash::make('password123'),
        'phone_verified_at' => now(),
        'email_verified_at' => null,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/otp/send', [
        'channel' => 'email',
        'email' => 'emailchannel@example.com',
    ]);

    $response->assertOk()
        ->assertJsonPath('sent', true)
        ->assertJsonPath('channel', 'email')
        ->assertJsonPath('target', 'emailchannel@example.com');

    // Ensure no numeric OTP code was saved in cache for email
    expect(\Illuminate\Support\Facades\Cache::has("otp_tenant_{$tenant->id}_email"))->toBeFalse();
});

test('requesting registration phone otp for already registered number is rejected', function () {
    \App\Models\Tenant::create([
        'name' => 'Existing Number User',
        'email' => 'existingnum@example.com',
        'phone' => '6281299990000',
        'password' => \Illuminate\Support\Facades\Hash::make('password123'),
        'phone_verified_at' => now(),
        'email_verified_at' => null,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/otp/send', [
        'channel' => 'whatsapp',
        'phone' => '081299990000',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['phone']);
});

test('existing unverified tenant can request otp and register to convert to verified', function () {
    $unverifiedTenant = \App\Models\Tenant::create([
        'name' => 'Pending Tenant',
        'email' => 'pending@example.com',
        'phone' => '6281211119999',
        'phone_verified_at' => null,
        'is_active' => true,
    ]);

    // 1. Request OTP on form succeeds for unverified tenant
    $sendResponse = $this->postJson('/api/v1/auth/otp/send', [
        'channel' => 'whatsapp',
        'phone' => '081211119999',
    ]);

    $sendResponse->assertOk()
        ->assertJsonPath('sent', true);

    $cached = \Illuminate\Support\Facades\Cache::get('reg_phone_otp_6281211119999');
    $otpCode = $cached['otp'];

    // 2. Submit registration to claim/convert the account
    $regResponse = $this->postJson('/api/v1/auth/register', [
        'name' => 'Pending Tenant Active',
        'email' => 'pending@example.com',
        'phone' => '081211119999',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
        'otp' => $otpCode,
        'device_name' => 'mobile-test',
    ]);

    $regResponse->assertCreated()
        ->assertJsonPath('phone_verified', true)
        ->assertJsonStructure(['token', 'user']);

    // Ensure it updated the same tenant record rather than duplicating
    expect(\App\Models\Tenant::where('phone', '6281211119999')->count())->toBe(1);
    $unverifiedTenant->refresh();
    expect($unverifiedTenant->hasVerifiedPhone())->toBeTrue();
    expect($unverifiedTenant->name)->toBe('Pending Tenant Active');
});

test('tenant login is blocked with 403 if phone number is not verified', function () {
    \App\Models\Tenant::create([
        'name' => 'Unverified Login User',
        'email' => 'unverifiedlogin@example.com',
        'phone' => '6281255556666',
        'password' => \Illuminate\Support\Facades\Hash::make('password123'),
        'phone_verified_at' => null,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => '081255556666',
        'password' => 'password123',
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('phone_verified', false)
        ->assertJsonPath('requires_verification', true);
});
