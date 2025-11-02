<?php

namespace Jiny\Chat\Http\Controllers\Home\Room;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;

/**
 * ShowController - 채팅방 메시지 표시
 *
 * 채팅방 입장 시 SQLite에서 메시지를 조회하여 표시합니다.
 */
class ShowController extends Controller
{
    public function __invoke(Request $request, $roomId)
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

        try {
            // 1. 채팅방 조회
            $room = ChatRoom::findOrFail($roomId);

            // 2. 사용자 참여 여부 확인
            $participant = ChatParticipant::where('room_id', $room->id)
                ->where('user_uuid', $user->uuid)
                ->where('status', 'active')
                ->first();

            if (!$participant) {
                return redirect()->route('home.chat.index')
                    ->withErrors(['error' => '이 채팅방에 참여하지 않았습니다.']);
            }

            Log::info('채팅방 접근', [
                'room_id' => $roomId,
                'room_title' => $room->title,
                'user_uuid' => $user->uuid
            ]);

            // 3. 참여자 목록 조회
            $participants = ChatParticipant::where('room_id', $room->id)
                ->where('status', 'active')
                ->orderBy('role', 'desc') // owner, admin, member 순
                ->orderBy('joined_at', 'asc')
                ->get();

            // 5. 사용자 권한 정보 추가
            $room->user_role = $participant->role;
            $room->is_owner = $room->owner_uuid === $user->uuid;
            $room->can_moderate = in_array($participant->role, ['owner', 'admin']);

            // 6. 읽음 상태 업데이트 (마지막 접근 시간)
            $participant->update([
                'last_seen_at' => now(),
                'last_read_at' => now()
            ]);

            return view('jiny-chat::home.room.show', compact(
                'room',
                'participants',
                'user',
                'participant'
            ));

        } catch (\Exception $e) {
            Log::error('채팅방 접근 실패', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_uuid' => $user->uuid
            ]);

            return redirect()->route('home.chat.index')
                ->withErrors(['error' => '채팅방에 접근할 수 없습니다: ' . $e->getMessage()]);
        }
    }

}