<?php

namespace Jiny\Chat\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Events\MessageSent;
use App\Models\User;

class MessageCreationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $chatRoom;

    protected function setUp(): void
    {
        parent::setUp();

        // JWT 인증 미들웨어 비활성화
        $this->withoutMiddleware('jwt.auth');

        // 테스트용 사용자 생성
        $this->user = User::factory()->create([
            'name' => '메시지 작성 테스트 사용자',
            'email' => 'message-test@example.com',
            'uuid' => 'message-test-user-uuid'
        ]);

        // 테스트용 채팅방 생성
        $this->chatRoom = ChatRoom::create([
            'title' => '메시지 작성 플로우 테스트방',
            'slug' => 'message-creation-flow-test',
            'type' => 'group',
            'code' => 'message_flow_test',
            'is_public' => true,
            'allow_join' => true,
            'owner_uuid' => $this->user->uuid,
        ]);

        // 참여자 추가
        ChatParticipant::create([
            'room_id' => $this->chatRoom->id,
            'room_uuid' => $this->chatRoom->code,
            'user_uuid' => $this->user->uuid,
            'shard_id' => 1,
            'email' => $this->user->email,
            'name' => $this->user->name,
            'role' => 'owner',
            'status' => 'active',
            'permissions' => json_encode(['send_message', 'read_message']),
            'can_send_message' => 1,
            'notifications_enabled' => 1,
            'last_read_at' => now(),
            'joined_at' => now(),
            'language' => 'ko'
        ]);

        $this->createTestSqliteDatabase();
    }

    /** @test */
    public function test_complete_message_creation_flow()
    {
        // Given: 인증된 사용자와 채팅방
        $this->actingAs($this->user);

        $messageData = [
            'message' => '메시지 작성 플로우 테스트',
            'message_type' => 'text'
        ];

        // When: 메시지 작성 API 호출
        $response = $this->postJson("/home/chat/room/{$this->chatRoom->id}/messages", $messageData);

        // Then: 성공적인 응답
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => '메시지가 전송되었습니다.'
        ]);

        $responseData = $response->json();
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('message_id', $responseData['data']);
        $this->assertEquals($messageData['message'], $responseData['data']['message']);

        // SQLite에 실제로 저장되었는지 확인
        $this->assertMessageExistsInSqlite($messageData['message']);
    }

    /** @test */
    public function test_message_creation_triggers_sse_event()
    {
        Event::fake();
        $this->actingAs($this->user);

        $messageData = [
            'message' => 'SSE 이벤트 트리거 테스트',
            'message_type' => 'text'
        ];

        // When: 메시지 작성
        $response = $this->postJson("/home/chat/room/{$this->chatRoom->id}/messages", $messageData);

        // Then: MessageSent 이벤트가 발생해야 함
        $response->assertStatus(200);

        Event::assertDispatched(MessageSent::class, function ($event) use ($messageData) {
            return $event->room->id === $this->chatRoom->id
                && $event->message['message'] === $messageData['message']
                && $event->message['user_uuid'] === $this->user->uuid;
        });
    }

    /** @test */
    public function test_message_creation_updates_room_activity()
    {
        $this->actingAs($this->user);

        // Given: 방의 초기 상태 확인
        $initialRoom = $this->chatRoom->fresh();
        $initialMessageCount = $initialRoom->message_count ?? 0;

        $messageData = [
            'message' => '방 활동 업데이트 테스트',
            'message_type' => 'text'
        ];

        // When: 메시지 작성
        $response = $this->postJson("/home/chat/room/{$this->chatRoom->id}/messages", $messageData);

        // Then: 방의 활동 정보가 업데이트되어야 함
        $response->assertStatus(200);

        $updatedRoom = $this->chatRoom->fresh();
        $this->assertNotNull($updatedRoom->last_activity_at);
        $this->assertNotNull($updatedRoom->last_message_at);
        $this->assertEquals($initialMessageCount + 1, $updatedRoom->message_count);
    }

    /** @test */
    public function test_message_creation_updates_participant_read_status()
    {
        $this->actingAs($this->user);

        $participant = ChatParticipant::where('room_id', $this->chatRoom->id)
            ->where('user_uuid', $this->user->uuid)
            ->first();

        $initialReadAt = $participant->last_read_at;

        $messageData = [
            'message' => '참여자 읽음 상태 업데이트 테스트',
            'message_type' => 'text'
        ];

        // When: 메시지 작성
        $response = $this->postJson("/home/chat/room/{$this->chatRoom->id}/messages", $messageData);

        // Then: 참여자의 읽음 상태가 업데이트되어야 함
        $response->assertStatus(200);

        $updatedParticipant = $participant->fresh();
        $this->assertNotEquals($initialReadAt, $updatedParticipant->last_read_at);
        $this->assertNotNull($updatedParticipant->last_read_message_id);
    }

    /** @test */
    public function test_message_creation_requires_authentication()
    {
        // Given: 인증되지 않은 상태
        $messageData = [
            'message' => '인증 필요 테스트',
            'message_type' => 'text'
        ];

        // When: 메시지 작성 시도
        $response = $this->postJson("/home/chat/room/{$this->chatRoom->id}/messages", $messageData);

        // Then: 401 에러 반환
        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => '로그인이 필요합니다.'
        ]);
    }

    /** @test */
    public function test_message_creation_requires_room_participation()
    {
        // Given: 다른 사용자 생성 (참여하지 않은)
        $otherUser = User::factory()->create([
            'uuid' => 'non-participant-user'
        ]);

        $this->actingAs($otherUser);

        $messageData = [
            'message' => '참여 권한 확인 테스트',
            'message_type' => 'text'
        ];

        // When: 메시지 작성 시도
        $response = $this->postJson("/home/chat/room/{$this->chatRoom->id}/messages", $messageData);

        // Then: 403 에러 반환
        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => '이 채팅방에 참여하지 않았습니다.'
        ]);
    }

    /** @test */
    public function test_message_creation_validates_input()
    {
        $this->actingAs($this->user);

        // When: 빈 메시지로 시도
        $response = $this->postJson("/home/chat/room/{$this->chatRoom->id}/messages", [
            'message' => '',
            'message_type' => 'text'
        ]);

        // Then: 유효성 검사 실패
        $response->assertStatus(422);
    }

    /** @test */
    public function test_sqlite_database_interaction()
    {
        $this->actingAs($this->user);

        $messageData = [
            'message' => 'SQLite 상호작용 테스트 메시지',
            'message_type' => 'text'
        ];

        // When: 메시지 작성
        $response = $this->postJson("/home/chat/room/{$this->chatRoom->id}/messages", $messageData);

        // Then: SQLite에 올바르게 저장되었는지 확인
        $response->assertStatus(200);

        $responseData = $response->json();
        $messageId = $responseData['data']['message_id'];

        // SQLite에서 직접 확인
        $sqlitePath = $this->getSqlitePath();
        $this->assertFileExists($sqlitePath);

        $pdo = new \PDO("sqlite:" . $sqlitePath);
        $stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE id = ? AND room_id = ?");
        $stmt->execute([$messageId, $this->chatRoom->id]);
        $savedMessage = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotNull($savedMessage);
        $this->assertEquals($messageData['message'], $savedMessage['message']);
        $this->assertEquals($this->user->uuid, $savedMessage['user_uuid']);
        $this->assertEquals($messageData['message_type'], $savedMessage['message_type']);
        $this->assertEquals(0, $savedMessage['is_system']);
        $this->assertEquals(0, $savedMessage['is_deleted']);
    }

    private function assertMessageExistsInSqlite($messageText)
    {
        $sqlitePath = $this->getSqlitePath();
        $this->assertFileExists($sqlitePath);

        $pdo = new \PDO("sqlite:" . $sqlitePath);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE message = ? AND room_id = ? AND is_deleted = 0");
        $stmt->execute([$messageText, $this->chatRoom->id]);
        $count = $stmt->fetchColumn();

        $this->assertGreaterThan(0, $count, "메시지가 SQLite에 저장되지 않았습니다: {$messageText}");
    }

    protected function createTestSqliteDatabase()
    {
        $year = date('Y');
        $month = date('m');
        $day = date('d');

        $chatDbDir = database_path("chat/{$year}/{$month}/{$day}");
        $chatDbPath = $chatDbDir . "/room-{$this->chatRoom->id}.sqlite";

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

        // 시스템 메시지 추가
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (room_id, user_uuid, message, message_type, is_system, created_at, updated_at)
            VALUES (?, 'system', ?, 'system', 1, datetime('now'), datetime('now'))
        ");
        $stmt->execute([$this->chatRoom->id, "채팅방 '{$this->chatRoom->title}'이 생성되었습니다."]);

        $pdo = null;
    }

    protected function getSqlitePath()
    {
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        return database_path("chat/{$year}/{$month}/{$day}/room-{$this->chatRoom->id}.sqlite");
    }

    protected function tearDown(): void
    {
        $sqlitePath = $this->getSqlitePath();
        if (file_exists($sqlitePath)) {
            unlink($sqlitePath);
        }

        parent::tearDown();
    }
}