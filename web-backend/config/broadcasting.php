<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    */
    'default' => env('BROADCAST_DRIVER', env('BROADCASTER', 'log')),

    'connections' => [
        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY', env('VITE_PUSHER_KEY')),
            'secret' => env('PUSHER_APP_SECRET', null),
            'app_id' => env('PUSHER_APP_ID', null),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER', env('VITE_PUSHER_CLUSTER')),
                'useTLS' => env('PUSHER_USE_TLS', true),
                'host' => env('PUSHER_APP_HOST', env('VITE_PUSHER_HOST', null)),
                'port' => env('PUSHER_APP_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],
];
