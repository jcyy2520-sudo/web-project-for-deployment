<?php

/**
 * Security Configuration
 * Settings for securing the application
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Enabled Security Features
    |--------------------------------------------------------------------------
    |
    | Controls which security features are enabled in the application
    |
    */

    'enabled_features' => [
        'rate_limiting' => true,
        'error_logging' => true,
        'request_monitoring' => true,
        'security_headers' => true,
        'cors_enforcement' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for API endpoints
    |
    */

    'rate_limiting' => [
        'default' => '60,1', // 60 requests per 1 minute
        'auth_attempts' => '5,15', // 5 attempts per 15 minutes
        'api_key' => '1000,1', // 1000 requests per 1 minute for API key
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Cross-Origin Resource Sharing settings
    |
    */

    'cors' => [
        'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:5173')),
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
        // SECURITY FIX: Removed wildcard '*' - only allow specific headers needed
        'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With', 'X-XSRF-TOKEN'],
        'exposed_headers' => ['X-Total-Count', 'X-Page-Number'],
        'max_age' => 86400, // 24 hours
        'supports_credentials' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Security
    |--------------------------------------------------------------------------
    |
    | Session security settings
    |
    */

    'session' => [
        'secure' => env('SESSION_SECURE_COOKIES', env('APP_ENV') === 'production'),
        'http_only' => true,
        'same_site' => 'lax',
        'same_site_strict' => env('APP_ENV') === 'production',
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Requirements
    |--------------------------------------------------------------------------
    |
    | Password complexity requirements
    |
    */

    'password' => [
        'min_length' => 8,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_special_chars' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Fields (won't be logged)
    |--------------------------------------------------------------------------
    |
    | Fields that should never be logged or stored in error logs
    |
    */

    'sensitive_fields' => [
        'password',
        'pin',
        'token',
        'api_key',
        'secret',
        'credit_card',
        'cvv',
        'ssn',
        'authorization',
        'x-api-key',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | HTTP security headers to add to responses
    |
    */

    'headers' => [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        'Strict-Transport-Security' => env('APP_ENV') === 'production' 
            ? 'max-age=31536000; includeSubDomains; preload'
            : null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Mode Security
    |--------------------------------------------------------------------------
    |
    | Debug mode should always be disabled in production
    |
    */

    'debug' => [
        'enabled' => env('APP_DEBUG', false) && env('APP_ENV') !== 'production',
        'show_errors_to_users' => false,
        'log_full_stack_traces' => env('APP_ENV') !== 'production',
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Whitelisting (Optional)
    |--------------------------------------------------------------------------
    |
    | Restrict access to certain endpoints to specific IPs
    |
    */

    'ip_whitelist' => [
        'enabled' => env('IP_WHITELIST_ENABLED', false),
        'ips' => explode(',', env('IP_WHITELIST', '')),
        'protected_endpoints' => [
            '/admin/*',
            '/api/admin/*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Lockout
    |--------------------------------------------------------------------------
    |
    | Lockout accounts after failed login attempts
    |
    */

    'account_lockout' => [
        'enabled' => true,
        'max_attempts' => 5,
        'lockout_duration_minutes' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Log sensitive operations for security audit
    |
    */

    'audit_logging' => [
        'enabled' => true,
        'log_events' => [
            'user.login',
            'user.logout',
            'user.created',
            'user.deleted',
            'user.password_changed',
            'admin.action',
        ],
    ],
];
