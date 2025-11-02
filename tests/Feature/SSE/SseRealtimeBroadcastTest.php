<?php

namespace Jiny\Chat\Tests\Feature\SSE;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Events\MessageSent;
use Jiny\Chat\Events\UserTyping;
use App\Models\User;

class SseRealtimeBroadcastTest extends TestCase
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
            'title' => 'SSE 실시간 브로드캐스트 테스트방',
            'slug' => 'sse-realtime-broadcast-test',
            'type' => 'group',
            'code' => 'sse_realtime_test',
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

    #[Test]
    public function test_sse_controller_can_register_event_listeners()
    {
        // Given: SSE 컨트롤러 인스턴스
        $controller = new \Jiny\Chat\Http\Controllers\Home\Room\SseController();
        $this->actingAs($this->user);

        // When: SSE 스트림 요청을 시뮬레이션
        $request = new \Illuminate\Http\Request();

        // Then: SSE 컨트롤러가 정상적으로 스트리밍 응답을 생성하는지 확인
        $response = $controller->stream($request, $this->chatRoom->id);

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
        $this->assertEquals('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
    }

    #[Test]
    public function test_message_sent_event_is_captured_and_formatted_correctly()
    {
        // Given: 메시지 데이터
        $messageData = [
            'id' => 999,
            'room_id' => $this->chatRoom->id,
            'user_uuid' => $this->user->uuid,
            'message' => 'SSE 실시간 브로드캐스트 테스트 메시지',
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

        // When: MessageSent 이벤트 생성
        $event = new MessageSent($this->chatRoom, $messageData, $participants);

        // Then: 이벤트가 올바른 데이터를 포함하는지 확인
        $this->assertEquals($this->chatRoom->id, $event->room->id);
        $this->assertEquals('SSE 실시간 브로드캐스트 테스트 메시지', $event->message['message']);
        $this->assertEquals($this->user->uuid, $event->message['user_uuid']);

        // 그리고 SSE 형식으로 올바르게 변환되는지 확인
        $sseFormat = $event->toSseFormat();
        $this->assertStringStartsWith('event: message.sent', $sseFormat);
        $this->assertStringContainsString('SSE 실시간 브로드캐스트 테스트 메시지', $sseFormat);
        $this->assertStringEndsWith("\n\n", $sseFormat);

        // JSON 데이터 검증
        $lines = explode("\n", $sseFormat);
        $dataLine = '';
        foreach ($lines as $line) {
            if (strpos($line, 'data: ') === 0) {
                $dataLine = substr($line, 6);
                break;
            }
        }

        $decodedData = json_decode($dataLine, true);
        $this->assertIsArray($decodedData);
        $this->assertEquals('message', $decodedData['type']);
        $this->assertEquals($this->chatRoom->id, $decodedData['room_id']);
        $this->assertEquals('SSE 실시간 브로드캐스트 테스트 메시지', $decodedData['message']['message']);
    }

    #[Test]
    public function test_user_typing_event_is_captured_and_formatted_correctly()
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

        // JSON 데이터 검증
        $lines = explode("\n", $sseFormat);
        $dataLine = '';
        foreach ($lines as $line) {
            if (strpos($line, 'data: ') === 0) {
                $dataLine = substr($line, 6);
                break;
            }
        }

        $decodedData = json_decode($dataLine, true);
        $this->assertIsArray($decodedData);
        $this->assertEquals('typing', $decodedData['type']);
        $this->assertEquals($this->chatRoom->id, $decodedData['room_id']);
        $this->assertEquals('start', $decodedData['action']);
        $this->assertEquals($this->user->uuid, $decodedData['user_uuid']);
    }

    #[Test]
    public function test_event_listener_registration_works()
    {
        // 실제 이벤트 리스너가 작동하는지 확인
        Event::fake();

        // Given: 메시지 데이터
        $messageData = [
            'id' => 555,
            'room_id' => $this->chatRoom->id,
            'user_uuid' => $this->user->uuid,
            'message' => '이벤트 리스너 테스트 메시지',
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

        // When: MessageSent 이벤트 발생
        event(new MessageSent($this->chatRoom, $messageData, $participants));

        // Then: 이벤트가 정상적으로 발생했는지 확인
        Event::assertDispatched(MessageSent::class, function ($event) use ($messageData) {
            return $event->room->id === $this->chatRoom->id
                && $event->message['message'] === $messageData['message']
                && $event->message['user_uuid'] === $this->user->uuid;
        });
    }

    #[Test]
    public function test_multiple_event_types_work_correctly()
    {
        Event::fake();

        // Given: 메시지 이벤트와 타이핑 이벤트
        $messageData = [
            'id' => 777,
            'room_id' => $this->chatRoom->id,
            'user_uuid' => $this->user->uuid,
            'message' => '다중 이벤트 테스트',
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

        // When: 두 이벤트를 연속으로 발생
        event(new MessageSent($this->chatRoom, $messageData, $participants));
        event(new UserTyping($this->chatRoom->id, $this->user->uuid, $this->user->name, 'start'));

        // Then: 두 이벤트 모두 정상적으로 발생했는지 확인
        Event::assertDispatched(MessageSent::class);
        Event::assertDispatched(UserTyping::class);

        Event::assertDispatchedTimes(MessageSent::class, 1);
        Event::assertDispatchedTimes(UserTyping::class, 1);
    }

    #[Test]
    public function test_korean_characters_in_sse_events()
    {
        // Given: 한글 메시지 데이터
        $koreanMessage = '안녕하세요! SSE 실시간 채팅에서 한글 테스트입니다. 😊🚀';
        $messageData = [
            'id' => 888,
            'room_id' => $this->chatRoom->id,
            'user_uuid' => $this->user->uuid,
            'message' => $koreanMessage,
            'message_type' => 'text',
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

        // When: MessageSent 이벤트 생성
        $event = new MessageSent($this->chatRoom, $messageData, $participants);
        $sseFormat = $event->toSseFormat();

        // Then: 한글이 올바르게 인코딩되는지 확인
        $this->assertStringContainsString($koreanMessage, $sseFormat);
        $this->assertStringContainsString('한글 사용자', $sseFormat);

        // JSON 파싱 확인
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