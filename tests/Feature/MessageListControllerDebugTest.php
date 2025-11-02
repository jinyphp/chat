<?php

namespace Jiny\Chat\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;
use App\Models\User;

class MessageListControllerDebugTest extends TestCase
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
            'name' => '디버그 테스트 사용자',
            'email' => 'debug@example.com',
            'uuid' => 'debug-test-user-uuid'
        ]);

        // 테스트용 채팅방 생성
        $this->chatRoom = ChatRoom::create([
            'title' => 'MessageList 디버그 테스트방',
            'slug' => 'messagelist-debug-test',
            'type' => 'group',
            'code' => 'debug_test',
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
    public function test_message_list_api_returns_without_count_error()
    {
        // Given: 인증된 사용자와 채팅방
        $this->actingAs($this->user);

        // When: 메시지 목록 API 호출
        $response = $this->getJson("/home/chat/room/{$this->chatRoom->id}/messages/list");

        // Then: count() 오류 없이 성공적으로 응답해야 함
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'messages',
                'participants',
                'room',
                'current_user'
            ]
        ]);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['data']['messages']);

        // messages는 배열이어야 하고, count() 함수로 개수를 셀 수 있어야 함
        $messages = $data['data']['messages'];
        $this->assertIsArray($messages);
        $this->assertIsInt(count($messages));
    }

    /** @test */
    public function test_messagelist_controller_processes_messages_correctly()
    {
        // Given: SQLite에 메시지 추가
        $this->addTestMessagesToSqlite();
        $this->actingAs($this->user);

        // When: 메시지 목록 API 호출
        $response = $this->getJson("/home/chat/room/{$this->chatRoom->id}/messages/list");

        // Then: 메시지가 배열 형태로 반환되어야 함
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['success']);
        $messages = $data['data']['messages'];

        // 메시지가 배열인지 확인
        $this->assertIsArray($messages);

        // 각 메시지가 올바른 구조를 가지는지 확인
        foreach ($messages as $message) {
            $this->assertIsArray($message);
            $this->assertArrayHasKey('id', $message);
            $this->assertArrayHasKey('message', $message);
            $this->assertArrayHasKey('user_uuid', $message);
            $this->assertArrayHasKey('room_id', $message);
        }

        // count() 함수가 정상 작동하는지 확인
        $messageCount = count($messages);
        $this->assertIsInt($messageCount);
        $this->assertGreaterThan(0, $messageCount);
    }

    /** @test */
    public function test_debug_message_type_in_controller()
    {
        // Given: SQLite에 다양한 메시지 추가
        $this->addTestMessagesToSqlite();
        $this->actingAs($this->user);

        // When: 메시지 목록 API 호출하여 로그 확인
        $response = $this->getJson("/home/chat/room/{$this->chatRoom->id}/messages/list");

        // Then: 응답이 성공하고 메시지가 배열이어야 함
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['success']);
        $messages = $data['data']['messages'];

        // 메시지는 반드시 배열이어야 함
        $this->assertIsArray($messages);

        // Collection이 아닌 순수 배열인지 확인
        $this->assertNotInstanceOf(\Illuminate\Support\Collection::class, $messages);

        // count() 함수 호출 테스트
        $messageCount = count($messages);
        $this->assertIsNumeric($messageCount);
    }

    private function addTestMessagesToSqlite()
    {
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        $sqlitePath = database_path("chat/{$year}/{$month}/{$day}/room-{$this->chatRoom->id}.sqlite");

        $pdo = new \PDO("sqlite:" . $sqlitePath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // 테스트 메시지 추가
        $messages = [
            'MessageListController 디버그 테스트 메시지 1',
            'MessageListController 디버그 테스트 메시지 2',
            'count() 오류 재현 테스트 메시지'
        ];

        foreach ($messages as $index => $message) {
            $stmt = $pdo->prepare("
                INSERT INTO chat_messages (room_id, user_uuid, message, message_type, is_system, created_at, updated_at)
                VALUES (?, ?, ?, 'text', 0, datetime('now'), datetime('now'))
            ");
            $stmt->execute([$this->chatRoom->id, $this->user->uuid, $message]);
        }

        $pdo = null;
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

    protected function tearDown(): void
    {
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        $sqlitePath = database_path("chat/{$year}/{$month}/{$day}/room-{$this->chatRoom->id}.sqlite");

        if (file_exists($sqlitePath)) {
            unlink($sqlitePath);
        }

        parent::tearDown();
    }
}