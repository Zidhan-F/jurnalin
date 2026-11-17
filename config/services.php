<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

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

    // Ringkasan & pengingat WhatsApp otomatis (P42). driver: log | http
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'log'),
        'url' => env('WHATSAPP_API_URL'),
        'token' => env('WHATSAPP_API_TOKEN'),
        'cost_per_message' => (int) env('WHATSAPP_COST_PER_MESSAGE', 0),
    ],

    // Asisten AI (P26, P27, P36). Tanpa kunci, fitur AI memakai penyusun berbasis aturan saja.
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
    ],

];
