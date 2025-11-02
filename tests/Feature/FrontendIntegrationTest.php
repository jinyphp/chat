<?php

namespace Jiny\Chat\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;
use App\Models\User;

class FrontendIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_chat_room_page_loads_correctly()
    {
        // JWT 인증 미들웨어 비활성화
        $this->withoutMiddleware('jwt.auth');

        $user = User::factory()->create([
            'uuid' => 'frontend-test-user'
        ]);

        $chatRoom = ChatRoom::create([
            'title' => '프론트엔드 테스트방',
            'type' => 'group',
            'code' => 'frontend_test',
            'is_public' => true,
            'owner_uuid' => $user->uuid,
        ]);

        ChatParticipant::create([
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
            'notifications_enabled' => 1,
            'last_read_at' => now(),
            'joined_at' => now(),
            'language' => 'ko'
        ]);

        $this->createTestSqliteDatabase($chatRoom);

        $this->actingAs($user);

        // When: 채팅방 페이지 접근
        $response = $this->get("/home/chat/room/{$chatRoom->id}");

        // Then: 페이지가 정상적으로 로드되어야 함
        $response->assertStatus(200);
        $response->assertSee($chatRoom->title);
        $response->assertSee('chatMessages'); // 메시지 컨테이너 ID
        $response->assertSee('messageForm'); // 메시지 폼 ID
        $response->assertSee('connectionStatus'); // 연결 상태 요소

        $this->cleanupTestDatabase($chatRoom);
    }

    /** @test */
    public function test_javascript_variables_are_correctly_set()
    {
        $this->withoutMiddleware('jwt.auth');

        $user = User::factory()->create([
            'uuid' => 'js-test-user',
            'name' => 'JavaScript 테스트 사용자'
        ]);

        $chatRoom = ChatRoom::create([
            'title' => 'JS 변수 테스트방',
            'type' => 'group',
            'code' => 'js_test',
            'is_public' => true,
            'owner_uuid' => $user->uuid,
        ]);

        ChatParticipant::create([
            'room_id' => $chatRoom->id,
            'room_uuid' => $chatRoom->code,
            'user_uuid' => $user->uuid,
            'shard_id' => 1,
            'email' => $user->email,
            'name' => $user->name,
            'role' => 'owner',
            'status' => 'active',
            'permissions' => json_encode(['send_message']),
            'can_send_message' => 1,
            'joined_at' => now(),
            'language' => 'ko'
        ]);

        $this->createTestSqliteDatabase($chatRoom);
        $this->actingAs($user);

        $response = $this->get("/home/chat/room/{$chatRoom->id}");

        // JavaScript 변수들이 올바르게 설정되었는지 확인
        $response->assertSee("window.chatRoom");
        $response->assertSee("id: {$chatRoom->id}");
        $response->assertSee("code: '{$chatRoom->code}'");
        $response->assertSee("uuid: '{$user->uuid}'");
        $response->assertSee("name: '{$user->name}'");

        // SSE URL 경로 확인
        $response->assertSee("/home/chat/room/{$chatRoom->id}/sse");

        // 메시지 전송 URL 확인
        $response->assertSee("/home/chat/room/{$chatRoom->id}/messages");

        $this->cleanupTestDatabase($chatRoom);
    }

    /** @test */
    public function test_message_list_api_returns_correct_structure()
    {
        $this->withoutMiddleware('jwt.auth');

        $user = User::factory()->create([
            'uuid' => 'api-test-user'
        ]);

        $chatRoom = ChatRoom::create([
            'title' => 'API 테스트방',
            'type' => 'group',
            'code' => 'api_test',
            'is_public' => true,
            'owner_uuid' => $user->uuid,
        ]);

        ChatParticipant::create([
            'room_id' => $chatRoom->id,
            'room_uuid' => $chatRoom->code,
            'user_uuid' => $user->uuid,
            'shard_id' => 1,
            'email' => $user->email,
            'name' => $user->name,
            'role' => 'owner',
            'status' => 'active',
            'permissions' => json_encode(['send_message']),
            'can_send_message' => 1,
            'joined_at' => now(),
            'language' => 'ko'
        ]);

        $this->createTestSqliteDatabase($chatRoom);
        $this->actingAs($user);

        // When: 메시지 목록 API 호출
        $response = $this->getJson("/home/chat/room/{$chatRoom->id}/messages/list");

        // Then: 올바른 JSON 구조 반환
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'messages' => [
                    '*' => [
                        'id',
                        'room_id',
                        'user_uuid',
                        'message',
                        'message_type',
                        'is_system',
                        'created_at'
                    ]
                ],
                'participants' => [
                    '*' => [
                        'user_uuid',
                        'name',
                        'role',
                        'status'
                    ]
                ],
                'room' => [
                    'id',
                    'title',
                    'code'
                ],
                'current_user' => [
                    'uuid',
                    'name'
                ]
            ]
        ]);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['data']['messages']);
        $this->assertIsArray($data['data']['participants']);
        $this->assertEquals($chatRoom->id, $data['data']['room']['id']);
        $this->assertEquals($user->uuid, $data['data']['current_user']['uuid']);

        $this->cleanupTestDatabase($chatRoom);
    }

    /** @test */
    public function test_message_creation_api_endpoint()
    {
        $this->withoutMiddleware('jwt.auth');

        $user = User::factory()->create([
            'uuid' => 'message-api-test-user'
        ]);

        $chatRoom = ChatRoom::create([
            'title' => '메시지 API 테스트방',
            'type' => 'group',
            'code' => 'message_api_test',
            'is_public' => true,
            'owner_uuid' => $user->uuid,
        ]);

        ChatParticipant::create([
            'room_id' => $chatRoom->id,
            'room_uuid' => $chatRoom->code,
            'user_uuid' => $user->uuid,
            'shard_id' => 1,
            'email' => $user->email,
            'name' => $user->name,
            'role' => 'owner',
            'status' => 'active',
            'permissions' => json_encode(['send_message']),
            'can_send_message' => 1,
            'joined_at' => now(),
            'language' => 'ko'
        ]);

        $this->createTestSqliteDatabase($chatRoom);
        $this->actingAs($user);

        $messageData = [
            'message' => '프론트엔드 API 테스트 메시지',
            'message_type' => 'text'
        ];

        // When: 메시지 생성 API 호출
        $response = $this->postJson("/home/chat/room/{$chatRoom->id}/messages", $messageData);

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

        // 메시지가 실제로 저장되었는지 확인
        $listResponse = $this->getJson("/home/chat/room/{$chatRoom->id}/messages/list");
        $listData = $listResponse->json();

        $userMessages = array_filter($listData['data']['messages'], function($msg) {
            return !$msg['is_system'];
        });

        $this->assertCount(1, $userMessages);
        $lastMessage = end($userMessages);
        $this->assertEquals($messageData['message'], $lastMessage['message']);

        $this->cleanupTestDatabase($chatRoom);
    }

    /** @test */
    public function test_sse_endpoint_accessibility()
    {
        $this->withoutMiddleware('jwt.auth');

        $user = User::factory()->create([
            'uuid' => 'sse-test-user'
        ]);

        $chatRoom = ChatRoom::create([
            'title' => 'SSE 테스트방',
            'type' => 'group',
            'code' => 'sse_test',
            'is_public' => true,
            'owner_uuid' => $user->uuid,
        ]);

        ChatParticipant::create([
            'room_id' => $chatRoom->id,
            'room_uuid' => $chatRoom->code,
            'user_uuid' => $user->uuid,
            'shard_id' => 1,
            'email' => $user->email,
            'name' => $user->name,
            'role' => 'owner',
            'status' => 'active',
            'permissions' => json_encode(['send_message']),
            'can_send_message' => 1,
            'joined_at' => now(),
            'language' => 'ko'
        ]);

        $this->createTestSqliteDatabase($chatRoom);
        $this->actingAs($user);

        // When: SSE 엔드포인트 접근 (간단한 HEAD 요청으로 테스트)
        $response = $this->get("/home/chat/room/{$chatRoom->id}/sse");

        // Then: SSE 스트림이 시작되어야 함 (200 응답)
        $response->assertStatus(200);

        // SSE 헤더 확인은 실제 EventSource 연결에서만 가능하므로
        // 여기서는 응답 상태만 확인

        $this->cleanupTestDatabase($chatRoom);
    }

    /** @test */
    public function test_typing_indicator_api()
    {
        $this->withoutMiddleware('jwt.auth');

        $user = User::factory()->create([
            'uuid' => 'typing-test-user'
        ]);

        $chatRoom = ChatRoom::create([
            'title' => '타이핑 테스트방',
            'type' => 'group',
            'code' => 'typing_test',
            'is_public' => true,
            'owner_uuid' => $user->uuid,
        ]);

        ChatParticipant::create([
            'room_id' => $chatRoom->id,
            'room_uuid' => $chatRoom->code,
            'user_uuid' => $user->uuid,
            'shard_id' => 1,
            'email' => $user->email,
            'name' => $user->name,
            'role' => 'owner',
            'status' => 'active',
            'permissions' => json_encode(['send_message']),
            'can_send_message' => 1,
            'joined_at' => now(),
            'language' => 'ko'
        ]);

        $this->createTestSqliteDatabase($chatRoom);
        $this->actingAs($user);

        // When: 타이핑 시작 API 호출
        $response = $this->postJson("/home/chat/room/{$chatRoom->id}/typing", [
            'is_typing' => true
        ]);

        // Then: 성공적인 응답
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => '타이핑 상태가 업데이트되었습니다.'
        ]);

        // When: 타이핑 종료 API 호출
        $response = $this->postJson("/home/chat/room/{$chatRoom->id}/typing", [
            'is_typing' => false
        ]);

        // Then: 성공적인 응답
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => '타이핑 상태가 업데이트되었습니다.'
        ]);

        $this->cleanupTestDatabase($chatRoom);
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

    private function cleanupTestDatabase($chatRoom)
    {
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        $sqlitePath = database_path("chat/{$year}/{$month}/{$day}/room-{$chatRoom->id}.sqlite");

        if (file_exists($sqlitePath)) {
            unlink($sqlitePath);
        }
    }
}