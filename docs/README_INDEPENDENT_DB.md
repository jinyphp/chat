# Jiny Chat - 독립적인 채팅방 데이터베이스 시스템

각 채팅방마다 독립적인 SQLite 데이터베이스를 사용하여 메시지, 번역 데이터, 파일 정보 등을 분리 관리하는 시스템입니다.

## 🎯 주요 기능

- ✅ **채팅방별 독립 SQLite 데이터베이스**: 각 채팅방마다 별도의 SQLite 파일 생성
- ✅ **동적 데이터베이스 연결 관리**: 요청 시점에 해당 채팅방의 데이터베이스에 자동 연결
- ✅ **완전한 데이터 분리**: 메시지, 파일, 번역, 통계 데이터가 채팅방별로 독립적으로 저장
- ✅ **확장성**: 대용량 채팅 데이터를 효율적으로 분산 저장
- ✅ **데이터베이스 관리 도구**: 백업, 최적화, 크기 조회 등의 관리 기능
- ✅ **하위 호환성**: 기존 단일 테이블 방식과 병행 사용 가능

## 🏗️ 아키텍처

### 디렉토리 구조
```
storage/
└── chat/
    ├── {해시코드 0-1}/
    │   └── {해시코드 2-3}/
    │       └── {해시코드 4-5}/
    │           └── {채팅방코드}.sqlite
    └── backups/
        └── {채팅방코드}_{날짜시간}.sqlite
```

### 데이터베이스 테이블 구조
각 채팅방의 SQLite 파일에는 다음 테이블들이 생성됩니다:

- **chat_messages**: 메시지 데이터
- **chat_message_reads**: 메시지 읽음 상태
- **chat_message_translations**: 메시지 번역 데이터
- **chat_files**: 파일 정보
- **chat_message_favourites**: 메시지 즐겨찾기
- **chat_room_stats**: 채팅방 통계

## 🚀 사용법

### 1. 새 채팅방 생성

```php
use Jiny\Chat\Models\ChatRoom;

// 채팅방 생성 (독립 데이터베이스 자동 생성)
$room = ChatRoom::createRoom([
    'title' => '프로젝트 토론방',
    'description' => '프로젝트 관련 토론을 위한 채팅방',
    'type' => 'public',
    'owner_uuid' => $userUuid,
]);

// 채팅방 코드를 통해 독립 데이터베이스에 접근
echo $room->code; // 예: room_abc12345
```

### 2. 메시지 전송

```php
// 방법 1: ChatRoom 모델을 통한 전송
$message = $room->sendMessage($senderUuid, [
    'content' => '안녕하세요!',
    'type' => 'text',
]);

// 방법 2: 직접 독립 데이터베이스 사용
use Jiny\Chat\Models\ChatRoomMessage;

$message = ChatRoomMessage::createMessage($room->code, $senderUuid, [
    'content' => '안녕하세요!',
    'type' => 'text',
]);
```

### 3. 파일 업로드

```php
// 파일 업로드 (독립 데이터베이스에 메타데이터 저장)
$chatFile = $room->uploadFile($uploaderUuid, $uploadedFile);

// 또는 직접 사용
use Jiny\Chat\Models\ChatRoomFile;

$chatFile = ChatRoomFile::uploadFile($room->code, $uploadedFile, $uploaderUuid);
```

### 4. 메시지 조회

```php
use Jiny\Chat\Models\ChatRoomMessage;

// 특정 채팅방의 메시지 조회
$messages = ChatRoomMessage::forRoom($room->code)
    ->notDeleted()
    ->orderBy('created_at', 'desc')
    ->limit(50)
    ->get();

// 특정 사용자의 메시지 조회
$userMessages = ChatRoomMessage::forRoom($room->code)
    ->bySender($userUuid)
    ->get();

// 메시지 검색
$searchResults = ChatRoomMessage::forRoom($room->code)
    ->where('content', 'LIKE', "%검색어%")
    ->get();
```

### 5. 메시지 번역

