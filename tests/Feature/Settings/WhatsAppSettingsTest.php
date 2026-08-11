<?php

use App\Models\Setting;
use App\Models\User;
use App\Notifications\Drivers\WhatsappLogDriver;
use OpenKOS\Platform\Notification\NotificationDriverRegistration;
use OpenKOS\Platform\Notification\NotificationRegistry;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('WhatsApp settings page', function () {
    it('renders the form', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->get(route('settings.whatsapp.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/whatsapp')
                ->has('drivers')
                ->has('settings.whatsapp_driver')
                ->has('settings.whatsapp_config')
            );
    });

    it('contains all built-in drivers', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->get(route('settings.whatsapp.edit'))
            ->assertInertia(fn ($page) => $page
                ->where('drivers.0.name', 'openkos/whatsapp-log')
            );
    });

    it('lists a driver registered at runtime by a plugin', function () {
        app(NotificationRegistry::class)->registerDriver(new NotificationDriverRegistration(
            name: 'custom_gateway',
            channel: 'whatsapp',
            driverClass: WhatsappLogDriver::class,
            label: 'Custom Gateway',
        ));

        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->get(route('settings.whatsapp.edit'))
            ->assertInertia(fn ($page) => $page
                ->where('drivers.1.name', 'custom_gateway')
                ->where('drivers.1.label', 'Custom Gateway')
            );
    });

    it('updates whatsapp driver selection', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->patch(route('settings.whatsapp.update'), [
                'whatsapp_driver' => 'log',
            ])
            ->assertRedirect();

        expect(Setting::get('whatsapp_driver'))->toBe('log');
    });

    it('updates whatsapp config', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->patch(route('settings.whatsapp.update'), [
                'whatsapp_driver' => 'log',
                'whatsapp_config' => [
                    'log' => [
                        'note' => 'test',
                    ],
                ],
            ])
            ->assertRedirect();

        $config = Setting::get('whatsapp_config');

        expect($config['log']['note'])->toBe('test');
    });

    it('does not overwrite existing config on update', function () {
        $owner = User::factory()->owner()->create();
        Setting::set('whatsapp_config', ['log' => ['note' => 'test']], 'encrypted:array');
        Setting::set('whatsapp_driver', 'log');

        $this->actingAs($owner)
            ->patch(route('settings.whatsapp.update'), [
                'whatsapp_driver' => 'log',
                'whatsapp_config' => [
                    'log' => ['note' => 'test'],
                ],
            ])
            ->assertRedirect();

        $config = Setting::get('whatsapp_config');
        expect($config['log']['note'])->toBe('test');
    });

    it('tests connection with log driver', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->post(route('settings.whatsapp.test'))
            ->assertRedirect();
    });

    it('is owner-only', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.whatsapp.edit'))
            ->assertForbidden();
    });

    it('validates driver name', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->patch(route('settings.whatsapp.update'), [
                'whatsapp_driver' => 'invalid_driver',
            ])
            ->assertSessionHasErrors(['whatsapp_driver']);
    });

    it('rejects fonnte when the integration package is absent', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->patch(route('settings.whatsapp.update'), [
                'whatsapp_driver' => 'fonnte',
            ])
            ->assertSessionHasErrors(['whatsapp_driver']);
    });

    it('accepts the fonnte alias when the integration is registered', function () {
        app(NotificationRegistry::class)->registerDriver(new NotificationDriverRegistration(
            name: 'openkos/fonnte',
            channel: 'whatsapp',
            driverClass: WhatsappLogDriver::class,
            label: 'Fonnte (Test)',
        ));

        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->patch(route('settings.whatsapp.update'), [
                'whatsapp_driver' => 'fonnte',
            ])
            ->assertRedirect();

        expect(Setting::get('whatsapp_driver'))->toBe('fonnte');
    });

    it('returns status json', function () {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->get(route('settings.whatsapp.status'))
            ->assertOk()
            ->assertJsonStructure(['healthy', 'message', 'phone', 'lastConnected']);
    });

});
