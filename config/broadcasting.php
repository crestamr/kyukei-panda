<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => env('BROADCAST_DRIVER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over websockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY', 'kyukei-panda-key'),
            'secret' => env('PUSHER_APP_SECRET', 'kyukei-panda-secret'),
            'app_id' => env('PUSHER_APP_ID', 'kyukei-panda-app'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
                'host' => env('PUSHER_HOST') ?: 'api-' . env('PUSHER_APP_CLUSTER', 'mt1') . '.pusherapp.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
            ],
            'client_options' => [
                'timeout' => 60,
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
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

        // Kyukei-Panda WebSocket Server
        'kyukei-websocket' => [
            'driver' => 'pusher',
            'key' => env('KYUKEI_WEBSOCKET_KEY', 'kyukei-panda-local'),
            'secret' => env('KYUKEI_WEBSOCKET_SECRET', 'kyukei-panda-secret'),
            'app_id' => env('KYUKEI_WEBSOCKET_APP_ID', 'kyukei-panda'),
            'options' => [
                'host' => env('KYUKEI_WEBSOCKET_HOST', '127.0.0.1'),
                'port' => env('KYUKEI_WEBSOCKET_PORT', 6001),
                'scheme' => env('KYUKEI_WEBSOCKET_SCHEME', 'http'),
                'useTLS' => env('KYUKEI_WEBSOCKET_SCHEME', 'http') === 'https',
                'encrypted' => false,
                'enabledTransports' => ['ws', 'wss'],
            ],
            'client_options' => [
                'timeout' => 60,
            ],
        ],

    ],

];