```php
use Jiny\Chat\Models\ChatRoomMessageTranslation;

// 수동 번역
$translation = ChatRoomMessageTranslation::translateMessage(
    $room->code,
    $messageId,
    'en',
    'Hello World!'
);

// 자동 번역 (Google Translate)
$translation = ChatRoomMessageTranslation::autoTranslate(
    $room->code,
    $messageId,
    'en',
    'ko'
);

// 여러 언어로 동시 번역
$translations = ChatRoomMessageTranslation::translateToMultipleLanguages(
    $room->code,
    $messageId,
    ['en', 'ja', 'zh']
);
```

### 6. 즐겨찾기 관리

```php
use Jiny\Chat\Models\ChatRoomMessageFavourite;

// 즐겨찾기 토글
$isFavourite = ChatRoomMessageFavourite::toggleFavourite(
    $room->code,
    $messageId,
    $userUuid,
    '중요한 메시지' // 선택적 메모
);

// 사용자의 즐겨찾기 목록 조회
$favourites = ChatRoomMessageFavourite::getUserFavourites($room->code, $userUuid);
```

### 7. 통계 조회

```php
use Jiny\Chat\Models\ChatRoomStats;

// 피크 시간대 분석
$peakHours = ChatRoomStats::getPeakHours($room->code, 7); // 최근 7일

// 활발한 사용자 분석
$activeUsers = ChatRoomStats::getActiveUsers($room->code, 7);

// 월별 요약 통계
$monthlySummary = ChatRoomStats::getMonthlySummary($room->code, 2024, 10);

// 또는 ChatRoom을 통해 통합 조회
$stats = $room->getStats(7);
```

### 8. 데이터베이스 관리

```php
use Jiny\Chat\Helpers\ChatDatabaseManager;

// 데이터베이스 크기 조회
$size = $room->getDatabaseSize();
$sizeInBytes = ChatDatabaseManager::getChatDatabaseSize($room->code);

// 데이터베이스 백업
$backupResult = $room->backupDatabase();
$backupPath = ChatDatabaseManager::backupChatDatabase($room->code, '/custom/backup/path');

// 데이터베이스 최적화 (VACUUM)
$optimizeResult = $room->optimizeDatabase();
ChatDatabaseManager::optimizeChatDatabase($room->code);

// 모든 채팅방 데이터베이스 목록 조회
$databases = ChatDatabaseManager::getAllChatDatabases();
```

## 🧪 테스트 및 개발

### 테스트 페이지 접근
개발 환경에서 다음 URL로 테스트 페이지에 접근할 수 있습니다:

```
http://your-domain.com/chat/test
```

### 서비스 클래스 사용

```php
use Jiny\Chat\Services\ChatRoomDatabaseService;

$chatService = app(ChatRoomDatabaseService::class);

// 새 채팅방 생성
$room = $chatService->createChatRoom([
    'title' => '테스트 채팅방',
    'owner_uuid' => 'user-123',
]);

// 메시지 전송
$message = $chatService->sendMessage($room->code, 'user-123', [
    'content' => '테스트 메시지',
]);

// 파일 업로드
$file = $chatService->uploadFile($room->code, $uploadedFile, 'user-123');

// 통계 조회
$stats = $chatService->getRoomStats($room->code);

// 데이터베이스 연결 테스트
$connectionTest = $chatService->testDatabaseConnection($room->code);
```

## 📊 성능 및 모니터링

### 데이터베이스 모니터링

```php
// 모든 채팅방 데이터베이스 현황
$databases = ChatDatabaseManager::getAllChatDatabases();

foreach ($databases as $db) {
    echo "Room: {$db['room_code']}\n";
    echo "Size: " . number_format($db['size'] / 1024 / 1024, 2) . " MB\n";
    echo "Modified: {$db['modified_at']}\n\n";
}

// 특정 채팅방의 상세 통계
$stats = ChatRoomStats::getMonthlySummary($roomCode, date('Y'), date('n'));
echo "이번 달 메시지: {$stats['total_messages']}\n";
echo "이번 달 파일: {$stats['total_files']}\n";
echo "총 파일 크기: " . number_format($stats['total_file_size'] / 1024 / 1024, 2) . " MB\n";
```

### 성능 최적화

