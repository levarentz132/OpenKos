<?php

use App\Enums\Role;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('administrator can delete a staff user from the web users management', function () {
    $owner = User::factory()->owner()->create();
    $staff = User::factory()->staff()->create();
    $property = Property::factory()->create();
    $staff->properties()->attach($property);

    // Create session for user
    DB::table('sessions')->insert([
        'id' => 'session-123',
        'user_id' => $staff->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PHPUnit',
        'payload' => 'dummy',
        'last_activity' => time(),
    ]);

    $response = $this->actingAs($owner)
        ->delete("/users/{$staff->id}");

    $response->assertRedirect(route('users.index'));

    $this->assertDatabaseMissing('users', [
        'id' => $staff->id,
    ]);

    $this->assertDatabaseMissing('sessions', [
        'id' => 'session-123',
    ]);

    $this->assertDatabaseMissing('property_user', [
        'user_id' => $staff->id,
    ]);
});

test('last active owner cannot be deleted via web users management', function () {
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)
        ->delete("/users/{$owner->id}");

    $response->assertSessionHasErrors(['user']);

    $this->assertDatabaseHas('users', [
        'id' => $owner->id,
    ]);
});
