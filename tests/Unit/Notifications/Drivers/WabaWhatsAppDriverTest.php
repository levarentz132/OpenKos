<?php

use App\Notifications\Drivers\WabaWhatsAppDriver;
use Illuminate\Support\Facades\Http;
use OpenKOS\Core\Data\WhatsApp\WhatsAppMessage;
use Tests\TestCase;

uses(TestCase::class);

test('waba driver sends text message successfully', function () {
    Http::fake([
        'https://graph.facebook.com/v21.0/123456789/messages' => Http::response([
            'messaging_product' => 'whatsapp',
            'contacts' => [['input' => '6281234567890', 'wa_id' => '6281234567890']],
            'messages' => [['id' => 'wamid.HBgL...']],
        ], 200),
    ]);

    $driver = new WabaWhatsAppDriver([
        'phone_number_id' => '123456789',
        'access_token' => 'test-token',
    ]);

    $driver->send(new WhatsAppMessage(
        phone: '081234567890',
        message: 'Your OpenKos verification code is: *123456*.',
    ));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://graph.facebook.com/v21.0/123456789/messages'
            && $request['to'] === '6281234567890'
            && $request['type'] === 'text'
            && str_contains($request['text']['body'], '123456');
    });
});

test('waba driver sends template message when template_name is specified', function () {
    Http::fake([
        'https://graph.facebook.com/v21.0/123456789/messages' => Http::response([
            'messages' => [['id' => 'wamid.HBgL...']],
        ], 200),
    ]);

    $driver = new WabaWhatsAppDriver([
        'phone_number_id' => '123456789',
        'access_token' => 'test-token',
        'template_name' => 'otp_verification',
        'template_language' => 'id',
    ]);

    $driver->send(new WhatsAppMessage(
        phone: '6281234567890',
        message: 'Your OpenKos verification code is: *654321*.',
    ));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://graph.facebook.com/v21.0/123456789/messages'
            && $request['to'] === '6281234567890'
            && $request['type'] === 'template'
            && $request['template']['name'] === 'otp_verification'
            && $request['template']['components'][0]['parameters'][0]['text'] === '654321';
    });
});

test('waba driver health check verifies phone_number_id with meta', function () {
    Http::fake([
        'https://graph.facebook.com/v21.0/123456789' => Http::response([
            'id' => '123456789',
            'display_phone_number' => '+62 812-3456-7890',
            'verified_name' => 'Highlander Stay',
        ], 200),
    ]);

    $driver = new WabaWhatsAppDriver([
        'phone_number_id' => '123456789',
        'access_token' => 'test-token',
    ]);

    $health = $driver->health();

    expect($health->healthy)->toBeTrue();
    expect($health->message)->toContain('Highlander Stay');
    expect($health->phone)->toBe('+62 812-3456-7890');
});
