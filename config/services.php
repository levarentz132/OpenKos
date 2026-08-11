<?php

use App\Notifications\Drivers\WhatsappLogDriver;

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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
                'label' => 'Log',
            ],
        ],
    ],

];
