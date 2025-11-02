<?php

namespace Jiny\Chat\Http\Controllers\Home\Room;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Services\ChatService;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;

/**
 * 채팅방 삭제 처리 컨트롤러
 *
 * Single Action Controller - 채팅방 삭제 요청 처리
 */
class DeleteController extends Controller
{
    protected $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * 채팅방 삭제 처리
     */
    public function __invoke(Request $request, $id)
    {
        // 요청 데이터 로깅
        \Log::info('채팅방 삭제 요청', [
            'room_id' => $id,
            'method' => $request->method(),
            'url' => $request->fullUrl()
        ]);

        // JWT 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            // 임시 테스트 사용자 생성 (개발용)
            $user = (object) [
                'uuid' => 'test-user-001',
                'name' => 'Test User',
                'email' => 'test@example.com'
            ];
        }

        try {
            // 채팅방 조회
            $room = ChatRoom::findOrFail($id);

            // 권한 확인 - 방장만 삭제 가능
            if ($room->owner_uuid !== $user->uuid) {
                \Log::warning('채팅방 삭제 권한 없음', [
                    'room_id' => $id,
                    'room_owner' => $room->owner_uuid,
                    'user_uuid' => $user->uuid
                ]);

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => '채팅방을 삭제할 권한이 없습니다. 방장만 삭제할 수 있습니다.'
                    ], 403);
                }

                return redirect()->back()
                    ->with('error', '채팅방을 삭제할 권한이 없습니다.');
            }

            // 참여자가 있는지 확인 (옵션: 참여자가 있으면 삭제 불가)
            $participantCount = ChatParticipant::where('room_id', $id)
                ->where('status', 'active')
                ->count();

            if ($participantCount > 1) {
                \Log::warning('채팅방 삭제 실패 - 참여자 존재', [
                    'room_id' => $id,
                    'participant_count' => $participantCount
                ]);

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => '다른 참여자가 있는 채팅방은 삭제할 수 없습니다. 모든 참여자가 나간 후 삭제해주세요.'
                    ], 400);
                }

                return redirect()->back()
                    ->with('error', '다른 참여자가 있는 채팅방은 삭제할 수 없습니다.');
            }

            // 채팅방 삭제 (soft delete)
            $roomTitle = $room->title;
            $room->status = 'deleted';
            $room->deleted_at = now();
            $room->save();

            // 모든 참여자 상태 비활성화
            ChatParticipant::where('room_id', $id)->update([
                'status' => 'inactive',
                'left_at' => now()
            ]);

            \Log::info('채팅방 삭제 성공', [
                'room_id' => $id,
                'room_title' => $roomTitle,
                'user_uuid' => $user->uuid
            ]);

            // JSON 응답
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => '채팅방이 성공적으로 삭제되었습니다.'
                ]);
            }

            return redirect()->route('home.chat.index')
                ->with('success', '채팅방 "' . $roomTitle . '"이 삭제되었습니다.');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::warning('채팅방 삭제 실패 - 채팅방 없음', [
                'room_id' => $id,
                'user_uuid' => $user->uuid ?? 'unknown'
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => '존재하지 않는 채팅방입니다.'
                ], 404);
            }

            return redirect()->back()
                ->with('error', '존재하지 않는 채팅방입니다.');

        } catch (\Exception $e) {
            // 로그 기록
            \Log::error('채팅방 삭제 실패', [
                'room_id' => $id,
                'user_uuid' => $user->uuid ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => '채팅방 삭제 중 오류가 발생했습니다.',
                    'error' => $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', '채팅방 삭제 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }
}