# Jiny Chat Package

채팅 시스템을 위한 Laravel 패키지입니다. 실시간 채팅, 채팅방 관리, 사용자 관리 등의 기능을 제공합니다.

## 기능

### 채팅방 관리
- 공개/비공개 채팅방 생성
- 비밀번호 보호 채팅방
- 채팅방 타입 (public, private, group)
- 최대 참여자 수 제한
- 채팅방 검색 및 필터링

### 메시지 기능
- 실시간 메시지 전송/수신
- 메시지 타입 지원 (text, image, file, system)
- 메시지 읽음 상태 추적
- 메시지 삭제 (관리자)

### 사용자 관리
- 샤딩된 사용자 시스템 지원 (user_0xx 패턴)
- JWT 인증 연동
- 사용자 차단/해제
- 활동 상태 추적

### 관리자 기능
- 전체 채팅방 관리
- 사용자 관리 및 통계
- 메시지 모니터링
- 시스템 통계 및 대시보드

## 설치

### 1. 패키지 설치

```bash
composer require jiny/chat
```

### 2. 서비스 프로바이더 등록

`config/app.php`에 서비스 프로바이더를 추가합니다:

```php
'providers' => [
    // ...
    \Jiny\Chat\ChatServiceProvider::class,
],
```

### 3. 데이터베이스 마이그레이션

```bash
php artisan migrate --path=vendor/jiny/chat/database/migrations
```

### 4. 설정 파일 발행 (선택사항)

```bash
php artisan vendor:publish --provider="Jiny\Chat\ChatServiceProvider" --tag="config"
```

## 설정

### 기본 설정

`config/chat.php` 파일에서 채팅 시스템을 구성할 수 있습니다:

```php
return [
    // 샤딩 설정
    'sharding' => [
        'enabled' => config('auth.sharding.enabled', true),
        'pattern' => config('auth.sharding.pattern', 'user_%03d'),
        'max_shards' => config('auth.sharding.max_shards', 100),
    ],

    // JWT 인증 설정
    'jwt' => [
        'enabled' => config('auth.jwt.enabled', true),
        'secret' => config('auth.jwt.secret'),
        'algorithm' => config('auth.jwt.algorithm', 'HS256'),
    ],

    // 채팅방 기본 설정
    'room' => [
        'max_participants_default' => 100,
        'allow_public_rooms' => true,
        'allow_private_rooms' => true,
        'require_approval' => false,
    ],

    // 메시지 설정
    'message' => [
        'max_length' => 1000,
        'allowed_types' => ['text', 'image', 'file'],
        'enable_read_receipts' => true,
    ],

    // 브로드캐스팅 설정
    'broadcasting' => [
        'enabled' => false,
        'connection' => 'pusher',
    ],
];
```

### 사용자 샤딩

이 패키지는 Jiny Auth 패키지의 사용자 샤딩 시스템을 사용합니다:

```php
use Jiny\Auth\Facades\Shard;
use Jiny\Auth\Facades\JwtAuth;

// 사용자 정보 조회
$user = Shard::getUser($userUuid);

// JWT 인증
$token = JwtAuth::generateToken($user);
$user = JwtAuth::parseToken($token);
```

## 사용법

### 라우트

패키지는 다음 라우트를 제공합니다:

#### 사용자 라우트
- `GET /chat` - 채팅 대시보드
- `GET /chat/rooms` - 채팅방 목록
- `GET /chat/rooms/create` - 채팅방 생성 페이지
- `POST /chat/rooms` - 채팅방 생성 처리
- `GET /chat/rooms/{room}` - 채팅방 입장
- `POST /chat/rooms/{room}/join` - 채팅방 참여
- `DELETE /chat/rooms/{room}/leave` - 채팅방 나가기

#### 관리자 라우트 (`/admin/chat` prefix)
- `GET /admin/chat/stats` - 채팅 통계
- `GET /admin/chat/rooms` - 채팅방 관리
- `GET /admin/chat/users` - 사용자 관리
- `GET /admin/chat/messages` - 메시지 관리

### 컨트롤러 사용 예제

#### 채팅방 생성

