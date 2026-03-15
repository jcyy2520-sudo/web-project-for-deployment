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

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],
    
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
    
    // Allowed origins — set CORS_ALLOWED_ORIGINS in .env as comma-separated list
    // Uses config('app.env') so it works correctly after config:cache
    'allowed_origins' => array_filter(array_map('trim', explode(',',
        env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:3001,http://localhost:5173,http://127.0.0.1:5173,https://web-project-for-deployment255.vercel.app')
    ))),
    
    'allowed_origins_patterns' => [],
    
    // Only expose necessary headers
    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With', 'X-Session-ID'],
    
    'exposed_headers' => ['Authorization', 'X-Total-Count', 'X-Page-Count'],
    
    // Cache preflight for 24 hours (production) or 1 hour (dev)
    'max_age' => (int) env('CORS_MAX_AGE', 3600),
    
    'supports_credentials' => true,

];
