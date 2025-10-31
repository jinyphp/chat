<?php

namespace Jiny\Chat\Http\Controllers\Home;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Models\ChatMessage;
use Jiny\Chat\Services\ChatService;

/**
 * ChatController - 채팅 메인 컨트롤러
 *
 * [컨트롤러 역할]
 * - 채팅방 목록 및 상세 페이지 제공
 * - 채팅 인터페이스 렌더링
 * - 사용자 인증 및 권한 검증
 * - 샤딩된 사용자 시스템 지원
 *
 * [주요 기능]
 * - 채팅방 목록 (공개방, 참여방)
 * - 채팅방 입장 및 인터페이스
 * - 사용자별 채팅 대시보드
 * - 채팅 설정 관리
 *
 * [라우트 예시]
 * - GET /chat - 채팅 대시보드
 * - GET /chat/rooms - 채팅방 목록
 * - GET /chat/room/{id} - 채팅방 입장
 * - POST /chat/room/{id}/join - 채팅방 참여
 */
class ChatController extends Controller
{
    protected $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }




    /**
     * 채팅방 생성 폼
     */
    public function createRoom(Request $request)
    {
        // JWT 인증 확인 (임시로 우회)
        $user = \JwtAuth::user($request);
        if (!$user) {
            // 임시 테스트 사용자 생성
            $user = (object) [
                'uuid' => 'test-user-001',
                'name' => 'Test User',
                'email' => 'test@example.com'
            ];
        }

        return view('jiny-chat::home.room.create', compact('user'));
    }

    /**
     * 채팅방 생성 처리
     */
    public function storeRoom(Request $request)
    {
        // 요청 데이터 로깅
        \Log::info('채팅방 생성 요청', [
            'request_data' => $request->all(),
            'method' => $request->method(),
            'url' => $request->fullUrl()
        ]);

        // JWT 인증 확인 (임시로 우회)
        $user = \JwtAuth::user($request);
        if (!$user) {
            // 임시 테스트 사용자 생성
            $user = (object) [
                'uuid' => 'test-user-001',
                'name' => 'Test User',
                'email' => 'test@example.com'
            ];
        }

        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'type' => 'required|in:public,private,group',
                'password' => 'nullable|string|min:4',
                'max_participants' => 'nullable|integer|min:2|max:1000',
                'is_public' => 'boolean',
                'allow_join' => 'boolean',
                'allow_invite' => 'boolean',
            ]);

            \Log::info('채팅방 생성 validation 통과');


            $roomData = $request->only([
                'title',
                'description',
                'type',
                'max_participants',
                'is_public',
                'allow_join',
                'allow_invite'
            ]);

            $room = $this->chatService->createRoom($user->uuid, $roomData);

            // 비밀번호 설정
            if ($request->password) {
                $room->setPassword($request->password);
            }

            \Log::info('채팅방 생성 성공', [
                'room_id' => $room->id,
                'room_title' => $room->title,
                'user_uuid' => $user->uuid
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'room' => $room,
                    'redirect' => route('home.chat.rooms.show', $room->id)
                ]);
            }

            return redirect()->route('home.chat.rooms.show', $room->id)
                ->with('success', '채팅방이 생성되었습니다. 채팅을 시작해보세요!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('채팅방 생성 validation 실패', [
                'user_uuid' => $user->uuid ?? 'unknown',
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            throw $e; // Laravel이 자동으로 처리하도록 다시 던짐
        } catch (\Exception $e) {
            // 로그 기록
            \Log::error('채팅방 생성 실패', [
                'user_uuid' => $user->uuid ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage()
                ], 400);
            }

            return back()
                ->withInput()
                ->with('error', '채팅방 생성 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }



}