```php
use Jiny\Chat\Http\Controllers\RoomController;

$controller = new RoomController();
$request = request()->merge([
    'title' => '새 채팅방',
    'description' => '채팅방 설명',
    'type' => 'public',
    'is_public' => true,
    'allow_join' => true,
]);

$response = $controller->store($request);
```

#### 메시지 전송

```php
use Jiny\Chat\Services\MessageService;

$messageService = new MessageService();
$message = $messageService->sendMessage([
    'room_id' => 1,
    'sender_uuid' => $user->uuid,
    'content' => '안녕하세요!',
    'type' => 'text'
]);
```

### 뷰 컴포넌트

#### 채팅방 목록

```blade
@extends('jiny-site::layouts.app')

@section('content')
<div class="container">
    @include('jiny-chat::rooms.list', ['rooms' => $rooms])
</div>
@endsection
```

#### 실시간 채팅

```blade
@extends('jiny-site::layouts.app')

@section('content')
<div class="container">
    @livewire('chat.room', ['roomId' => $room->id])
</div>
@endsection
```

## 브로드캐스팅 설정

실시간 채팅을 위한 브로드캐스팅 설정:

### 1. Laravel Echo 설치

```bash
npm install laravel-echo pusher-js
```

### 2. 브로드캐스팅 드라이버 설정

```php
// config/broadcasting.php
'connections' => [
    'pusher' => [
        'driver' => 'pusher',
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'encrypted' => true,
        ],
    ],
],
```

### 3. 큐 워커 시작

```bash
php artisan queue:work
```

## 테스트

### 테스트 실행

```bash
# 전체 테스트
php artisan test

# 채팅 관련 테스트만
php artisan test --filter=Chat

# 특정 테스트 클래스
php artisan test tests/Feature/ChatRoomTest.php
```

### 테스트 예제

```php
use Tests\TestCase;
use Jiny\Chat\Models\Room;

class ChatRoomTest extends TestCase
{
    public function test_can_create_chat_room()
    {
        $response = $this->post('/chat/rooms', [
            'title' => '테스트 채팅방',
            'type' => 'public',
            'is_public' => true,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('chat_rooms', [
            'title' => '테스트 채팅방'
        ]);
    }
}
```

## API 문서

### 채팅방 API

#### 채팅방 생성
```
POST /chat/rooms
Content-Type: application/json

{
    "title": "채팅방 제목",
    "description": "채팅방 설명",
    "type": "public|private|group",
    "is_public": true,
    "allow_join": true,
    "allow_invite": true,
    "password": "선택적 비밀번호",
    "max_participants": 100
}
```

#### 메시지 전송
```
POST /chat/rooms/{roomId}/messages
Content-Type: application/json

{
    "content": "메시지 내용",
    "type": "text|image|file"
}
```

## 문제해결

### 일반적인 문제

1. **404 에러 발생**
   - 서비스 프로바이더가 등록되었는지 확인
   - 마이그레이션이 실행되었는지 확인
   - 라우트 캐시를 클리어: `php artisan route:clear`

2. **데이터베이스 테이블 없음**
   ```bash
   php artisan migrate --path=vendor/jiny/chat/database/migrations
   ```

3. **레이아웃 컴포넌트 오류**
   - jiny-site 패키지가 설치되었는지 확인
   - 올바른 레이아웃을 사용하고 있는지 확인

### 로그 확인

```bash
tail -f storage/logs/laravel.log
```

## 기여하기

1. 포크하기
2. 기능 브랜치 생성 (`git checkout -b feature/amazing-feature`)
3. 변경사항 커밋 (`git commit -m 'Add amazing feature'`)
4. 브랜치에 푸시 (`git push origin feature/amazing-feature`)
5. Pull Request 생성

## 라이선스

이 패키지는 MIT 라이선스 하에 제공됩니다.

## 지원

- 이슈: [GitHub Issues](https://github.com/jinyphp/chat/issues)
- 문서: [패키지 문서](https://docs.jinyphp.com/chat)
- 커뮤니티: [JinyPHP 커뮤니티](https://community.jinyphp.com)

## 변경 이력

### v1.0.0
- 초기 릴리스
- 기본 채팅방 기능
- 사용자 관리 시스템
- 관리자 대시보드

---

**JinyPHP Team** - 더 나은 웹 개발을 위한 Laravel 패키지
