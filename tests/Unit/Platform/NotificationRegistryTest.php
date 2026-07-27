<?php

use App\Notifications\Drivers\LogMailDriver;
use App\Notifications\Drivers\SmtpMailDriver;
use OpenKOS\Platform\Notification\NotificationDriverRegistration;
use OpenKOS\Platform\Notification\NotificationRegistry;

test('it registers drivers into channel-scoped storage', function () {
    $registry = new NotificationRegistry;

    $reg1 = new NotificationDriverRegistration(
        name: 'openkos/smtp',
        channel: 'mail',
        driverClass: SmtpMailDriver::class,
        label: 'SMTP',
    );

    $reg2 = new NotificationDriverRegistration(
        name: 'openkos/log',
        channel: 'mail',
        driverClass: LogMailDriver::class,
        label: 'Log',
    );

    $registry->registerDriver($reg1);
    $registry->registerDriver($reg2);

    expect($registry->driver('mail', 'openkos/smtp'))->toBe($reg1);
    expect($registry->driver('mail', 'openkos/log'))->toBe($reg2);
    expect($registry->forChannel('mail'))->toHaveCount(2);
});

test('it allows identical re-registrations', function () {
    $registry = new NotificationRegistry;

    $reg = new NotificationDriverRegistration(
        name: 'openkos/smtp',
        channel: 'mail',
        driverClass: SmtpMailDriver::class,
        label: 'SMTP',
    );

    $registry->registerDriver($reg);
    $registry->registerDriver($reg);

    expect($registry->forChannel('mail'))->toHaveCount(1);
});

test('it rejects conflicting driver registrations for the same channel and name', function () {
    $registry = new NotificationRegistry;

    $reg1 = new NotificationDriverRegistration(
        name: 'openkos/smtp',
        channel: 'mail',
        driverClass: SmtpMailDriver::class,
        label: 'SMTP',
    );

    $reg2 = new NotificationDriverRegistration(
        name: 'openkos/smtp',
        channel: 'mail',
        driverClass: LogMailDriver::class,
        label: 'Conflicting Driver',
    );

    $registry->registerDriver($reg1);

    expect(fn () => $registry->registerDriver($reg2))->toThrow(InvalidArgumentException::class);
});
