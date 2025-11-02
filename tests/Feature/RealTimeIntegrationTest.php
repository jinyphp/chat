<?php

namespace Jiny\Chat\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Events\MessageSent;
use App\Models\User;

class RealTimeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_message_workflow_end_to_end()
    {
        // Given: 기본 데이터 설정
        $user = User::factory()->create([
            'name' => '실시간 테스트 사용자',
            'email' => 'realtime@test.com',
            'uuid' => 'realtime-user-uuid'
        ]);

        $chatRoom = ChatRoom::create([
            'title' => '실시간 통합 테스트방',
            'slug' => 'realtime-integration-test',
            'type' => 'group',
            'code' => 'realtime_test_001',
            'is_public' => true,
            'allow_join' => true,
            'allow_invite' => true,
            'max_participants' => 10,
            'owner_uuid' => $user->uuid,
        ]);

        $participant = ChatParticipant::create([
            'room_id' => $chatRoom->id,
            'room_uuid' => $chatRoom->code,
            'user_uuid' => $user->uuid,
            'shard_id' => 1,
            'email' => $user->email,
            'name' => $user->name,
            'role' => 'owner',
            'status' => 'active',
            'permissions' => json_encode(['send_message', 'read_message']),
            'can_send_message' => 1,
            'can_invite' => 1,
            'can_moderate' => 1,
            'notifications_enabled' => 1,
            'notification_settings' => json_encode(['mentions' => true, 'all_messages' => true]),
            'last_read_at' => now(),
            'last_read_message_id' => 0,
            'unread_count' => 0,
            'joined_at' => now(),
            'last_seen_at' => now(),
            'language' => 'ko'
        ]);

        $this->createTestSqliteDatabase($chatRoom);

        // When & Then: 단계별 테스트
        $this->performMessageCreationTest($user, $chatRoom);
        $this->performSseEventTest($user, $chatRoom);
        $this->performMessageListTest($user, $chatRoom);

        $this->cleanupTestDatabase($chatRoom);
    }

    private function performMessageCreationTest($user, $chatRoom)
    {
        Log::info('=== 메시지 작성 테스트 시작 ===');

        // 메시지 작성을 직접 컨트롤러 메서드를 통해 테스트
        $messageController = new \Jiny\Chat\Http\Controllers\Home\Room\MessageController();

        $request = new \Illuminate\Http\Request();
        $request->merge([
            'message' => '통합 테스트 메시지',
            'message_type' => 'text'
        ]);

        // 사용자 인증 모킹
        $this->actingAs($user);

        try {
            $result = $messageController->store($request, $chatRoom->id);
            $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $result);

            $data = $result->getData(true);
            $this->assertTrue($data['success'] ?? false);
            $this->assertArrayHasKey('data', $data);
            $this->assertArrayHasKey('message_id', $data['data']);

            Log::info('메시지 작성 테스트 성공', [
                'message_id' => $data['data']['message_id'],
                'message' => $data['data']['message']
            ]);

        } catch (\Exception $e) {
            Log::error('메시지 작성 테스트 실패', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('메시지 작성 실패: ' . $e->getMessage());
        }

        Log::info('=== 메시지 작성 테스트 완료 ===');
    }

    private function performSseEventTest($user, $chatRoom)
    {
        Log::info('=== SSE 이벤트 테스트 시작 ===');

        Event::fake();

        // Given: 메시지 데이터
        $messageData = [
            'id' => 999,
            'room_id' => $chatRoom->id,
            'user_uuid' => $user->uuid,
            'message' => 'SSE 이벤트 테스트 메시지',
            'message_type' => 'text',
            'reply_to_id' => null,
            'is_system' => false,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString()
        ];

        $participants = [
            [
                'user_uuid' => $user->uuid,
                'name' => $user->name
            ]
        ];

        // When: MessageSent 이벤트 발생
        event(new MessageSent($chatRoom, $messageData, $participants));

        // Then: 이벤트가 정상적으로 발생했는지 확인
        Event::assertDispatched(MessageSent::class, function ($event) use ($messageData, $chatRoom) {
            $isCorrectRoom = $event->room->id === $chatRoom->id;
            $isCorrectMessage = $event->message['message'] === $messageData['message'];
            $isCorrectUser = $event->message['user_uuid'] === $messageData['user_uuid'];

            Log::info('SSE 이벤트 검증', [
                'room_match' => $isCorrectRoom,
                'message_match' => $isCorrectMessage,
                'user_match' => $isCorrectUser,
                'event_room_id' => $event->room->id,
                'expected_room_id' => $chatRoom->id,
                'event_message' => $event->message['message'],
                'expected_message' => $messageData['message']
            ]);

            return $isCorrectRoom && $isCorrectMessage && $isCorrectUser;
        });

        // SSE 형식 테스트
        $event = new MessageSent($chatRoom, $messageData, $participants);
        $sseFormat = $event->toSseFormat();

        $this->assertStringStartsWith('event: message.sent', $sseFormat);
        $this->assertStringContainsString('SSE 이벤트 테스트 메시지', $sseFormat);
        $this->assertStringEndsWith("\n\n", $sseFormat);

        Log::info('SSE 형식 테스트 완료', [
            'sse_format_length' => strlen($sseFormat),
            'contains_korean' => mb_check_encoding($sseFormat, 'UTF-8')
        ]);

        Log::info('=== SSE 이벤트 테스트 완료 ===');
    }

    private function performMessageListTest($user, $chatRoom)
    {
        Log::info('=== 메시지 목록 테스트 시작 ===');

        // 메시지 목록 컨트롤러 직접 테스트
        $messageListController = new \Jiny\Chat\Http\Controllers\Home\Room\MessageListController();

        $request = new \Illuminate\Http\Request();
        $request->merge(['limit' => 50]);

        $this->actingAs($user);

        try {
            $result = $messageListController->index($request, $chatRoom->id);
            $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $result);

            $data = $result->getData(true);
            $this->assertTrue($data['success'] ?? false);
            $this->assertArrayHasKey('data', $data);
            $this->assertArrayHasKey('messages', $data['data']);

            $messages = $data['data']['messages'];
            $this->assertIsArray($messages);

            Log::info('메시지 목록 테스트 완료', [
                'total_messages' => count($messages),
                'user_messages' => count(array_filter($messages, function($msg) {
                    return !$msg['is_system'];
                })),
                'system_messages' => count(array_filter($messages, function($msg) {
                    return $msg['is_system'];
                }))
            ]);

            // 메시지 내용 검증
            $userMessages = array_filter($messages, function($msg) {
                return !$msg['is_system'];
            });

            if (!empty($userMessages)) {
                $lastMessage = end($userMessages);
                $this->assertArrayHasKey('message', $lastMessage);
                $this->assertArrayHasKey('user_uuid', $lastMessage);
                $this->assertEquals($user->uuid, $lastMessage['user_uuid']);

                Log::info('마지막 사용자 메시지 확인', [
                    'message' => $lastMessage['message'],
                    'user_uuid' => $lastMessage['user_uuid'],
                    'created_at' => $lastMessage['created_at']
                ]);
            }

        } catch (\Exception $e) {
            Log::error('메시지 목록 테스트 실패', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('메시지 목록 조회 실패: ' . $e->getMessage());
        }

        Log::info('=== 메시지 목록 테스트 완료 ===');
    }

    /** @test */
    public function test_sqlite_database_integration()
    {
        Log::info('=== SQLite 데이터베이스 통합 테스트 시작 ===');

        $user = User::factory()->create(['uuid' => 'sqlite-test-user']);
        $chatRoom = ChatRoom::create([
            'title' => 'SQLite 테스트방',
            'type' => 'group',
            'code' => 'sqlite_test',
            'is_public' => true,
            'owner_uuid' => $user->uuid,
        ]);

        $this->createTestSqliteDatabase($chatRoom);

        $sqlitePath = $this->getSqlitePath($chatRoom);
        $this->assertFileExists($sqlitePath);

        // SQLite에 직접 메시지 삽입
        $pdo = new \PDO("sqlite:" . $sqlitePath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec("PRAGMA encoding = 'UTF-8'");

        $testMessage = 'SQLite 직접 삽입 테스트 메시지';
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (room_id, user_uuid, message, message_type, is_system, created_at, updated_at)
            VALUES (?, ?, ?, 'text', 0, datetime('now'), datetime('now'))
        ");
        $stmt->execute([$chatRoom->id, $user->uuid, $testMessage]);

        $messageId = $pdo->lastInsertId();
        $this->assertGreaterThan(0, $messageId);

        // 삽입된 메시지 조회
        $stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE id = ?");
        $stmt->execute([$messageId]);
        $savedMessage = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotNull($savedMessage);
        $this->assertEquals($testMessage, $savedMessage['message']);
        $this->assertEquals($user->uuid, $savedMessage['user_uuid']);

        Log::info('SQLite 직접 조작 테스트 완료', [
            'message_id' => $messageId,
            'message' => $savedMessage['message'],
            'user_uuid' => $savedMessage['user_uuid']
        ]);

        $this->cleanupTestDatabase($chatRoom);

        Log::info('=== SQLite 데이터베이스 통합 테스트 완료 ===');
    }

    /** @test */
    public function test_multiple_user_scenario()
    {
        Log::info('=== 다중 사용자 시나리오 테스트 시작 ===');

        // 사용자들 생성
        $users = [];
        for ($i = 1; $i <= 3; $i++) {
            $users[] = User::factory()->create([
                'name' => "테스트 사용자 {$i}",
                'uuid' => "multi-user-{$i}-uuid"
            ]);
        }

        $chatRoom = ChatRoom::create([
            'title' => '다중 사용자 테스트방',
            'type' => 'group',
            'code' => 'multi_user_test',
            'is_public' => true,
            'owner_uuid' => $users[0]->uuid,
        ]);

        // 모든 사용자를 참여자로 추가
        foreach ($users as $index => $user) {
            ChatParticipant::create([
                'room_id' => $chatRoom->id,
                'room_uuid' => $chatRoom->code,
                'user_uuid' => $user->uuid,
                'shard_id' => 1,
                'email' => $user->email,
                'name' => $user->name,
                'role' => $index === 0 ? 'owner' : 'member',
                'status' => 'active',
                'permissions' => json_encode(['send_message', 'read_message']),
                'can_send_message' => 1,
                'notifications_enabled' => 1,
                'last_read_at' => now(),
                'joined_at' => now(),
                'language' => 'ko'
            ]);
        }

        $this->createTestSqliteDatabase($chatRoom);

        Event::fake();

        // 각 사용자가 메시지 작성
        foreach ($users as $index => $user) {
            $messageData = [
                'id' => 1000 + $index,
                'room_id' => $chatRoom->id,
                'user_uuid' => $user->uuid,
                'message' => "사용자 {$user->name}의 메시지",
                'message_type' => 'text',
                'is_system' => false,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString()
            ];

            $participants = array_map(function($u) {
                return ['user_uuid' => $u->uuid, 'name' => $u->name];
            }, $users);

            // SSE 이벤트 발생
            event(new MessageSent($chatRoom, $messageData, $participants));

            Log::info("사용자 {$index}의 메시지 이벤트 발생", [
                'user' => $user->name,
                'message' => $messageData['message']
            ]);
        }

        // 이벤트가 정확히 3번 발생했는지 확인
        Event::assertDispatchedTimes(MessageSent::class, 3);

        $this->cleanupTestDatabase($chatRoom);

        Log::info('=== 다중 사용자 시나리오 테스트 완료 ===');
    }

    private function createTestSqliteDatabase($chatRoom)
    {
        $year = date('Y');
        $month = date('m');
        $day = date('d');

        $chatDbDir = database_path("chat/{$year}/{$month}/{$day}");
        $chatDbPath = $chatDbDir . "/room-{$chatRoom->id}.sqlite";

        if (!is_dir($chatDbDir)) {
            mkdir($chatDbDir, 0755, true);
        }

        $pdo = new \PDO("sqlite:" . $chatDbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec("PRAGMA encoding = 'UTF-8'");
        $pdo->exec("PRAGMA journal_mode = WAL");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS chat_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id INTEGER NOT NULL,
                user_uuid VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                message_type VARCHAR(50) DEFAULT 'text',
                reply_to_id INTEGER NULL,
                is_system BOOLEAN DEFAULT FALSE,
                is_deleted BOOLEAN DEFAULT FALSE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (room_id, user_uuid, message, message_type, is_system, created_at, updated_at)
            VALUES (?, 'system', ?, 'system', 1, datetime('now'), datetime('now'))
        ");
        $stmt->execute([$chatRoom->id, "채팅방 '{$chatRoom->title}'이 생성되었습니다."]);

        $pdo = null;
    }

    private function getSqlitePath($chatRoom)
    {
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        return database_path("chat/{$year}/{$month}/{$day}/room-{$chatRoom->id}.sqlite");
    }

    private function cleanupTestDatabase($chatRoom)
    {
        $sqlitePath = $this->getSqlitePath($chatRoom);
        if (file_exists($sqlitePath)) {
            unlink($sqlitePath);
        }
    }
}