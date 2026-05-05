<?php

$reverbHost = env('REVERB_HOST');

if (in_array($reverbHost, [null, ''], true)) {
    $reverbHost = parse_url(env('APP_URL', 'http://127.0.0.1'), PHP_URL_HOST) ?: '127.0.0.1';
} elseif ($reverbHost === '0.0.0.0') {
    $reverbHost = env('REVERB_SERVER_HOST');

    if (in_array($reverbHost, [null, '', '0.0.0.0'], true)) {
        $reverbHost = '127.0.0.1';
    }
}

return [
    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    */
    'default' => env('BROADCAST_CONNECTION', env('BROADCAST_DRIVER', env('BROADCASTER', 'log'))),

    'connections' => [
        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => $reverbHost,
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            'client_options' => [],
        ],

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
