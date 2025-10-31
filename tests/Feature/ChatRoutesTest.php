<?php

namespace Jiny\Chat\Tests\Feature;

use Jiny\Chat\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChatRoutesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 채팅 메인 대시보드 라우트 테스트
     */
    public function test_chat_dashboard_returns_200()
    {
        $response = $this->actingAsJwtUser()
            ->get('/chat');

        $response->assertStatus(200);
        $response->assertViewIs('jiny-chat::home.dashboard.index');
    }

    /**
     * 채팅방 목록 라우트 테스트
     */
    public function test_chat_rooms_list_returns_200()
    {
        $response = $this->actingAsJwtUser()
            ->get('/chat/rooms');

        $response->assertStatus(200);
        $response->assertViewIs('jiny-chat::rooms.index');
    }

    /**
     * 채팅방 생성 폼 라우트 테스트
     */
    public function test_chat_room_create_form_returns_200()
    {
        $response = $this->actingAsJwtUser()
            ->get('/chat/rooms/create');

        $response->assertStatus(200);
        $response->assertViewIs('jiny-chat::rooms.create');
    }

    /**
     * 채팅방 생성 POST 테스트
     */
    public function test_chat_room_store_returns_redirect()
    {
        $response = $this->actingAsJwtUser()
            ->post('/chat/rooms/create', [
                'title' => 'Test Room',
                'description' => 'Test Description',
                'type' => 'public',
                'is_public' => true,
                'allow_join' => true,
                'allow_invite' => true,
            ]);

        $response->assertStatus(302); // Redirect after creation
    }

    /**
     * 채팅방 상세 라우트 테스트 (방이 없을 때는 404)
     */
    public function test_chat_room_show_returns_404_when_not_exists()
    {
        $response = $this->actingAsJwtUser()
            ->get('/chat/room/999');

        $response->assertStatus(404);
    }

    /**
     * 채팅 설정 페이지 라우트 테스트
     */
    public function test_chat_settings_returns_200()
    {
        $response = $this->actingAsJwtUser()
            ->get('/chat/settings');

        $response->assertStatus(200);
        $response->assertViewIs('jiny-chat::settings.index');
    }

    /**
     * API 라우트 테스트 - 채팅방 목록
     */
    public function test_api_chat_rooms_returns_200()
    {
        $response = $this->actingAsJwtUser()
            ->getJson('/api/chat/rooms');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'rooms',
            'pagination'
        ]);
    }

    /**
     * API 라우트 테스트 - 메시지 전송
     */
    public function test_api_message_store_requires_room_id()
    {
        $response = $this->actingAsJwtUser()
            ->postJson('/api/chat/messages', [
                'content' => 'Test message',
                'type' => 'text'
            ]);

        $response->assertStatus(422); // Validation error - room_id required
    }

    /**
     * 인증되지 않은 사용자의 채팅 접근 테스트
     */
    public function test_unauthenticated_user_redirected_to_login()
    {
        $response = $this->get('/chat');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * 관리자 라우트 테스트 - 채팅방 관리
     */
    public function test_admin_chat_rooms_returns_200()
    {
        $response = $this->actingAsJwtUser()
            ->withMiddleware('admin')
            ->get('/admin/chat/rooms');

        $response->assertStatus(200);
        $response->assertViewIs('jiny-chat::admin.rooms.index');
    }

    /**
     * 관리자 라우트 테스트 - 메시지 관리
     */
    public function test_admin_chat_messages_returns_200()
    {
        $response = $this->actingAsJwtUser()
            ->withMiddleware('admin')
            ->get('/admin/chat/messages');

        $response->assertStatus(200);
        $response->assertViewIs('jiny-chat::admin.messages.index');
    }

    /**
     * 관리자 라우트 테스트 - 사용자 관리
     */
    public function test_admin_chat_users_returns_200()
    {
        $response = $this->actingAsJwtUser()
            ->withMiddleware('admin')
            ->get('/admin/chat/users');

        $response->assertStatus(200);
        $response->assertViewIs('jiny-chat::admin.users.index');
    }

    /**
     * 관리자 라우트 테스트 - 통계
     */
    public function test_admin_chat_stats_returns_200()
    {
        $response = $this->actingAsJwtUser()
            ->withMiddleware('admin')
            ->get('/admin/chat/stats');

        $response->assertStatus(200);
        $response->assertViewIs('jiny-chat::admin.stats');
    }

    /**
     * 초대 링크 라우트 테스트 (유효하지 않은 코드)
     */
    public function test_invite_link_with_invalid_code_returns_404()
    {
        $response = $this->get('/chat/invite/invalid-code');

        $response->assertStatus(404);
    }

    /**
     * 홈 라우트 테스트 (만약 있다면)
     */
    public function test_home_route_returns_200()
    {
        $response = $this->get('/');

        // 홈 라우트가 있다면 200, 없다면 404
        $this->assertContains($response->getStatusCode(), [200, 404]);
    }

    /**
     * 사용자별 채팅방 필터링 테스트
     */
    public function test_user_can_filter_joined_rooms()
    {
        $response = $this->actingAsJwtUser()
            ->get('/chat/rooms?type=joined');

        $response->assertStatus(200);
        $response->assertViewIs('jiny-chat::rooms.index');
    }

    /**
     * 채팅방 검색 기능 테스트
     */
    public function test_user_can_search_rooms()
    {
        $response = $this->actingAsJwtUser()
            ->get('/chat/rooms?search=test');

        $response->assertStatus(200);
        $response->assertViewIs('jiny-chat::rooms.index');
    }

    /**
     * API 메시지 목록 테스트
     */
    public function test_api_room_messages_requires_valid_room()
    {
        $response = $this->actingAsJwtUser()
            ->getJson('/api/chat/rooms/999/messages');

        $response->assertStatus(404);
    }

    /**
     * API 메시지 검색 테스트
     */
    public function test_api_message_search_requires_query()
    {
        $response = $this->actingAsJwtUser()
            ->getJson('/api/chat/rooms/1/messages/search');

        $response->assertStatus(422); // Validation error - query required
    }
}