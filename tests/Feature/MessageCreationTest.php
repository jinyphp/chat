<?php

namespace Jiny\Chat\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Events\MessageSent;
use App\Models\User;

class MessageCreationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $chatRoom;
    protected $participant;

    protected function setUp(): void
    {
        parent::setUp();

        // JWT 인증 미들웨어 비활성화 (테스트 환경)
        $this->withoutMiddleware('jwt.auth');

        // 테스트용 사용자 생성
        $this->user = User::factory()->create([
            'name' => '테스트 사용자',
            'email' => 'test@example.com',
            'uuid' => 'test-user-uuid'
        ]);

        // 테스트용 채팅방 생성
        $this->chatRoom = ChatRoom::create([
            'title' => '메시지 작성 테스트방',
            'slug' => 'message-creation-test',
            'description' => '메시지 작성 기능 테스트용 채팅방',
            'type' => 'group',
            'code' => 'test_room_msg',
            'is_public' => true,
            'allow_join' => true,
            'allow_invite' => true,
            'max_participants' => 10,
            'owner_uuid' => $this->user->uuid,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // 참여자 추가
        $this->participant = ChatParticipant::create([
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

        // SQLite 데이터베이스 파일 생성
        $this->createTestSqliteDatabase();
    }

    /** @test */
    public function user_can_create_a_message_in_chat_room()
    {
        // Given: 인증된 사용자와 메시지 데이터
        $this->actingAs($this->user);

        $messageData = [
            'message' => '안녕하세요! 첫 번째 테스트 메시지입니다.',
            'message_type' => 'text'
        ];

        // When: 메시지 작성 API 호출
        $response = $this->postJson("/home/chat/room/{$this->chatRoom->id}/messages", $messageData);

        // Then: 메시지가 성공적으로 생성되어야 함
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => '메시지가 전송되었습니다.'
        ]);

        $responseData = $response->json();
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('message_id', $responseData['data']);
        $this->assertEquals($messageData['message'], $responseData['data']['message']);
    }

    /** @test */
    public function message_is_saved_to_sqlite_database()
    {
        // Given: 인증된 사용자와 메시지 데이터
        $this->actingAs($this->user);

        $messageData = [
            'message' => '데이터베이스 저장 테스트 메시지',
            'message_type' => 'text'
        ];

        // When: 메시지 작성
        $response = $this->postJson("/home/chat/room/{$this->chatRoom->id}/messages", $messageData);

        // Then: SQLite 데이터베이스에 메시지가 저장되어야 함
        $response->assertStatus(200);

        $sqlitePath = $this->getSqlitePath();
        $this->assertFileExists($sqlitePath);

        $pdo = new \PDO("sqlite:" . $sqlitePath);
        $stmt = $pdo->query("SELECT * FROM chat_messages WHERE room_id = {$this->chatRoom->id} ORDER BY id DESC LIMIT 1");
        $message = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotNull($message);
        $this->assertEquals($messageData['message'], $message['message']);
        $this->assertEquals($this->user->uuid, $message['user_uuid']);
        $this->assertEquals($messageData['message_type'], $message['message_type']);
    }

    /** @test */
    public function message_creation_fires_sse_event()
    {
        Event::fake();

        // Given: 인증된 사용자와 메시지 데이터
        $this->actingAs($this->user);

        $messageData = [
            'message' => 'SSE 이벤트 테스트 메시지',
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
    public function message_list_returns_created_messages()
    {
        // Given: 인증된 사용자와 생성된 메시지들
        $this->actingAs($this->user);

        $messages = [
            '첫 번째 메시지입니다.',
            '두 번째 메시지입니다.',
            '세 번째 한글 메시지입니다. 😊'
        ];

        // When: 여러 메시지 생성
        foreach ($messages as $messageText) {
            $this->postJson("/home/chat/room/{$this->chatRoom->id}/messages", [
                'message' => $messageText,
                'message_type' => 'text'
            ])->assertStatus(200);
        }

        // Then: 메시지 목록 API에서 모든 메시지가 반환되어야 함
        $response = $this->getJson("/home/chat/room/{$this->chatRoom->id}/messages/list");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $responseData = $response->json();
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('messages', $responseData['data']);

        $returnedMessages = $responseData['data']['messages'];
        $this->assertCount(count($messages) + 1, $returnedMessages); // +1 for system message

        // 시스템 메시지 제외하고 사용자 메시지만 확인
        $userMessages = array_filter($returnedMessages, function ($msg) {
            return !$msg['is_system'];
        });

        $this->assertCount(count($messages), $userMessages);

        // 메시지 내용 확인
        $messageTexts = array_column($userMessages, 'message');
        foreach ($messages as $message) {
            $this->assertContains($message, $messageTexts);
        }
    }

    /** @test */
    public function message_creation_handles_korean_characters_correctly()
    {
        // Given: 인증된 사용자와 한글 메시지
        $this->actingAs($this->user);

        $koreanMessage = '안녕하세요! 한글 메시지 테스트입니다. 이모지도 포함: 🚀🌟💫';
        $messageData = [
            'message' => $koreanMessage,
            'message_type' => 'text'
        ];

        // When: 한글 메시지 작성
        $response = $this->postJson("/home/chat/room/{$this->chatRoom->id}/messages", $messageData);

        // Then: 메시지가 성공적으로 생성되고 한글이 올바르게 저장되어야 함
        $response->assertStatus(200);

        // SQLite에서 직접 확인
        $sqlitePath = $this->getSqlitePath();
        $pdo = new \PDO("sqlite:" . $sqlitePath);
        $pdo->exec("PRAGMA encoding = 'UTF-8'");

        $stmt = $pdo->prepare("SELECT message FROM chat_messages WHERE room_id = ? AND user_uuid = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$this->chatRoom->id, $this->user->uuid]);
        $savedMessage = $stmt->fetchColumn();

        $this->assertEquals($koreanMessage, $savedMessage);

        // API로도 확인
        $listResponse = $this->getJson("/home/chat/room/{$this->chatRoom->id}/messages/list");
        $listResponse->assertStatus(200);

        $responseData = $listResponse->json();
        $messages = $responseData['data']['messages'];

        $userMessages = array_filter($messages, function ($msg) {
            return !$msg['is_system'] && $msg['user_uuid'] === $this->user->uuid;
        });

        $lastMessage = end($userMessages);
        $this->assertEquals($koreanMessage, $lastMessage['message']);
    }

    /** @test */
    public function unauthorized_user_cannot_create_message()
    {
        // Given: 채팅방에 참여하지 않은 사용자
        $unauthorizedUser = User::factory()->create([
            'uuid' => 'unauthorized-user-uuid'
        ]);

        $this->actingAs($unauthorizedUser);

        $messageData = [
            'message' => '권한 없는 사용자의 메시지',
            'message_type' => 'text'
        ];

        // When: 메시지 작성 시도
        $response = $this->postJson("/home/chat/room/{$this->chatRoom->id}/messages", $messageData);

        // Then: 권한 오류가 발생해야 함
        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => '이 채팅방에 참여하지 않았습니다.'
        ]);
    }

    /** @test */
    public function user_with_no_send_permission_cannot_create_message()
    {
        // Given: 메시지 전송 권한이 없는 사용자
        $restrictedUser = User::factory()->create([
            'uuid' => 'restricted-user-uuid'
        ]);

        ChatParticipant::create([
            'room_id' => $this->chatRoom->id,
            'room_uuid' => $this->chatRoom->code,
            'user_uuid' => $restrictedUser->uuid,
            'shard_id' => 1,
            'email' => $restrictedUser->email,
            'name' => $restrictedUser->name,
            'role' => 'member',
            'status' => 'active',
            'permissions' => json_encode(['read_message']),
            'can_send_message' => 0, // 메시지 전송 권한 없음
            'can_invite' => 0,
            'can_moderate' => 0,
            'notifications_enabled' => 1,
            'notification_settings' => json_encode(['mentions' => true]),
            'last_read_at' => now(),
            'last_read_message_id' => 0,
            'unread_count' => 0,
            'joined_at' => now(),
            'last_seen_at' => now(),
            'language' => 'ko'
        ]);

        $this->actingAs($restrictedUser);

        $messageData = [
            'message' => '권한 제한된 사용자의 메시지',
            'message_type' => 'text'
        ];

        // When: 메시지 작성 시도
        $response = $this->postJson("/home/chat/room/{$this->chatRoom->id}/messages", $messageData);

        // Then: 권한 오류가 발생해야 함
        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => '메시지 전송 권한이 없습니다.'
        ]);
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

        // UTF-8 인코딩 설정
        $pdo->exec("PRAGMA encoding = 'UTF-8'");
        $pdo->exec("PRAGMA journal_mode = WAL");

        // 채팅 메시지 테이블 생성
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
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (reply_to_id) REFERENCES chat_messages(id)
            )
        ");

        // 시스템 메시지 추가
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (room_id, user_uuid, message, message_type, is_system, created_at, updated_at)
            VALUES (?, 'system', ?, 'system', 1, datetime('now'), datetime('now'))
        ");
        $stmt->execute([
            $this->chatRoom->id,
            "채팅방 '{$this->chatRoom->title}'이 생성되었습니다. 대화를 시작해보세요!"
        ]);

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
        // 테스트 후 SQLite 파일 정리
        $sqlitePath = $this->getSqlitePath();
        if (file_exists($sqlitePath)) {
            unlink($sqlitePath);
        }

        parent::tearDown();
    }
}