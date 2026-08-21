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
    | Credentials + endpoint URLs are configured in the admin dashboard
    | (LoCoNet → Settings & Integration) and stored in loconet_state.integration.
    | Env vars are optional empty fallbacks only — do NOT put project secrets here
    | for normal setup.
    */
    'loconet' => [
        'project_id' => env('LOCONET_PROJECT_ID', ''),
        'project_slug' => env('LOCONET_PROJECT_SLUG', ''),
        'app_id' => env('LOCONET_APP_ID', ''),
        'api_base' => env('LOCONET_API_BASE', ''),
        'socket_url' => env('LOCONET_SOCKET_URL', ''),
        'media_url' => env('LOCONET_MEDIA_URL', ''),
        'webrtc_url' => env('LOCONET_WEBRTC_URL', ''),
        'health_url' => env('LOCONET_HEALTH_URL', ''),
        'webhook_url' => env('LOCONET_WEBHOOK_URL', 'https://api.appdash.yekbun.org/api/webhooks/loconet'),
        'webhook_secret' => env('LOCONET_WEBHOOK_SECRET', ''),
        'certificate' => env('LOCONET_CERTIFICATE', ''),
    ],

];
