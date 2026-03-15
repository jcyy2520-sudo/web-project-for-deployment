<?php

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s%s',
        'localhost,localhost:3000,localhost:5173,127.0.0.1,127.0.0.1:8000,::1',
        env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : '',
        ',web-project-for-deployment255.vercel.app'
    ))),

    'guard' => ['web'],

    'expiration' => env('SANCTUM_TOKEN_EXPIRATION', 10080), // 7 days in minutes — was null (never expire)

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];