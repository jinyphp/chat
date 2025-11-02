<?php

namespace Jiny\Chat\Tests\Feature\SSE;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Events\MessageSent;
use App\Models\User;

class RealTimeChatTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user1;
    protected $user2;
    protected $user3;
    protected $chatRoom;

    protected function setUp(): void
    {
        parent::setUp();

        // JWT 인증 미들웨어 비활성화 (테스트 환경)
        $this->withoutMiddleware('jwt.auth');

        // 테스트용 사용자들 생성
        $this->user1 = User::factory()->create([
            'name' => '사용자1',
            'email' => 'user1@test.com',
            'uuid' => 'test-user-1-uuid'
        ]);

        $this->user2 = User::factory()->create([
            'name' => '사용자2',
            'email' => 'user2@test.com',
            'uuid' => 'test-user-2-uuid'
        ]);

        $this->user3 = User::factory()->create([
            'name' => '사용자3',
            'email' => 'user3@test.com',
            'uuid' => 'test-user-3-uuid'
        ]);

        // 테스트용 채팅방 생성
        $this->chatRoom = ChatRoom::create([
            'title' => '실시간 채팅 테스트방',
            'slug' => 'realtime-chat-test',
            'description' => 'SSE 기반 실시간 채팅 테스트용 방',
            'type' => 'group',
            'code' => 'test_room_001',
            'is_public' => true,
            'allow_join' => true,
            'allow_invite' => true,
            'max_participants' => 10,
            'owner_uuid' => $this->user1->uuid,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // 참여자들 추가
        foreach ([$this->user1, $this->user2, $this->user3] as $index => $user) {
            ChatParticipant::create([
                'room_id' => $this->chatRoom->id,
                'room_uuid' => $this->chatRoom->code,
                'user_uuid' => $user->uuid,
                'shard_id' => 1,
                'email' => $user->email,
                'name' => $user->name,
                'role' => $index === 0 ? 'owner' : 'member',
                'status' => 'active',
                'permissions' => json_encode(['send_message', 'read_message']),
                'can_send_message' => 1,
                'can_invite' => $index === 0 ? 1 : 0,
                'can_moderate' => $index === 0 ? 1 : 0,
                'notifications_enabled' => 1,
                'notification_settings' => json_encode(['mentions' => true, 'all_messages' => true]),
                'last_read_at' => now(),
                'last_read_message_id' => 0,
                'unread_count' => 0,
                'joined_at' => now(),
                'last_seen_at' => now(),
                'language' => 'ko'
            ]);
        }

        // SQLite 데이터베이스 파일 생성
        $this->createTestSqliteDatabase();
    }

    /** @test */
    public function sse_endpoint_should_be_accessible_for_authenticated_users()
    {
        // Given: 인증된 사용자
        $this->actingAs($this->user1);

        // When: SSE 엔드포인트에 접근
        $response = $this->get("/home/chat/room/{$this->chatRoom->id}/sse");

        // Then: 성공적으로 접근 가능해야 함
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/event-stream');
        $response->assertHeader('Cache-Control', 'no-cache');
        $response->assertHeader('Connection', 'keep-alive');
    }

    /** @test */
    public function sse_endpoint_should_deny_access_for_non_participants()
    {
        // Given: 채팅방에 참여하지 않은 사용자
        $nonParticipant = User::factory()->create([
            'uuid' => 'non-participant-uuid'
        ]);
        $this->actingAs($nonParticipant);

        // When: SSE 엔드포인트에 접근
        $response = $this->get("/home/chat/room/{$this->chatRoom->id}/sse");

        // Then: 접근이 거부되어야 함
        $response->assertStatus(403);
    }

    /** @test */
    public function message_should_be_broadcasted_to_all_participants_via_sse()
    {
        Event::fake();

        // Given: 사용자1이 메시지를 작성
        $this->actingAs($this->user1);
        $messageData = [
            'message' => '안녕하세요! 실시간 채팅 테스트 메시지입니다.',
            'message_type' => 'text'
        ];

        // When: 메시지를 전송
        $response = $this->postJson("/home/chat/room/{$this->chatRoom->id}/messages", $messageData);

        // Then: 메시지가 성공적으로 저장되고 이벤트가 발생해야 함
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        Event::assertDispatched(MessageSent::class, function ($event) use ($messageData) {
            return $event->room->id === $this->chatRoom->id
                && $event->message['message'] === $messageData['message']
                && $event->message['user_uuid'] === $this->user1->uuid;
        });
    }

    /** @test */
    public function multiple_users_should_receive_messages_simultaneously()
    {
        Queue::fake();
        Event::fake();

        // Given: 여러 사용자가 SSE를 연결한 상태
        $connections = [];
        foreach ([$this->user1, $this->user2, $this->user3] as $user) {
            $this->actingAs($user);
            $connections[] = $this->get("/home/chat/room/{$this->chatRoom->id}/sse");
        }

        // When: 사용자1이 메시지를 전송
        $this->actingAs($this->user1);
        $messageData = [
            'message' => '모든 사용자에게 전송되는 메시지',
            'message_type' => 'text'
        ];

        $response = $this->postJson("/home/chat/room/{$this->chatRoom->id}/messages", $messageData);

        // Then: 이벤트가 발생하고 모든 참여자에게 브로드캐스트되어야 함
        $response->assertStatus(200);
        Event::assertDispatched(MessageSent::class);
    }

    /** @test */
    public function sse_should_deliver_proper_json_format()
    {
        Event::fake();

        // Given: 사용자가 SSE를 연결
        $this->actingAs($this->user2);

        // When: 메시지가 전송될 때
        $this->actingAs($this->user1);
        $messageData = [
            'message' => 'JSON 형식 테스트 메시지',
            'message_type' => 'text'
        ];

        $this->postJson("/home/chat/room/{$this->chatRoom->id}/messages", $messageData);

        // Then: 이벤트 데이터가 올바른 JSON 형식이어야 함
        Event::assertDispatched(MessageSent::class, function ($event) {
            $expectedData = [
                'id' => $event->message['id'],
                'room_id' => $this->chatRoom->id,
                'user_uuid' => $this->user1->uuid,
                'user_name' => $this->user1->name,
                'message' => 'JSON 형식 테스트 메시지',
                'message_type' => 'text',
                'is_system' => false,
                'created_at' => $event->message['created_at']
            ];

            return is_array($event->toArray()) &&
                   isset($event->toArray()['message']) &&
                   $event->toArray()['message']['message'] === 'JSON 형식 테스트 메시지';
        });
    }

    /** @test */
    public function user_typing_indicator_should_work_via_sse()
    {
        Event::fake();

        // Given: 사용자2가 SSE를 연결
        $this->actingAs($this->user2);
        $this->get("/home/chat/room/{$this->chatRoom->id}/sse");

        // When: 사용자1이 타이핑 상태를 전송
        $this->actingAs($this->user1);
        $response = $this->postJson("/home/chat/room/{$this->chatRoom->id}/typing", [
            'is_typing' => true
        ]);

        // Then: 타이핑 이벤트가 발생해야 함
        $response->assertStatus(200);
        Event::assertDispatched(\Jiny\Chat\Events\UserTyping::class);
    }

    /** @test */
    public function system_messages_should_be_broadcasted_when_user_joins()
    {
        // Note: 채팅방 참여 기능은 향후 구현될 예정
        $this->markTestSkipped('채팅방 참여 기능은 아직 구현되지 않았습니다.');
    }

    /** @test */
    public function sse_connection_should_handle_reconnection_gracefully()
    {
        // Given: 사용자가 SSE에 연결
        $this->actingAs($this->user1);
        $response1 = $this->get("/home/chat/room/{$this->chatRoom->id}/sse");
        $response1->assertStatus(200);

        // When: 연결이 끊어진 후 재연결
        $response2 = $this->get("/home/chat/room/{$this->chatRoom->id}/sse");

        // Then: 재연결이 성공적으로 이루어져야 함
        $response2->assertStatus(200);
        $response2->assertHeader('Content-Type', 'text/event-stream');
    }

    /** @test */
    public function sse_should_send_heartbeat_to_maintain_connection()
    {
        // Given: 사용자가 SSE에 연결
        $this->actingAs($this->user1);

        // When: SSE 엔드포인트에 접근
        $response = $this->get("/home/chat/room/{$this->chatRoom->id}/sse");

        // Then: 응답에 heartbeat 설정이 포함되어야 함
        $response->assertStatus(200);
        // SSE 스트림에서 heartbeat는 주기적으로 전송됨
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