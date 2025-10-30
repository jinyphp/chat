<?php

use Illuminate\Support\Facades\Route;

/**
 * Jiny Chat 웹 안내 라우트
 *
 * [라우트 구조]
 * - 채팅 서비스 소개 및 안내 페이지
 * - 공개 접근 가능한 정보 페이지
 * - 서비스 소개, 가이드, 문서
 *
 * [접근 권한]
 * - 인증 불필요 (공개 페이지)
 * - 채팅 서비스 홍보 및 안내
 * - 서비스 이용 가이드
 */

// 지니채팅 시스템 메인 페이지 (제품 설명)
Route::get('/chat', function () {
    $features = [
        [
            'icon' => '💬',
            'title' => '실시간 채팅',
            'description' => '빠르고 안정적인 실시간 메시지 전송으로 원활한 소통이 가능합니다.'
        ],
        [
            'icon' => '🔒',
            'title' => '보안',
            'description' => 'JWT 인증과 암호화를 통해 안전한 채팅 환경을 제공합니다.'
        ],
        [
            'icon' => '📱',
            'title' => '반응형 디자인',
            'description' => '모바일, 태블릿, 데스크톱 모든 기기에서 최적화된 경험을 제공합니다.'
        ],
        [
            'icon' => '⚙️',
            'title' => '고급 설정',
            'description' => '채팅방 타입, 권한 관리, 알림 설정 등 다양한 옵션을 제공합니다.'
        ],
        [
            'icon' => '👥',
            'title' => '그룹 채팅',
            'description' => '공개방, 비공개방, 그룹방 등 다양한 형태의 채팅방을 지원합니다.'
        ],
        [
            'icon' => '📊',
            'title' => '관리 도구',
            'description' => '관리자를 위한 통계, 모니터링, 사용자 관리 기능을 제공합니다.'
        ]
    ];

    $stats = [
        'total_rooms' => 12,
        'total_messages' => 1547,
        'active_users' => 89,
    ];

    return view('jiny-chat::www.index', compact('features', 'stats'));
})->name('chat.index');

// 채팅 서비스 특징 소개
Route::get('/chat/features', function () {
    $features = [
        [
            'category' => '채팅 기능',
            'items' => [
                '실시간 메시지 전송 및 수신',
                '메시지 읽음 상태 확인',
                '파일 및 이미지 공유',
                '메시지 검색 기능',
                '메시지 고정 및 반응',
            ]
        ],
        [
            'category' => '채팅방 관리',
            'items' => [
                '공개/비공개 채팅방 생성',
                '채팅방 비밀번호 설정',
                '참여자 수 제한',
                '초대 링크 생성',
                '역할 기반 권한 관리',
            ]
        ],
        [
            'category' => '보안 및 프라이버시',
            'items' => [
                'JWT 기반 인증 시스템',
                '메시지 암호화',
                '사용자 차단 기능',
                '스팸 방지 시스템',
                '속도 제한 (Rate Limiting)',
            ]
        ],
        [
            'category' => '관리자 기능',
            'items' => [
                '실시간 채팅 통계',
                '사용자 관리 및 모니터링',
                '메시지 내용 관리',
                '채팅방 강제 종료',
                '시스템 상태 모니터링',
            ]
        ]
    ];

    return view('jiny-chat::www.features', compact('features'));
})->name('chat.features');

// 채팅 서비스 시작 가이드
Route::get('/chat/guide', function () {
    $steps = [
        [
            'step' => 1,
            'title' => '회원가입 및 로그인',
            'description' => '지니채팅을 이용하기 위해 먼저 회원가입을 진행하고 로그인하세요.',
            'action' => '로그인하기',
            'url' => '/login'
        ],
        [
            'step' => 2,
            'title' => '채팅방 둘러보기',
            'description' => '공개되어 있는 채팅방 목록을 확인하고 관심있는 주제의 채팅방을 찾아보세요.',
            'action' => '채팅방 보기',
            'url' => '/home/chat/rooms'
        ],
        [
            'step' => 3,
            'title' => '채팅방 참여하기',
            'description' => '원하는 채팅방에 참여하여 다른 사용자들과 대화를 시작하세요.',
            'action' => '채팅 시작',
            'url' => '/home/chat'
        ],
        [
            'step' => 4,
            'title' => '나만의 채팅방 만들기',
            'description' => '원하는 주제의 채팅방이 없다면 직접 새로운 채팅방을 만들어보세요.',
            'action' => '채팅방 만들기',
            'url' => '/home/chat/rooms/create'
        ]
    ];

    return view('jiny-chat::www.guide', compact('steps'));
})->name('chat.guide');

// API 문서
Route::get('/chat/api-docs', function () {
    $endpoints = [
        [
            'method' => 'GET',
            'endpoint' => '/api/chat/rooms',
            'description' => '채팅방 목록 조회',
            'auth' => 'JWT 토큰 필요'
        ],
        [
            'method' => 'POST',
            'endpoint' => '/api/chat/rooms',
            'description' => '새 채팅방 생성',
            'auth' => 'JWT 토큰 필요'
        ],
        [
            'method' => 'GET',
            'endpoint' => '/api/chat/rooms/{id}/messages',
            'description' => '채팅방 메시지 목록',
            'auth' => 'JWT 토큰 필요'
        ],
        [
            'method' => 'POST',
            'endpoint' => '/api/chat/messages',
            'description' => '메시지 전송',
            'auth' => 'JWT 토큰 필요'
        ],
        [
            'method' => 'POST',
            'endpoint' => '/api/chat/rooms/{id}/join',
            'description' => '채팅방 참여',
            'auth' => 'JWT 토큰 필요'
        ],
    ];

    return view('jiny-chat::www.api', compact('endpoints'));
})->name('chat.api');

// 개발자 문서
Route::get('/chat/docs', function () {
    $technologies = [
        [
            'name' => 'Laravel 12',
            'description' => '최신 PHP 프레임워크 기반 안정적인 백엔드'
        ],
        [
            'name' => 'JWT Authentication',
            'description' => '토큰 기반 인증으로 보안성 향상'
        ],
        [
            'name' => 'WebSocket/Broadcasting',
            'description' => '실시간 메시지 전송을 위한 브로드캐스팅'
        ],
        [
            'name' => 'User Sharding',
            'description' => '대용량 사용자 처리를 위한 샤딩 시스템'
        ],
        [
            'name' => 'Tailwind CSS',
            'description' => '반응형 및 모던 UI 디자인'
        ]
    ];

    return view('jiny-chat::www.api', compact('technologies'));
})->name('chat.docs');

// SSE (Server-Sent Events) 라우트
Route::get('/chat/sse/{roomId}', [\Jiny\Chat\Http\Controllers\ChatSseController::class, 'stream'])
    ->name('chat.sse.stream');

Route::post('/chat/sse/{roomId}/typing', [\Jiny\Chat\Http\Controllers\ChatSseController::class, 'updateTyping'])
    ->name('chat.sse.typing');

Route::get('/chat/sse/{roomId}/status', [\Jiny\Chat\Http\Controllers\ChatSseController::class, 'status'])
    ->name('chat.sse.status');

// 초대 링크 관련 라우트
Route::get('/chat/join/{token}', [\Jiny\Chat\Http\Controllers\ChatInviteController::class, 'join'])
    ->name('chat.join');

Route::get('/chat/invite/{token}', [\Jiny\Chat\Http\Controllers\ChatInviteController::class, 'preview'])
    ->name('chat.invite.preview');