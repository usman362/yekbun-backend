<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'bunny' => [
        'cdn_url'      => env('BUNNY_CDN_URL', 'https://yekbun.b-cdn.net'),
        'storage_zone' => env('BUNNY_STORAGE_ZONE'),
        'storage_key'  => env('BUNNY_STORAGE_KEY'),
        'region'       => env('BUNNY_REGION', 'de'),
    ],

    /*
    | Firebase Cloud Messaging (mobile push).
    | Must live in config — after `php artisan config:cache`, env() is null
    | outside config files, which previously skipped every FCM push.
    */
    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
    ],

    /*
    | LoCoNet communication provider (chat / calls / streams).
    | Admin dashboard stores overrides in loconet_state.integration;
    | env values are used as defaults / server-side secrets.
    */
    'loconet' => [
        'project_id' => env('LOCONET_PROJECT_ID', 'yekbun-prod-01'),
        'api_base' => env('LOCONET_API_BASE', 'https://api.loconet.io/v1'),
        'socket_url' => env('LOCONET_SOCKET_URL', 'wss://realtime.loconet.io/socket/yekbun-prod-01'),
        'media_url' => env('LOCONET_MEDIA_URL', 'https://media.loconet.io/v1/upload/yekbun-prod-01'),
        'webrtc_url' => env('LOCONET_WEBRTC_URL', 'wss://rtc.loconet.io/signal/yekbun-prod-01'),
        'health_url' => env('LOCONET_HEALTH_URL', 'https://api.loconet.io/v1/health'),
        'webhook_url' => env('LOCONET_WEBHOOK_URL', 'https://api.appdash.yekbun.org/api/webhooks/loconet'),
        'webhook_secret' => env('LOCONET_WEBHOOK_SECRET'),
        'certificate' => env('LOCONET_CERTIFICATE'),
    ],

];
