<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Chat Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your chat settings for the Jiny Chat package.
    |
    */

    'table_prefix' => 'chat_',

    /*
    |--------------------------------------------------------------------------
    | Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | Chat authentication uses jiny/auth package's Shard:: and JwtAuth:: facades
    | No additional configuration needed - references jiny/auth package settings
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Real-time Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for real-time chat features
    |
    */
    'realtime' => [
        'driver' => 'pusher', // pusher, redis, socket.io
        'pusher' => [
            'app_id' => env('PUSHER_APP_ID'),
            'app_key' => env('PUSHER_APP_KEY'),
            'app_secret' => env('PUSHER_APP_SECRET'),
            'app_cluster' => env('PUSHER_APP_CLUSTER'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for chat messages
    |
    */
    'message' => [
        'max_length' => 1000,
        'allow_images' => true,
        'allow_files' => false,
        'image_max_size' => 2048, // KB
        'storage_path' => 'chat/images',
    ],

    /*
    |--------------------------------------------------------------------------
    | Room Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for chat rooms
    |
    */
    'room' => [
        'max_participants' => 100,
        'allow_private' => true,
        'allow_password' => true,
        'auto_cleanup' => true,
        'cleanup_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    |
    | User interface settings
    |
    */
    'ui' => [
        'theme' => 'default',
        'colors' => [
            'bg_color' => 'bg-gray-50',
            'send_color' => 'bg-blue-500',
            'receive_color' => 'bg-gray-200',
            'user_color' => 'bg-green-500',
        ],
        'layout' => 'modern', // modern, classic
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security and moderation settings
    |
    */
    'security' => [
        'encryption' => true,
        'moderation' => true,
        'spam_detection' => true,
        'rate_limiting' => [
            'enabled' => true,
            'max_messages_per_minute' => 10,
        ],
    ],
];