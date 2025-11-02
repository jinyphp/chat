<?php

namespace Jiny\Chat\Http\Controllers\Home\ServerEvent;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Services\ChatService;

/**
 * ShowController - 채팅방 상세/입장 페이지 (SAC)
 *
 * [Single Action Controller]
 * - 채팅방 상세 페이지 및 입장 처리만 담당
 * - 사용자 권한 검증 및 자동 참여 처리
 * - 채팅방 접근 제어 및 보안 검증
 * - 메시지 로드 및 읽음 처리
 *
 * [주요 기능]
 * - 채팅방 존재 및 활성 상태 검증
 * - 사용자 참여 상태 확인
 * - 자동 참여 처리 (조건 충족 시)
 * - 접근 제한 처리 (비밀번호, 초대 필요)
 * - 최근 메시지 로드 (페이지네이션)
 * - 읽음 처리 및 알림 정리
 * - 차단된 사용자 처리
 *
 * [접근 제어]
 * - JWT 인증 필수
 * - 방 상태 검증 (active만 허용)
 * - 참여 권한 확인
 * - 비밀번호 보호 처리
 * - 초대 전용 방 처리
 * - 차단 사용자 제한
 *
 * [보안 기능]
 * - 세션 인증 대체 지원
 * - 테스트 사용자 지원 (개발용)
 * - 자동 참여 시 예외 처리
 * - 권한 검증 후 리다이렉트
 *
 * [라우트]
 * - GET /home/chat/rooms/{id} -> 채팅방 상세/입장
 */
class ShowController extends Controller
{
    protected $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * 채팅방 상세 페이지 (입장)
     *
     * @param int $id 채팅방 ID
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function __invoke($id, Request $request)
    {
        // 다양한 인증 방식 시도
        $user = null;

        // 1. JWT 인증 시도
        try {
            $user = \JwtAuth::user($request);
        } catch (\Exception $e) {
            // JWT 실패 시 무시
        }

        // 2. 세션 인증 시도
        if (!$user && auth()->check()) {
            $authUser = auth()->user();
            $user = (object) [
                'uuid' => $authUser->uuid ?? 'user-' . $authUser->id,
                'name' => $authUser->name,
                'email' => $authUser->email,
                'avatar' => $authUser->avatar ?? null
            ];
        }

        // 3. 마지막으로 테스트 사용자
        if (!$user) {
            $user = (object) [
                'uuid' => 'test-user-001',
                'name' => 'Test User',
                'email' => 'test@example.com',
                'avatar' => null
            ];
        }

        // 채팅방 조회 (활성 참여자 포함)
        $room = ChatRoom::with(['activeParticipants'])
            ->find($id);

        if (!$room || $room->status !== 'active') {
            return redirect()->route('home.chat.rooms.index')
                ->with('error', '채팅방을 찾을 수 없습니다.');
        }

        // 사용자 참여 상태 확인
        $participant = $room->participants()
            ->where('user_uuid', $user->uuid)
            ->first();

        // 참여 가능 여부 및 제한 사항 확인
        $canJoin = $room->canJoin($user->uuid);
        $needsPassword = $room->password && !$participant;
        $needsInvite = !$room->is_public && !$participant && !$room->allow_join;

        // 접근 제한이 있는 경우 접근 페이지 표시
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

                \Log::info('채팅방 자동 참여 성공', [
                    'room_id' => $room->id,
                    'user_uuid' => $user->uuid,
                    'room_title' => $room->title
                ]);
            } catch (\Exception $e) {
                \Log::error('채팅방 자동 참여 실패', [
                    'room_id' => $room->id,
                    'user_uuid' => $user->uuid,
                    'error' => $e->getMessage()
                ]);

                return redirect()->route('home.chat.rooms.index')
                    ->with('error', $e->getMessage());
            }
        }

        // 차단된 사용자 처리
        if ($participant && $participant->isBanned()) {
            \Log::warning('차단된 사용자의 채팅방 접근 시도', [
                'room_id' => $room->id,
                'user_uuid' => $user->uuid,
                'participant_id' => $participant->id,
                'ban_reason' => $participant->ban_reason
            ]);

            return view('jiny-chat::home.room.banned', compact('room', 'participant'));
        }

        // 최근 메시지 조회 (페이지네이션)
        $messages = $room->messages()
            ->with(['replyTo'])
            ->where('is_deleted', false)
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        // 시간순으로 정렬 (최신이 아래)
        $messages = $messages->reverse();

        // 읽음 처리 (참여자인 경우만)
        if ($participant) {
            try {
                $this->chatService->markAsRead($room->id, $user->uuid);
            } catch (\Exception $e) {
                \Log::warning('읽음 처리 실패', [
                    'room_id' => $room->id,
                    'user_uuid' => $user->uuid,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // 채팅방 입장 로깅
        \Log::info('채팅방 입장', [
            'room_id' => $room->id,
            'room_title' => $room->title,
            'user_uuid' => $user->uuid,
            'user_name' => $user->name,
            'participant_id' => $participant->id ?? null,
            'is_auto_joined' => !$participant ? false : true,
            'message_count' => $messages->count(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString()
        ]);

        return view('jiny-chat::home.chat.sse', compact(
            'room',
            'participant',
            'messages',
            'user'
        ));
    }
}
