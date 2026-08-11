<?php

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use OpenKOS\Core\Events\SettingsUpdated as PlatformSettingsUpdated;
use OpenKOS\Platform\Facades\OpenKOS;
use OpenKOS\Platform\Settings\SettingDefinition;
use OpenKOS\Platform\Settings\SettingsPage;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    OpenKOS::settings()
        ->registerPage(new SettingsPage(
            key: 'plugin-demo',
            title: 'Plugin Demo',
            href: '/settings/plugin-demo',
            group: 'Integrations',
        ))
        ->registerSetting(new SettingDefinition(
            key: 'plugin_demo.api_key',
            label: 'API Key',
            page: 'plugin-demo',
        ));
});

test('tenant-linked users cannot view registered dynamic settings pages', function () {
    $tenantUser = User::factory()->create();
    Tenant::factory()->withUser($tenantUser)->create();

    $this->actingAs($tenantUser)
        ->get(route('settings.dynamic.edit', 'plugin-demo'))
        ->assertForbidden();
});

test('tenant-linked users cannot update registered dynamic settings', function () {
    $tenantUser = User::factory()->create();
    Tenant::factory()->withUser($tenantUser)->create();

    $this->actingAs($tenantUser)
        ->post(route('settings.values.upsert'), [
            'key' => 'plugin_demo.api_key',
            'value' => 'tenant-secret',
        ])
        ->assertForbidden();
});

test('owners can view and update registered dynamic settings', function () {
    $owner = User::factory()->owner()->create();

    $this->from(route('settings.dynamic.edit', 'plugin-demo'))->actingAs($owner)
        ->get(route('settings.dynamic.edit', 'plugin-demo'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/dynamic')
            ->where('page', 'plugin-demo')
            ->has('definitions', 1)
            ->where('definitions.0.key', 'plugin_demo.api_key'));

    Event::fake([PlatformSettingsUpdated::class]);

    $this->from(route('settings.dynamic.edit', 'plugin-demo'))->actingAs($owner)
        ->post(route('settings.values.upsert'), [
            'key' => 'plugin_demo.api_key',
            'value' => 'owner-secret',
        ])
        ->assertRedirect(route('settings.dynamic.edit', 'plugin-demo'));

    expect(Setting::get('plugin_demo.api_key'))->toBe('owner-secret');

    Event::assertDispatched(PlatformSettingsUpdated::class, function (PlatformSettingsUpdated $event) use ($owner): bool {
        return $event->group === 'plugin-demo'
            && $event->keys === ['plugin_demo.api_key']
            && $event->actorId === $owner->getKey();
    });
});
