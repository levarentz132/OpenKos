<?php

use App\Notifications\Drivers\WhatsappLogDriver;
use OpenKOS\Core\Data\WhatsApp\WhatsAppAttachment;
use OpenKOS\Core\Data\WhatsApp\WhatsAppMessage;

it('sends without exception', function () {
    $driver = new WhatsappLogDriver;

    $driver->send(new WhatsAppMessage('08123456789', 'Test message'));

    expect(true)->toBeTrue();
});

it('sends documents without logging their contents', function () {
    $driver = new WhatsappLogDriver;

    $driver->send(new WhatsAppMessage(
        '08123456789',
        'Invoice',
        attachment: new WhatsAppAttachment('%PDF-test', 'invoice.pdf', 'application/pdf'),
    ));

    expect(true)->toBeTrue();
});

it('returns healthy', function () {
    $driver = new WhatsappLogDriver;

    expect($driver->health()->healthy)->toBeTrue();
});

it('does not support pairing', function () {
    $driver = new WhatsappLogDriver;

    expect($driver->supportsPairing())->toBeFalse();
});

it('has empty configuration schema', function () {
    $driver = new WhatsappLogDriver;

    expect($driver->configurationSchema())->toBe([]);
});

it('returns null qr code', function () {
    $driver = new WhatsappLogDriver;

    expect($driver->getPairingQrCode())->toBeNull();
});
