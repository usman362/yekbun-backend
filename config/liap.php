<?php

declare(strict_types=1);

return [
    'routing' => [
        'signed' => false,
        'middleware' => [],
        'prefix' => '',
    ],

    'google_play_package_name' => env('GOOGLE_PLAY_PACKAGE_NAME', 'com.some.thing'),
    'appstore_password' => env('APPSTORE_PASSWORD', ''),

    'eventListeners' => [],

    // App Store JWT (optional; for server-to-server APIs)
    'appstore_private_key_id' => env('APPSTORE_PRIVATE_KEY_ID'),
    'appstore_private_key' => env('APPSTORE_PRIVATE_KEY'),
    'appstore_issuer_id' => env('APPSTORE_ISSUER_ID'),
    'appstore_bundle_id' => env('APPSTORE_BUNDLE_ID'),
];

