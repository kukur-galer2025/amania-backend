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
    */

    // 1. TAMBAHKAN 'storage/*' AGAR FILE IMAGE BISA DI-FETCH SEBAGAI BLOB
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    // 2. MENGGUNAKAN '*' AGAR AMAN DI LOKAL MAUPUN HOSTING
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // 3. SET TRUE JIKA KAMU MENGGUNAKAN COOKIE/SESSION (SANCTUM)
    'supports_credentials' => true,

];