<?php

use App\Models\Setting;
use App\Models\User;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('Mail settings page', function () {
    it('renders the form', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('settings.mail.edit'))->actingAs($owner)
            ->get(route('settings.mail.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/mail')
                ->has('settings.mail_config')
                ->has('drivers')
            );
    });

    it('updates mail settings', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('settings.mail.edit'))->actingAs($owner)
            ->patch(route('settings.mail.update'), [
                'mail_config' => [
                    'host' => 'smtp.example.com',
                    'port' => 587,
                    'username' => 'user@example.com',
                    'encryption' => 'tls',
                    'from_address' => 'noreply@example.com',
                    'from_name' => 'Test',
                ],
            ])
            ->assertRedirect(route('settings.mail.edit'));

        $config = Setting::get('mail_config');

        expect($config['host'])->toBe('smtp.example.com');
        expect($config['port'])->toBe(587);
        expect($config['username'])->toBe('user@example.com');
        expect($config['encryption'])->toBe('tls');
        expect($config['from_address'])->toBe('noreply@example.com');
        expect($config['from_name'])->toBe('Test');
    });

    it('encrypts the mail password', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('settings.mail.edit'))->actingAs($owner)
            ->patch(route('settings.mail.update'), [
                'mail_config' => [
                    'password' => 'secret123',
                ],
            ]);

        $config = Setting::get('mail_config');

        expect($config['password'])->toBe('secret123');
    });

    it('validates mail settings', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('settings.mail.edit'))->actingAs($owner)
            ->patch(route('settings.mail.update'), [
                'mail_config' => [
                    'port' => 'not-a-number',
                    'encryption' => 'invalid',
                    'from_address' => 'not-an-email',
                ],
            ])
            ->assertSessionHasErrors([
                'mail_config.port',
                'mail_config.encryption',
                'mail_config.from_address',
            ]);
    });

    it('requires authentication', function () {
        $this->get(route('settings.mail.edit'))->assertRedirect(route('login'));
        $this->patch(route('settings.mail.update'))->assertRedirect(route('login'));
    });

    it('pre-fills form with env-derived values when settings table has no mail_config', function () {
        config([
            'mail.mailers.smtp.host' => 'env-smtp.example.com',
            'mail.mailers.smtp.port' => 1025,
            'mail.from.address' => 'env-from@example.com',
        ]);

        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->get(route('settings.mail.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/mail')
                ->where('settings.mail_config.host', 'env-smtp.example.com')
                ->where('settings.mail_config.port', 1025)
                ->where('settings.mail_config.from_address', 'env-from@example.com')
                ->where('notificationChannels.mail', true)
            );
    });

    it('overrides env values field-by-field and keeps env fallback for unset fields in partial settings row', function () {
        config([
            'mail.mailers.smtp.host' => 'env-host.com',
            'mail.mailers.smtp.port' => 2525,
            'mail.mailers.smtp.username' => 'env_user',
            'mail.mailers.smtp.password' => 'env_pass',
            'mail.from.address' => 'env@example.com',
        ]);

        Setting::set('mail_config', [
            'host' => 'override-host.com',
        ]);

        $effective = Setting::effectiveMailConfig();

        expect($effective['host'])->toBe('override-host.com');
        expect($effective['port'])->toBe(2525);
        expect($effective['username'])->toBe('env_user');
        expect($effective['password'])->toBe('env_pass');
        expect($effective['from_address'])->toBe('env@example.com');
    });

    it('resolves mail.default driver from settings driver to env mailer to log', function () {
        config(['mail.default' => 'sendmail']);

        expect(Setting::effectiveMailConfig()['driver'])->toBe('sendmail');

        Setting::set('mail_config', ['driver' => 'smtp']);

        expect(Setting::effectiveMailConfig()['driver'])->toBe('openkos/smtp');
    });

    it('sends test email using effective config', function () {
        config([
            'mail.mailers.smtp.host' => 'smtp.test.com',
            'mail.from.address' => 'sender@test.com',
        ]);

        $owner = User::factory()->owner()->create();

        $this->from(route('settings.mail.edit'))
            ->actingAs($owner)
            ->post(route('settings.mail.test'))
            ->assertRedirect(route('settings.mail.edit'));
    });
});
