<?php

return [
    'private_key_path' => env('JWT_PRIVATE_KEY_PATH', storage_path('app/jwt/private.pem')),
    'public_key_path' => env('JWT_PUBLIC_KEY_PATH', storage_path('app/jwt/public.pem')),
    'access_ttl' => (int) env('JWT_ACCESS_TTL', 86400),
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 2592000),
    'issuer' => env('JWT_ISSUER', env('APP_URL', 'http://localhost')),
];
