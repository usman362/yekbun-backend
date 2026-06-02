<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Allowed front-end origins. `FRONTEND_URL` is the legacy admin dashboard
    // (appdash.yekbun.org); `yekbun.app` is the new public web app. Both run on
    // separate Plesk subscriptions but hit the same Laravel API.
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:3002'),
        'https://yekbun.app',
        'https://www.yekbun.app',
        'http://localhost:8080', // yekbun-app vite dev server
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
