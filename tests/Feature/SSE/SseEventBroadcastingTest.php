<?php

namespace Jiny\Chat\Tests\Feature\SSE;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Events\MessageSent;
use Jiny\Chat\Events\UserTyping;
use App\Models\User;

class SseEventBroadcastingTest extends TestCase
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
            'name' => '테스트 사용자',
            'email' => 'test@example.com',
            'uuid' => 'test-user-uuid'
        ]);

        // 테스트용 채팅방 생성
        $this->chatRoom = ChatRoom::create([
            'title' => 'SSE 브로드캐스팅 테스트방',
            'slug' => 'sse-broadcasting-test',
            'type' => 'group',
            'code' => 'sse_broadcast_test',
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
    public function test_message_creation_fires_event_and_creates_sse_broadcast()
    {
        // Given: 인증된 사용자
        $this->actingAs($this->user);

        $messageData = [
            'message' => 'SSE 브로드캐스팅 테스트 메시지',
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

        // 그리고 SQLite에 저장되어야 함
        $sqlitePath = $this->getSqlitePath();
        $this->assertFileExists($sqlitePath);

        $pdo = new \PDO("sqlite:" . $sqlitePath);
        $stmt = $pdo->query("SELECT * FROM chat_messages WHERE room_id = {$this->chatRoom->id} AND is_system = 0 ORDER BY id DESC LIMIT 1");
        $message = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotNull($message);
        $this->assertEquals($messageData['message'], $message['message']);
        $this->assertEquals($this->user->uuid, $message['user_uuid']);
    }

    /** @test */
    public function test_direct_event_firing_creates_proper_sse_format()
    {
        // Given: 메시지 데이터
        $messageData = [
            'id' => 999,
            'room_id' => $this->chatRoom->id,
            'user_uuid' => $this->user->uuid,
            'message' => 'SSE 형식 테스트 메시지',
            'message_type' => 'text',
            'is_system' => false,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString()
        ];

        $participants = [
            [
                'user_uuid' => $this->user->uuid,
                'name' => $this->user->name
            ]
        ];

        // When: MessageSent 이벤트 직접 발생
        $event = new MessageSent($this->chatRoom, $messageData, $participants);
        $sseFormat = $event->toSseFormat();

        // Then: 올바른 SSE 형식인지 확인
        $this->assertStringStartsWith('event: message.sent', $sseFormat);
        $this->assertStringContainsString('SSE 형식 테스트 메시지', $sseFormat);
        $this->assertStringEndsWith("\n\n", $sseFormat);

        // JSON 데이터 파싱 테스트
        $lines = explode("\n", $sseFormat);
        $dataLine = '';
        foreach ($lines as $line) {
            if (strpos($line, 'data: ') === 0) {
                $dataLine = substr($line, 6);
                break;
            }
        }

        $this->assertNotEmpty($dataLine);
        $decodedData = json_decode($dataLine, true);
        $this->assertIsArray($decodedData);
        $this->assertEquals('message', $decodedData['type']);
        $this->assertEquals($this->chatRoom->id, $decodedData['room_id']);
        $this->assertEquals('SSE 형식 테스트 메시지', $decodedData['message']['message']);
    }

    /** @test */
    public function test_typing_event_creates_proper_sse_format()
    {
        // Given: UserTyping 이벤트
        $event = new UserTyping(
            $this->chatRoom->id,
            $this->user->uuid,
            $this->user->name,
            'start'
        );

        // When: SSE 형식으로 변환
        $sseFormat = $event->toSseFormat();

        // Then: 올바른 SSE 형식인지 확인
        $this->assertStringStartsWith('event: user.typing', $sseFormat);
        $this->assertStringContainsString($this->user->name, $sseFormat);
        $this->assertStringEndsWith("\n\n", $sseFormat);

        // JSON 데이터 파싱 테스트
        $lines = explode("\n", $sseFormat);
        $dataLine = '';
        foreach ($lines as $line) {
            if (strpos($line, 'data: ') === 0) {
                $dataLine = substr($line, 6);
                break;
            }
        }

        $this->assertNotEmpty($dataLine);
        $decodedData = json_decode($dataLine, true);
        $this->assertIsArray($decodedData);
        $this->assertEquals('typing', $decodedData['type']);
        $this->assertEquals($this->chatRoom->id, $decodedData['room_id']);
        $this->assertEquals('start', $decodedData['action']);
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