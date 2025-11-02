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

class RealTimeChatUnitTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $chatRoom;

    protected function setUp(): void
    {
        parent::setUp();

        // 테스트용 사용자 생성
        $this->user = User::factory()->create([
            'name' => '테스트 사용자',
            'email' => 'test@example.com',
            'uuid' => 'test-user-uuid'
        ]);

        // 테스트용 채팅방 생성
        $this->chatRoom = ChatRoom::create([
            'title' => '테스트 채팅방',
            'slug' => 'test-chat-room',
            'description' => 'SSE 기능 테스트용 채팅방',
            'type' => 'group',
            'code' => 'test_room_001',
            'is_public' => true,
            'allow_join' => true,
            'allow_invite' => true,
            'max_participants' => 10,
            'owner_uuid' => $this->user->uuid,
            'created_at' => now(),
            'updated_at' => now()
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
    public function message_sent_event_can_be_created_and_fired()
    {
        Event::fake();

        // Given: 메시지 데이터
        $messageData = [
            'id' => 1,
            'room_id' => $this->chatRoom->id,
            'user_uuid' => $this->user->uuid,
            'message' => '테스트 메시지입니다.',
            'message_type' => 'text',
            'reply_to_id' => null,
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

        // When: MessageSent 이벤트 발생
        event(new MessageSent($this->chatRoom, $messageData, $participants));

        // Then: 이벤트가 정상적으로 발생했는지 확인
        Event::assertDispatched(MessageSent::class, function ($event) use ($messageData) {
            return $event->room->id === $this->chatRoom->id
                && $event->message['message'] === $messageData['message']
                && $event->message['user_uuid'] === $this->user->uuid;
        });
    }

    /** @test */
    public function user_typing_event_can_be_created_and_fired()
    {
        Event::fake();

        // When: UserTyping 이벤트 발생
        event(new UserTyping(
            $this->chatRoom->id,
            $this->user->uuid,
            $this->user->name,
            'start'
        ));

        // Then: 이벤트가 정상적으로 발생했는지 확인
        Event::assertDispatched(UserTyping::class, function ($event) {
            return $event->roomId === $this->chatRoom->id
                && $event->userUuid === $this->user->uuid
                && $event->action === 'start';
        });
    }

    /** @test */
    public function message_sent_event_produces_correct_sse_format()
    {
        // Given: MessageSent 이벤트
        $messageData = [
            'id' => 1,
            'room_id' => $this->chatRoom->id,
            'user_uuid' => $this->user->uuid,
            'message' => '한글 테스트 메시지입니다.',
            'message_type' => 'text',
            'reply_to_id' => null,
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

        $event = new MessageSent($this->chatRoom, $messageData, $participants);

        // When: SSE 형식으로 변환
        $sseFormat = $event->toSseFormat();

        // Then: 올바른 SSE 형식인지 확인
        $this->assertStringStartsWith('event: message.sent', $sseFormat);
        $this->assertStringContainsString('data: {', $sseFormat);
        $this->assertStringContainsString('한글 테스트 메시지입니다.', $sseFormat);
        $this->assertStringEndsWith("\n\n", $sseFormat);

        // JSON 파싱이 가능한지 확인
        $lines = explode("\n", $sseFormat);
        $dataLine = '';
        foreach ($lines as $line) {
            if (strpos($line, 'data: ') === 0) {
                $dataLine = substr($line, 6); // 'data: ' 제거
                break;
            }
        }

        $this->assertNotEmpty($dataLine);
        $decodedData = json_decode($dataLine, true);
        $this->assertIsArray($decodedData);
        $this->assertEquals('message', $decodedData['type']);
        $this->assertEquals($this->chatRoom->id, $decodedData['room_id']);
    }

    /** @test */
    public function user_typing_event_produces_correct_sse_format()
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
        $this->assertStringContainsString('data: {', $sseFormat);
        $this->assertStringContainsString($this->user->name, $sseFormat);
        $this->assertStringEndsWith("\n\n", $sseFormat);

        // JSON 파싱이 가능한지 확인
        $lines = explode("\n", $sseFormat);
        $dataLine = '';
        foreach ($lines as $line) {
            if (strpos($line, 'data: ') === 0) {
                $dataLine = substr($line, 6); // 'data: ' 제거
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

    /** @test */
    public function events_handle_korean_characters_correctly()
    {
        // Given: 한글 메시지 데이터
        $koreanMessage = '안녕하세요! 실시간 채팅 기능을 테스트합니다. 😊';
        $messageData = [
            'id' => 1,
            'room_id' => $this->chatRoom->id,
            'user_uuid' => $this->user->uuid,
            'message' => $koreanMessage,
            'message_type' => 'text',
            'reply_to_id' => null,
            'is_system' => false,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString()
        ];

        $participants = [
            [
                'user_uuid' => $this->user->uuid,
                'name' => '한글 사용자'
            ]
        ];

        $event = new MessageSent($this->chatRoom, $messageData, $participants);

        // When: SSE 형식으로 변환
        $sseFormat = $event->toSseFormat();

        // Then: 한글이 올바르게 처리되는지 확인
        $this->assertStringContainsString($koreanMessage, $sseFormat);
        $this->assertStringContainsString('한글 사용자', $sseFormat);

        // JSON 유니코드 처리 확인
        $lines = explode("\n", $sseFormat);
        $dataLine = '';
        foreach ($lines as $line) {
            if (strpos($line, 'data: ') === 0) {
                $dataLine = substr($line, 6);
                break;
            }
        }

        $decodedData = json_decode($dataLine, true);
        $this->assertEquals($koreanMessage, $decodedData['message']['message']);
        $this->assertEquals('한글 사용자', $decodedData['message']['user_name']);
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

        $pdo = null;
    }

    protected function tearDown(): void
    {
        // 테스트 후 SQLite 파일 정리
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        $chatDbPath = database_path("chat/{$year}/{$month}/{$day}/room-{$this->chatRoom->id}.sqlite");

        if (file_exists($chatDbPath)) {
            unlink($chatDbPath);
        }

        parent::tearDown();
    }
}