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

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */

    'webhook_url' => env('PAYMONGO_WEBHOOK_URL', 'https://legalease.com/webhook'),

    /*
    |--------------------------------------------------------------------------
    | PayMongo API Base URL
    |--------------------------------------------------------------------------
    */

    'api_base_url' => 'https://api.paymongo.com/v1',

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
