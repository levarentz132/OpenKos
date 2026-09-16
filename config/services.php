<?php

use App\Notifications\Drivers\FonnteWhatsAppDriver;
use App\Notifications\Drivers\WabaWhatsAppDriver;
use App\Notifications\Drivers\WhatsappLogDriver;

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'default' => env('WHATSAPP_DRIVER', 'log'),

        // Seed data for the WhatsAppPlugin, which registers these into the
        // platform NotificationRegistry (the runtime source of truth).
        'drivers' => [
            'log' => [
                'class' => WhatsappLogDriver::class,
                'label' => 'Log (Development)',
            ],
            'fonnte' => [
                'class' => FonnteWhatsAppDriver::class,
                'label' => 'Fonnte (WhatsApp Gateway)',
                'token' => env('FONNTE_TOKEN'),
            ],
            'waba' => [
                'class' => WabaWhatsAppDriver::class,
                'label' => 'WhatsApp Business Cloud API (WABA)',
                'phone_number_id' => env('WABA_PHONE_NUMBER_ID'),
                'access_token' => env('WABA_ACCESS_TOKEN'),
                'template_name' => env('WABA_TEMPLATE_NAME'),
                'template_language' => env('WABA_TEMPLATE_LANGUAGE', 'en'),
                'button_type' => env('WABA_BUTTON_TYPE', 'url'),
            ],
        ],
    ],

    'doku' => [
        'client_id' => env('DOKU_CLIENT_ID'),
        'secret_key' => env('DOKU_SECRET_KEY'),
        'api_key' => env('DOKU_API_KEY'),
        'environment' => env('DOKU_ENVIRONMENT', 'sandbox'),
        'callback_url' => env('DOKU_CALLBACK_URL'),
    ],

];