```php
// 주기적 데이터베이스 최적화 (스케줄러에서 실행)
$rooms = ChatRoom::whereNotNull('code')->get();

foreach ($rooms as $room) {
    try {
        $sizeBefore = $room->getDatabaseSize();
        $room->optimizeDatabase();
        $sizeAfter = $room->getDatabaseSize();

        $saved = $sizeBefore - $sizeAfter;
        if ($saved > 0) {
            \Log::info("Room {$room->code} optimized: saved " . number_format($saved / 1024, 2) . " KB");
        }
    } catch (\Exception $e) {
        \Log::error("Failed to optimize room {$room->code}: " . $e->getMessage());
    }
}
```

## 🔄 마이그레이션

기존 단일 테이블에서 독립 데이터베이스로 마이그레이션:

```php
use Jiny\Chat\Services\ChatRoomDatabaseService;

$chatService = app(ChatRoomDatabaseService::class);

// 특정 채팅방 마이그레이션
$result = $chatService->migrateChatRoomData($roomId);

if ($result['success']) {
    echo "마이그레이션 완료: {$result['migrated_messages']}개 메시지\n";
    echo "데이터베이스 크기: {$result['database_size']} bytes\n";
} else {
    echo "마이그레이션 실패: {$result['error']}\n";
}
```

## 🛠️ 설정

### 환경 설정

`.env` 파일에 다음 설정을 추가할 수 있습니다:

```env
# 채팅 관련 설정
CHAT_USE_INDEPENDENT_DATABASE=true
CHAT_DATABASE_PATH=storage/chat
CHAT_BACKUP_PATH=storage/chat/backups
CHAT_AUTO_OPTIMIZE=true
CHAT_CLEANUP_OLD_BACKUPS=true
CHAT_MAX_DATABASE_SIZE=100MB
```

### 설정 파일

`config/chat.php`:

```php
return [
    'independent_database' => [
        'enabled' => env('CHAT_USE_INDEPENDENT_DATABASE', true),
        'storage_path' => env('CHAT_DATABASE_PATH', 'storage/chat'),
        'backup_path' => env('CHAT_BACKUP_PATH', 'storage/chat/backups'),
        'auto_optimize' => env('CHAT_AUTO_OPTIMIZE', true),
        'max_size' => env('CHAT_MAX_DATABASE_SIZE', '100MB'),
    ],
];
```

## 🔍 디버깅

### 로그 모니터링

독립 데이터베이스 관련 모든 작업은 로그에 기록됩니다:

```bash
# 채팅방 생성 로그
tail -f storage/logs/laravel.log | grep "독립 데이터베이스 채팅방 생성"

# 메시지 전송 로그
tail -f storage/logs/laravel.log | grep "독립 데이터베이스 메시지 전송"

# 파일 업로드 로그
tail -f storage/logs/laravel.log | grep "독립 데이터베이스 파일 업로드"
```

### 문제 해결

1. **데이터베이스 연결 실패**
   ```php
   // 연결 테스트
   $result = ChatDatabaseManager::getChatConnection($roomCode);
   if (!$result) {
       // 데이터베이스 재생성
       ChatDatabaseManager::createChatDatabase($roomCode);
   }
   ```

2. **권한 문제**
   ```bash
   # 스토리지 디렉토리 권한 확인
   chmod -R 755 storage/chat
   chown -R www-data:www-data storage/chat
   ```

3. **디스크 용량 문제**
   ```php
   // 큰 데이터베이스 찾기
   $databases = ChatDatabaseManager::getAllChatDatabases();
   $largeDatabases = array_filter($databases, function($db) {
       return $db['size'] > 50 * 1024 * 1024; // 50MB 이상
   });
   ```

## 📈 향후 개선 사항

- [ ] 샤딩 전략 개선 (해시 기반 → 일관된 해싱)
- [ ] 압축 기능 추가 (메시지 내용 압축)
- [ ] 자동 아카이빙 (오래된 메시지 별도 저장)
- [ ] 실시간 복제 (고가용성)
- [ ] 메트릭 수집 (Prometheus/Grafana 연동)
- [ ] 데이터베이스 분할 정책 (크기/시간 기반)

---

이 시스템을 통해 각 채팅방의 데이터를 완전히 독립적으로 관리하면서도 성능과 확장성을 크게 개선할 수 있습니다.