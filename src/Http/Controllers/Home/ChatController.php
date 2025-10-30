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

        // 사용자가 참여한 채팅방 목록 (owner 정보 포함)
        $participatingRooms = ChatRoom::whereHas('participants', function ($query) use ($user) {
            $query->where('user_uuid', $user->uuid)
                  ->where('status', 'active');
        })
        ->with(['latestMessage', 'activeParticipants', 'participants' => function ($query) use ($user) {
            $query->where('user_uuid', $user->uuid)->where('status', 'active');
        }])
        ->orderBy('last_activity_at', 'desc')
        ->paginate(10);

        // 각 채팅방에 대한 사용자 역할 정보 추가
        foreach ($participatingRooms as $room) {
            $participant = $room->participants->where('user_uuid', $user->uuid)->first();
            $room->user_role = $participant ? $participant->role : null;
            $room->is_owner = $room->owner_uuid === $user->uuid;
        }

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
     * 채팅방 편집 페이지
     */
    public function edit($id, Request $request)
    {
        // JWT 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            return redirect()->route('login')->with('error', '로그인이 필요합니다.');
        }

        // 채팅방 조회
        $room = ChatRoom::find($id);
        if (!$room) {
            return redirect()->route('home.chat.index')->with('error', '채팅방을 찾을 수 없습니다.');
        }

        // 방장 권한 확인
        if ($room->owner_uuid !== $user->uuid) {
            return redirect()->route('home.chat.index')->with('error', '방장만 설정을 변경할 수 있습니다.');
        }

        // UI 설정에서 배경색 추출
        $backgroundColor = '#f8f9fa'; // 기본값
        if ($room->ui_settings && isset($room->ui_settings['background_color'])) {
            $backgroundColor = $room->ui_settings['background_color'];
        }

        return view('jiny-chat::home.room.edit', compact('room', 'backgroundColor'));
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

    /**
     * 방 설정 페이지 (owner 전용)
     */
    public function roomSettings($id, Request $request)
    {
        // JWT 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // 채팅방 조회
        $room = ChatRoom::find($id);
        if (!$room) {
            return response()->json(['error' => 'Room not found'], 404);
        }

        // 방장 권한 확인
        if ($room->owner_uuid !== $user->uuid) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        // UI 설정에서 배경색 로드
        $uiSettings = $room->ui_settings ?? [];
        $backgroundColor = $uiSettings['background_color'] ?? '#f8f9fa';

        // 방 설정 폼 HTML 반환
        $html = view('jiny-chat::partials.room-settings-form', compact('room', 'backgroundColor'))->render();

        return response($html);
    }

    /**
     * 방 설정 업데이트 (owner 전용)
     */
    public function updateRoomSettings($id, Request $request)
    {
        // JWT 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // 채팅방 조회
        $room = ChatRoom::find($id);
        if (!$room) {
            return response()->json(['error' => 'Room not found'], 404);
        }

        // 방장 권한 확인
        if ($room->owner_uuid !== $user->uuid) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:public,private,group',
            'category' => 'nullable|string|max:50',
            'is_public' => 'boolean',
            'allow_join' => 'boolean',
            'allow_invite' => 'boolean',
            'allow_file_upload' => 'boolean',
            'allow_voice_message' => 'boolean',
            'allow_image_upload' => 'boolean',
            'require_approval' => 'boolean',
            'auto_moderation' => 'boolean',
            'show_member_list' => 'boolean',
            'allow_mentions' => 'boolean',
            'allow_reactions' => 'boolean',
            'read_receipts' => 'boolean',
            'password' => 'nullable|string|min:4|max:255',
            'max_participants' => 'nullable|integer|min:0|max:1000',
            'message_retention_days' => 'nullable|integer|min:0|max:365',
            'max_file_size_mb' => 'nullable|integer|min:1|max:100',
            'slow_mode_seconds' => 'nullable|integer|min:0|max:3600',
            'daily_message_limit' => 'nullable|integer|min:0|max:10000',
            'background_color' => 'required|regex:/^#[a-fA-F0-9]{6}$/',
            'blocked_words' => 'nullable|string',
            'room_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        try {
            // 기존 UI 설정 가져오기
            $uiSettings = $room->ui_settings ?? [];
            $uiSettings['background_color'] = $request->background_color;

            // 금지어 처리
            $blockedWords = [];
            if ($request->blocked_words) {
                $blockedWords = json_decode($request->blocked_words, true) ?: [];
            }

            $updateData = [
                'title' => $request->title,
                'description' => $request->description,
                'type' => $request->type,
                'category' => $request->category,
                'is_public' => $request->boolean('is_public'),
                'allow_join' => $request->boolean('allow_join'),
                'allow_invite' => $request->boolean('allow_invite'),
                'allow_file_upload' => $request->boolean('allow_file_upload', true),
                'allow_voice_message' => $request->boolean('allow_voice_message', true),
                'allow_image_upload' => $request->boolean('allow_image_upload', true),
                'require_approval' => $request->boolean('require_approval'),
                'auto_moderation' => $request->boolean('auto_moderation'),
                'show_member_list' => $request->boolean('show_member_list', true),
                'allow_mentions' => $request->boolean('allow_mentions', true),
                'allow_reactions' => $request->boolean('allow_reactions', true),
                'read_receipts' => $request->boolean('read_receipts', true),
                'max_participants' => $request->max_participants ?: 0,
                'message_retention_days' => $request->message_retention_days ?: 0,
                'max_file_size_mb' => $request->max_file_size_mb ?: 10,
                'slow_mode_seconds' => $request->slow_mode_seconds ?: 0,
                'daily_message_limit' => $request->daily_message_limit ?: 0,
                'blocked_words' => $blockedWords,
                'ui_settings' => $uiSettings,
                'updated_at' => now(),
            ];

            // 이미지 업로드 처리
            if ($request->hasFile('room_image')) {
                $image = $request->file('room_image');

                // 기존 이미지 삭제
                if ($room->image && \Storage::disk('public')->exists($room->image)) {
                    \Storage::disk('public')->delete($room->image);
                }

                // 새 이미지 저장
                $imagePath = $image->store('chat/rooms', 'public');
                $updateData['image'] = $imagePath;

                \Log::info('채팅방 이미지 업로드 완료', [
                    'room_id' => $room->id,
                    'image_path' => $imagePath,
                    'original_name' => $image->getClientOriginalName()
                ]);
            }

            // 비밀번호가 입력된 경우에만 업데이트
            if ($request->filled('password')) {
                $updateData['password'] = bcrypt($request->password);
            }

            $room->update($updateData);

            return response()->json([
                'success' => true,
                'message' => '방 설정이 성공적으로 변경되었습니다.'
            ]);

        } catch (\Exception $e) {
            \Log::error('방 설정 업데이트 실패', [
                'room_id' => $id,
                'error' => $e->getMessage(),
                'user_uuid' => $user->uuid
            ]);

            return response()->json([
                'success' => false,
                'error' => '설정 저장 중 오류가 발생했습니다.'
            ], 500);
        }
    }

    /**
     * 초대 링크 관리 페이지
     */
    public function invites(Request $request)
    {
        // JWT 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            // 임시 테스트 사용자 생성
            $user = (object) [
                'uuid' => 'test-user-' . time(),
                'name' => '테스트 사용자',
                'email' => 'test@example.com',
                'avatar' => null,
                'shard_id' => 1
            ];
        }

        try {
            // 내가 생성한 채팅방 (초대 링크 발급)
            $myRooms = ChatRoom::where('owner_uuid', $user->uuid)
                ->where('status', 'active')
                ->with('activeParticipants')
                ->orderBy('created_at', 'desc')
                ->get();

            // 최근 참여한 채팅방 (다른 사람 방)
            $recentJoinedRooms = ChatRoom::whereHas('activeParticipants', function ($query) use ($user) {
                    $query->where('user_uuid', $user->uuid);
                })
                ->where('owner_uuid', '!=', $user->uuid)
                ->with(['activeParticipants' => function ($query) use ($user) {
                    $query->where('user_uuid', $user->uuid);
                }])
                ->orderBy('last_activity_at', 'desc')
                ->limit(10)
                ->get();

            return view('jiny-chat::home.invite.index', [
                'user' => $user,
                'myRooms' => $myRooms,
                'recentJoinedRooms' => $recentJoinedRooms
            ]);

        } catch (\Exception $e) {
            \Log::error('초대 링크 관리 페이지 로드 실패', [
                'error' => $e->getMessage(),
                'user_uuid' => $user->uuid ?? null
            ]);

            return back()->with('error', '페이지를 불러오는 중 오류가 발생했습니다.');
        }
    }

    /**
     * 초대 코드 재발급
     */
    public function regenerateInviteCode(Request $request, $id)
    {
        // JWT 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            // 임시 테스트 사용자 생성
            $user = (object) [
                'uuid' => 'test-user-' . time(),
                'name' => '테스트 사용자',
                'email' => 'test@example.com',
                'avatar' => null,
                'shard_id' => 1
            ];
        }

        try {
            // 채팅방 찾기 및 권한 확인
            $room = ChatRoom::where('id', $id)
                ->where('owner_uuid', $user->uuid)
                ->where('status', 'active')
                ->first();

            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => '채팅방을 찾을 수 없거나 권한이 없습니다.'
                ], 404);
            }

            // 새로운 초대 코드 생성
            do {
                $newInviteCode = \Str::random(12);
            } while (ChatRoom::where('invite_code', $newInviteCode)->exists());

            // 초대 코드 업데이트
            $room->update([
                'invite_code' => $newInviteCode,
                'updated_at' => now()
            ]);

            \Log::info('초대 코드 재발급', [
                'room_id' => $room->id,
                'room_title' => $room->title,
                'old_invite_code' => $room->invite_code,
                'new_invite_code' => $newInviteCode,
                'user_uuid' => $user->uuid
            ]);

            return response()->json([
                'success' => true,
                'message' => '새로운 초대 링크가 발급되었습니다.',
                'invite_code' => $newInviteCode,
                'invite_url' => url('/chat/invite/' . $newInviteCode)
            ]);

        } catch (\Exception $e) {
            \Log::error('초대 코드 재발급 실패', [
                'room_id' => $id,
                'error' => $e->getMessage(),
                'user_uuid' => $user->uuid ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => '초대 링크 발급 중 오류가 발생했습니다.'
            ], 500);
        }
    }
}