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
     * 채팅 대시보드
     */
    public function index(Request $request)
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

        // 사용자가 참여한 채팅방 목록
        $participatingRooms = ChatRoom::whereHas('participants', function ($query) use ($user) {
            $query->where('user_uuid', $user->uuid)
                  ->where('status', 'active');
        })
        ->with(['latestMessage', 'activeParticipants'])
        ->orderBy('last_activity_at', 'desc')
        ->paginate(10);

        // 읽지 않은 메시지 수 조회
        $unreadCounts = [];
        foreach ($participatingRooms as $room) {
            $participant = $room->participants
                ->where('user_uuid', $user->uuid)
                ->first();
            if ($participant) {
                $unreadCounts[$room->id] = $participant->unread_count;
            }
        }

        return view('jiny-chat::home.chat.index', compact(
            'participatingRooms',
            'unreadCounts',
            'user'
        ));
    }

    /**
     * 채팅방 목록
     */
    public function rooms(Request $request)
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

        $query = ChatRoom::query()
            ->where('status', 'active');

        // 필터링
        $type = $request->get('type', 'all');
        switch ($type) {
            case 'public':
                $query->where('is_public', true);
                break;
            case 'joined':
                $query->whereHas('participants', function ($q) use ($user) {
                    $q->where('user_uuid', $user->uuid)
                      ->where('status', 'active');
                });
                break;
            case 'owned':
                $query->where('owner_uuid', $user->uuid);
                break;
        }

        // 검색
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $rooms = $query->with(['activeParticipants'])
            ->orderBy('last_activity_at', 'desc')
            ->paginate(20);

        // API 요청인 경우 JSON 반환
        if ($request->expectsJson()) {
            return response()->json([
                'rooms' => $rooms->items(),
                'pagination' => [
                    'current_page' => $rooms->currentPage(),
                    'last_page' => $rooms->lastPage(),
                    'total' => $rooms->total(),
                ]
            ]);
        }

        return view('jiny-chat::home.room.index', compact('rooms', 'type', 'user'));
    }

    /**
     * 채팅방 상세 (입장)
     */
    public function room($id, Request $request)
    {
        // JWT 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            return redirect()->route('login')->with('error', '로그인이 필요합니다.');
        }

        // 채팅방 조회
        $room = ChatRoom::with(['activeParticipants'])
            ->find($id);

        if (!$room || $room->status !== 'active') {
            return redirect()->route('home.chat.rooms.index')
                ->with('error', '채팅방을 찾을 수 없습니다.');
        }

        // 참여자 확인
        $participant = $room->participants()
            ->where('user_uuid', $user->uuid)
            ->first();

        $canJoin = $room->canJoin($user->uuid);
        $needsPassword = $room->password && !$participant;
        $needsInvite = !$room->is_public && !$participant && !$room->allow_join;

        // 비회원이면서 접근 제한이 있는 경우
        if (!$participant && (!$canJoin || $needsPassword || $needsInvite)) {
            return view('jiny-chat::home.room.access', compact(
                'room',
                'user',
                'needsPassword',
                'needsInvite',
                'canJoin'
            ));
        }

        // 참여자가 아닌 경우 자동 참여 시도
        if (!$participant && $canJoin) {
            try {
                $participant = $this->chatService->joinRoom($room->id, $user->uuid);
            } catch (\Exception $e) {
                return redirect()->route('home.chat.rooms.index')
                    ->with('error', $e->getMessage());
            }
        }

        // 차단된 사용자인 경우
        if ($participant && $participant->isBanned()) {
            return view('jiny-chat::home.room.banned', compact('room', 'participant'));
        }

        // 최근 메시지 조회 (페이지네이션)
        $messages = $room->messages()
            ->with(['replyTo'])
            ->where('is_deleted', false)
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        $messages = $messages->reverse(); // 시간순으로 정렬

        // 읽음 처리
        if ($participant) {
            $this->chatService->markAsRead($room->id, $user->uuid);
        }

        return view('jiny-chat::home.room.show', compact(
            'room',
            'participant',
            'messages',
            'user'
        ));
    }

    /**
     * 채팅방 참여 처리
     */
    public function joinRoom($id, Request $request)
    {
        // JWT 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'password' => 'nullable|string',
            'invite_code' => 'nullable|string',
        ]);

        try {
            $participant = $this->chatService->joinRoom(
                $id,
                $user->uuid,
                $request->invite_code,
                $request->password
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'participant' => $participant,
                    'redirect' => route('home.chat.room', $id)
                ]);
            }

            return redirect()->route('home.chat.room', $id)
                ->with('success', '채팅방에 참여했습니다.');

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage()
                ], 400);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * 채팅방 탈퇴
     */
    public function leaveRoom($id, Request $request)
    {
        // JWT 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $this->chatService->leaveRoom($id, $user->uuid, '사용자 요청');

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'redirect' => route('home.chat.rooms.index')
                ]);
            }

            return redirect()->route('home.chat.rooms.index')
                ->with('success', '채팅방에서 나갔습니다.');

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage()
                ], 400);
            }

            return back()->with('error', $e->getMessage());
        }
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

    /**
     * 사용자 설정
     */
    public function settings(Request $request)
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

        // 사용자의 채팅 관련 설정 조회
        $chatSettings = [
            'notifications_enabled' => true,
            'sound_enabled' => true,
            'theme' => 'light',
            // TODO: 사용자별 설정 테이블에서 조회
        ];

        return view('jiny-chat::home.settings.index', compact('user', 'chatSettings'));
    }

    /**
     * 설정 업데이트
     */
    public function updateSettings(Request $request)
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

        $request->validate([
            'notifications_enabled' => 'boolean',
            'sound_enabled' => 'boolean',
            'theme' => 'in:light,dark',
        ]);

        try {
            // TODO: 사용자별 채팅 설정 저장 로직 구현

            if ($request->expectsJson()) {
                return response()->json(['success' => true]);
            }

            return back()->with('success', '설정이 저장되었습니다.');

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage()
                ], 400);
            }

            return back()->with('error', $e->getMessage());
        }
    }
}