<?php

namespace Jiny\Chat\Http\Controllers\Home\Invite;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Models\ChatRoom;

/**
 * RegenerateController - 초대 코드 재발급 (SAC)
 *
 * [Single Action Controller]
 * - 초대 코드 재발급만 담당
 * - 방장(owner) 전용 기능
 * - 기존 초대 코드를 무효화하고 새로운 코드 생성
 * - 보안을 위한 초대 링크 갱신 기능
 *
 * [주요 기능]
 * - 방장 권한 검증
 * - 유니크한 새로운 초대 코드 생성
 * - 기존 초대 코드 무효화
 * - 새로운 초대 URL 반환
 * - 상세한 로깅 및 감사 추적
 *
 * [보안 기능]
 * - 방장 권한 엄격 검증
 * - 초대 코드 중복 방지
 * - 활성 채팅방만 처리
 * - 요청 로깅 및 추적
 *
 * [인증 지원]
 * - JWT 인증 (우선)
 * - 세션 인증 (대체)
 * - 테스트 사용자 (개발용)
 *
 * [라우트]
 * - POST /home/chat/rooms/{id}/regenerate-invite -> 초대 코드 재발급
 */
class RegenerateController extends Controller
{
    /**
     * 초대 코드 재발급
     *
     * @param Request $request
     * @param int $id 채팅방 ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request, $id)
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
                'avatar' => $authUser->avatar ?? null,
                'shard_id' => $authUser->shard_id ?? 1
            ];
        }

        // 3. 마지막으로 테스트 사용자
        if (!$user) {
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
                    'message' => '채팅방을 찾을 수 없거나 권한이 없습니다.',
                    'error_code' => 'ROOM_NOT_FOUND_OR_ACCESS_DENIED'
                ], 404);
            }

            // 기존 초대 코드 백업 (로깅용)
            $oldInviteCode = $room->invite_code;

            // 새로운 초대 코드 생성 (유니크 보장)
            $maxAttempts = 10;
            $attempts = 0;
            do {
                $newInviteCode = \Str::random(12);
                $attempts++;

                if ($attempts >= $maxAttempts) {
                    throw new \Exception('초대 코드 생성 시도 횟수 초과');
                }
            } while (ChatRoom::where('invite_code', $newInviteCode)->exists());

            // 초대 코드 업데이트
            $room->update([
                'invite_code' => $newInviteCode,
                'updated_at' => now()
            ]);

            // 새로운 초대 URL 생성
            $inviteUrl = url('/chat/invite/' . $newInviteCode);

            // 상세 로깅
            \Log::info('초대 코드 재발급 완료', [
                'room_id' => $room->id,
                'room_title' => $room->title,
                'old_invite_code' => $oldInviteCode,
                'new_invite_code' => $newInviteCode,
                'invite_url' => $inviteUrl,
                'user_uuid' => $user->uuid,
                'user_name' => $user->name,
                'regeneration_attempts' => $attempts,
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'success' => true,
                'message' => '새로운 초대 링크가 발급되었습니다.',
                'data' => [
                    'room_id' => $room->id,
                    'room_title' => $room->title,
                    'invite_code' => $newInviteCode,
                    'invite_url' => $inviteUrl,
                    'old_code_invalidated' => !empty($oldInviteCode),
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('초대 코드 재발급 실패', [
                'room_id' => $id,
                'error' => $e->getMessage(),
                'user_uuid' => $user->uuid ?? null,
                'user_name' => $user->name ?? null,
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '초대 링크 발급 중 오류가 발생했습니다.',
                'error_code' => 'INVITE_REGENERATION_FAILED',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}