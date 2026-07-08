<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | API routes are served under /v1/* (not /api/*). Origins are configured
    | via CORS_ALLOWED_ORIGINS in .env (see config/gym.php).
    |
    */

    'paths' => ['v1/*', 'up', 'docs', 'api/documentation'],

    'allowed_methods' => ['*'],

    'allowed_origins' => config('gym.cors_allowed_origins', ['http://localhost:3000']),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
