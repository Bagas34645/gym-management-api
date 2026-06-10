<?php

return [
    'face_api_url' => env('FACE_API_URL', 'http://localhost:8001'),
    'face_api_key' => env('FACE_API_KEY', ''),
    'face_verify_threshold' => (float) env('FACE_VERIFY_THRESHOLD', 0.6),
    'face_identify_threshold' => (float) env('FACE_IDENTIFY_THRESHOLD', 0.45),
    'face_encryption_key' => env('FACE_ENCRYPTION_KEY', env('APP_KEY')),
    'cors_allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:5173'))),
    'otp_ttl' => (int) env('OTP_TTL', 300),
    'login_max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
    'login_lockout_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 15),
];
