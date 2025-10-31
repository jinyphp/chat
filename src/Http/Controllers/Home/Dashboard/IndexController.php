<?php

namespace Jiny\Chat\Http\Controllers\Home\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;

/**
 * IndexController - 채팅 대시보드 메인 페이지 (SAC)
 *
 * [Single Action Controller]
 * - 채팅 대시보드 메인 페이지만 담당
 * - 사용자가 참여한 채팅방 목록 표시
 * - 읽지 않은 메시지 수 표시
 * - 사용자별 채팅 활동 요약
 *
 * [주요 기능]
 * - 참여 중인 채팅방 목록 (페이지네이션)
 * - 각 채팅방별 읽지 않은 메시지 수
 * - 사용자 역할 정보 (방장, 참여자)
 * - 최근 활동 기준 정렬
 *
 * [라우트]
 * - GET /home/chat -> 채팅 대시보드
 */
class IndexController extends Controller
{
    /**
     * 채팅 대시보드 메인 페이지
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function __invoke(Request $request)
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

        return view('jiny-chat::home.dashboard.index', compact(
            'participatingRooms',
            'unreadCounts',
            'user'
        ));
    }
}