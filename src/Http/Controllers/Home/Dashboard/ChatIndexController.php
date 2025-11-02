<?php

namespace Jiny\Chat\Http\Controllers\Home\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;

/**
 * ChatIndexController - 간단한 채팅 대시보드
 *
 * 사용자가 참여한 채팅방 목록을 표시합니다.
 */
class ChatIndexController extends Controller
{
    public function __invoke(Request $request)
    {
        // JWT 인증된 사용자 정보 가져오기
        $authUser = auth()->user();
        if (!$authUser) {
            return redirect()->route('auth.login')
                ->withErrors(['error' => '로그인이 필요합니다.']);
        }

        $user = (object) [
            'uuid' => $authUser->uuid ?? 'user-' . $authUser->id,
            'name' => $authUser->name ?? 'Unknown User',
            'email' => $authUser->email ?? 'unknown@example.com'
        ];

        // 사용자가 참여한 채팅방 목록 조회
        $participatingRooms = ChatRoom::whereHas('participants', function ($query) use ($user) {
            $query->where('user_uuid', $user->uuid)
                  ->where('status', 'active');
        })
        ->with(['activeParticipants'])
        ->orderBy('created_at', 'desc')
        ->paginate(10);

        // 각 채팅방에 대한 사용자 역할 정보 추가
        foreach ($participatingRooms as $room) {
            $participant = ChatParticipant::where('room_id', $room->id)
                ->where('user_uuid', $user->uuid)
                ->where('status', 'active')
                ->first();

            $room->user_role = $participant ? $participant->role : 'member';
            $room->is_owner = $room->owner_uuid === $user->uuid;
            $room->unread_count = $participant ? $participant->unread_count : 0;
        }

        return view('jiny-chat::home.dashboard.index', compact(
            'participatingRooms',
            'user'
        ));
    }
}
