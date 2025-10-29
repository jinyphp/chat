<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Jiny Chat Broadcasting Configuration
    |--------------------------------------------------------------------------
    |
    | 채팅 시스템의 실시간 기능을 위한 브로드캐스팅 설정입니다.
    | WebSocket, Pusher, Redis 등 다양한 드라이버를 지원합니다.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | 기본 브로드캐스터를 설정합니다.
    | 개발 환경에서는 'log', 프로덕션에서는 'pusher' 또는 'redis'를 권장합니다.
    |
    */

    'default' => env('CHAT_BROADCAST_DRIVER', env('BROADCAST_DRIVER', 'null')),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | 채팅 시스템에서 사용할 브로드캐스트 연결 설정입니다.
    |
    */

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'encrypted' => true,
                'host' => env('PUSHER_HOST', 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusherapp.com'),
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
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

    ],

    /*
    |--------------------------------------------------------------------------
    | Chat Channels Configuration
    |--------------------------------------------------------------------------
    |
    | 채팅 시스템에서 사용하는 채널들의 설정입니다.
    |
    */

    'channels' => [

        /*
        |--------------------------------------------------------------------------
        | Channel Prefixes
        |--------------------------------------------------------------------------
        |
        | 채널 이름의 접두사를 설정합니다.
        |
        */

        'prefixes' => [
            'room' => 'chat-room',
            'user' => 'chat-user',
            'typing' => 'chat-typing',
            'presence' => 'chat-presence',
        ],

        /*
        |--------------------------------------------------------------------------
        | Private Channel Authentication
        |--------------------------------------------------------------------------
        |
        | 프라이빗 채널 인증 설정입니다.
        |
        */

        'auth' => [
            'middleware' => ['jwt'], // JWT 인증 미들웨어 사용
            'guard' => null,
        ],

        /*
        |--------------------------------------------------------------------------
        | Presence Channel Configuration
        |--------------------------------------------------------------------------
        |
        | 프레즌스 채널 설정 (온라인 사용자 목록 표시용)
        |
        */

        'presence' => [
            'enabled' => env('CHAT_PRESENCE_ENABLED', true),
            'timeout' => env('CHAT_PRESENCE_TIMEOUT', 30), // 30초 후 오프라인으로 간주
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Real-time Features
    |--------------------------------------------------------------------------
    |
    | 실시간 기능들의 활성화 여부를 설정합니다.
    |
    */

    'features' => [

        /*
        |--------------------------------------------------------------------------
        | Typing Indicators
        |--------------------------------------------------------------------------
        |
        | 타이핑 상태 표시 기능 설정
        |
        */

        'typing' => [
            'enabled' => env('CHAT_TYPING_ENABLED', true),
            'timeout' => env('CHAT_TYPING_TIMEOUT', 3000), // 3초 후 자동 해제 (밀리초)
            'throttle' => env('CHAT_TYPING_THROTTLE', 1000), // 1초마다 최대 한 번 전송
        ],

        /*
        |--------------------------------------------------------------------------
        | Message Reactions
        |--------------------------------------------------------------------------
        |
        | 메시지 반응 실시간 업데이트 설정
        |
        */

        'reactions' => [
            'enabled' => env('CHAT_REACTIONS_ENABLED', true),
            'realtime' => env('CHAT_REACTIONS_REALTIME', true),
        ],

        /*
        |--------------------------------------------------------------------------
        | Read Receipts
        |--------------------------------------------------------------------------
        |
        | 읽음 확인 실시간 업데이트 설정
        |
        */

        'read_receipts' => [
            'enabled' => env('CHAT_READ_RECEIPTS_ENABLED', true),
            'realtime' => env('CHAT_READ_RECEIPTS_REALTIME', true),
            'batch_size' => env('CHAT_READ_RECEIPTS_BATCH', 10), // 배치 처리 크기
        ],

        /*
        |--------------------------------------------------------------------------
        | Online Status
        |--------------------------------------------------------------------------
        |
        | 온라인 상태 실시간 업데이트 설정
        |
        */

        'online_status' => [
            'enabled' => env('CHAT_ONLINE_STATUS_ENABLED', true),
            'update_interval' => env('CHAT_ONLINE_STATUS_INTERVAL', 60), // 1분마다 업데이트
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | 성능 최적화를 위한 설정들
    |
    */

    'performance' => [

        /*
        |--------------------------------------------------------------------------
        | Message Broadcasting
        |--------------------------------------------------------------------------
        |
        | 메시지 브로드캐스팅 최적화 설정
        |
        */

        'message_broadcast' => [
            'queue' => env('CHAT_BROADCAST_QUEUE', true), // 큐를 통한 비동기 처리
            'queue_connection' => env('CHAT_BROADCAST_QUEUE_CONNECTION', 'default'),
            'batch_size' => env('CHAT_BROADCAST_BATCH_SIZE', 100), // 배치 처리 크기
        ],

        /*
        |--------------------------------------------------------------------------
        | Channel Limits
        |--------------------------------------------------------------------------
        |
        | 채널별 제한 설정
        |
        */

        'limits' => [
            'max_subscribers_per_room' => env('CHAT_MAX_SUBSCRIBERS_PER_ROOM', 1000),
            'max_messages_per_minute' => env('CHAT_MAX_MESSAGES_PER_MINUTE', 60),
            'max_typing_events_per_minute' => env('CHAT_MAX_TYPING_PER_MINUTE', 30),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | 보안 관련 설정들
    |
    */

    'security' => [

        /*
        |--------------------------------------------------------------------------
        | Channel Authorization
        |--------------------------------------------------------------------------
        |
        | 채널 접근 권한 확인 설정
        |
        */

        'authorization' => [
            'strict_mode' => env('CHAT_STRICT_AUTH', true), // 엄격한 인증 모드
            'token_validation' => env('CHAT_TOKEN_VALIDATION', true), // 토큰 유효성 검사
        ],

        /*
        |--------------------------------------------------------------------------
        | Rate Limiting
        |--------------------------------------------------------------------------
        |
        | 레이트 리미팅 설정
        |
        */

        'rate_limiting' => [
            'enabled' => env('CHAT_RATE_LIMITING', true),
            'max_attempts' => env('CHAT_RATE_LIMIT_ATTEMPTS', 60),
            'decay_minutes' => env('CHAT_RATE_LIMIT_DECAY', 1),
        ],

        /*
        |--------------------------------------------------------------------------
        | Content Filtering
        |--------------------------------------------------------------------------
        |
        | 콘텐츠 필터링 설정
        |
        */

        'content_filter' => [
            'enabled' => env('CHAT_CONTENT_FILTER', true),
            'profanity_filter' => env('CHAT_PROFANITY_FILTER', true),
            'spam_detection' => env('CHAT_SPAM_DETECTION', true),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | 브로드캐스팅 관련 로깅 설정
    |
    */

    'logging' => [
        'enabled' => env('CHAT_BROADCAST_LOGGING', false),
        'level' => env('CHAT_BROADCAST_LOG_LEVEL', 'info'),
        'channel' => env('CHAT_BROADCAST_LOG_CHANNEL', 'single'),
    ],

];