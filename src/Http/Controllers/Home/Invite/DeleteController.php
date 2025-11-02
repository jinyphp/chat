<?php

namespace Jiny\Chat\Http\Controllers\Home\Invite;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Jiny\Chat\Models\ChatRoom;
use JwtAuth;

class DeleteController extends Controller
{
    /**
     * 초대 링크 삭제 (비활성화)
     */
    public function __invoke(Request $request, $roomId)
    {
        try {
            // 현재 사용자 확인
            $user = JwtAuth::user($request);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => '로그인이 필요합니다.'
                ], 401);
            }

            // 채팅방 확인 및 권한 검증
            $room = ChatRoom::where('id', $roomId)
                ->where('owner_uuid', $user->uuid)
                ->where('status', 'active')
                ->first();

            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => '채팅방을 찾을 수 없거나 권한이 없습니다.'
                ], 404);
            }

            // 초대 코드 제거 (null로 설정)
            $room->update([
                'invite_code' => null,
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => '초대 링크가 삭제되었습니다.'
            ]);

        } catch (\Exception $e) {
            \Log::error('초대 링크 삭제 오류: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => '초대 링크 삭제 중 오류가 발생했습니다.'
            ], 500);
        }
    }
}