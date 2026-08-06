<?php

use App\Data\WhatsApp\WhatsAppAttachment;
use App\Data\WhatsApp\WhatsAppMessage;

it('creates with phone and message', function () {
    $message = new WhatsAppMessage('08123456789', 'Hello');

    expect($message->phone)->toBe('08123456789');
    expect($message->message)->toBe('Hello');
});

it('creates with optional sender', function () {
    $message = new WhatsAppMessage('08123456789', 'Hello', 'OpenKOS');

    expect($message->sender)->toBe('OpenKOS');
});

it('defaults sender to null', function () {
    $message = new WhatsAppMessage('08123456789', 'Hello');

    expect($message->sender)->toBeNull();
});

it('properties are readonly', function () {
    $message = new WhatsAppMessage('08123456789', 'Hello');

    expect(fn () => $message->phone = '123')->toThrow(Error::class);
});

it('supports an optional document attachment', function () {
    $attachment = new WhatsAppAttachment('%PDF-test', 'invoice.pdf', 'application/pdf');
    $message = new WhatsAppMessage('08123456789', 'Hello', attachment: $attachment);

    expect($message->attachment)->toBe($attachment);
});
