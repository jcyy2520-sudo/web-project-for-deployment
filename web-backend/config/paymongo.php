<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PayMongo API Keys
    |--------------------------------------------------------------------------
    |
    | Secret key is used server-side for creating checkout sessions and
    | verifying webhooks. Public key is exposed to the frontend for
    | client-side operations (not currently used but available).
    |
    */

    'secret_key' => env('PAYMONGO_SECRET_KEY'),
    'public_key' => env('PAYMONGO_PUBLIC_KEY'),
    'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */

    'webhook_url' => env(
        'PAYMONGO_WEBHOOK_URL',
        rtrim(env('APP_URL', 'http://localhost'), '/') . '/api/paymongo/webhook'
    ),
    'webhook_tolerance_seconds' => (int) env('PAYMONGO_WEBHOOK_TOLERANCE', 300),

    /*
    |--------------------------------------------------------------------------
    | Frontend Redirect URL
    |--------------------------------------------------------------------------
    */

    'frontend_url' => env(
        'PAYMONGO_FRONTEND_URL',
        env('FRONTEND_URL', env('APP_URL', 'http://localhost:5173'))
    ),

    /*
    |--------------------------------------------------------------------------
    | PayMongo API Base URL
    |--------------------------------------------------------------------------
    */

    'api_base_url' => env('PAYMONGO_API_BASE_URL', 'https://api.paymongo.com/v1'),

    /*
    |--------------------------------------------------------------------------
    | Supported Payment Methods
    |--------------------------------------------------------------------------
    |
    | These are the payment method types available in the checkout session.
    | PayMongo supports: card, gcash, grab_pay, paymaya, dob, dob_ubp, brankas_*
    |
    */

    'payment_method_types' => [
        'card',
        'gcash',
        'grab_pay',
        'paymaya',
    ],
];
