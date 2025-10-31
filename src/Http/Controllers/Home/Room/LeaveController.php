<?php

namespace Jiny\Chat\Http\Controllers\Home\Room;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Services\ChatService;

/**
 * LeaveController - 채팅방 탈퇴 처리 (SAC)
 *
 * [Single Action Controller]
 * - 채팅방 탈퇴 처리만 담당
 * - 사용자 요청에 의한 자발적 탈퇴
 * - 탈퇴 후 정리 작업 수행
 * - 탈퇴 성공 시 목록 페이지로 리다이렉트
 *
 * [주요 기능]
 * - 사용자 인증 확인
 * - ChatService를 통한 탈퇴 처리
 * - 탈퇴 사유 기록 ('사용자 요청')
 * - 성공/실패 응답 처리
 * - JSON 및 웹 요청 모두 지원
 *
 * [탈퇴 처리]
 * - 참여자 상태 비활성화
 * - 읽지 않은 메시지 정리
 * - 알림 설정 해제
 * - 탈퇴 이력 기록
 * - 방장인 경우 특별 처리
 *
 * [보안 기능]
 * - 다양한 인증 방식 지원
 * - 세션 인증 대체 지원
 * - 테스트 사용자 지원 (개발용)
 * - 탈퇴 시도 로깅
 * - 실패 사유 추적
 *
 * [응답 형태]
 * - JSON: API 요청 시 상세 정보 포함
 * - 웹: 성공 시 채팅방 목록으로 리다이렉트
 * - 실패 시 이전 페이지로 돌아가기
 *
 * [예외 처리]
 * - 이미 탈퇴한 사용자
 * - 존재하지 않는 채팅방
 * - 방장 탈퇴 시 권한 이양
 * - 시스템 오류 처리
 *
 * [라우트]
 * - POST /home/chat/rooms/{id}/leave -> 채팅방 탈퇴
 */
class LeaveController extends Controller
{
    protected $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * 채팅방 탈퇴 처리
     *
     * @param int $id 채팅방 ID
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
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

        // 탈퇴 사유 검증 (선택적)
        $reason = $request->input('reason', '사용자 요청');
        if (strlen($reason) > 255) {
            $reason = substr($reason, 0, 255);
        }

        // 탈퇴 시도 로깅
        \Log::info('채팅방 탈퇴 시도', [
            'room_id' => $id,
            'user_uuid' => $user->uuid,
            'user_name' => $user->name,
            'reason' => $reason,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString()
        ]);

        try {
            // ChatService를 통한 탈퇴 처리
            $result = $this->chatService->leaveRoom($id, $user->uuid, $reason);

            // 탈퇴 성공 로깅
            \Log::info('채팅방 탈퇴 성공', [
                'room_id' => $id,
                'user_uuid' => $user->uuid,
                'user_name' => $user->name,
                'reason' => $reason,
                'leave_result' => $result,
                'ip' => $request->ip(),
                'timestamp' => now()->toISOString()
            ]);

            // API 요청인 경우 JSON 응답
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => '채팅방에서 성공적으로 나갔습니다.',
                    'data' => [
                        'room_id' => $id,
                        'user_uuid' => $user->uuid,
                        'leave_reason' => $reason,
                        'left_at' => now()->toISOString(),
                        'redirect_url' => route('home.chat.rooms.index')
                    ]
                ]);
            }

            // 웹 요청인 경우 리다이렉트
            return redirect()->route('home.chat.rooms.index')
                ->with('success', '채팅방에서 나갔습니다.');

        } catch (\Exception $e) {
            // 탈퇴 실패 로깅
            \Log::warning('채팅방 탈퇴 실패', [
                'room_id' => $id,
                'user_uuid' => $user->uuid,
                'user_name' => $user->name,
                'reason' => $reason,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString()
            ]);

            // 특정 오류에 대한 사용자 친화적 메시지
            $userMessage = $e->getMessage();
            $errorCode = 'LEAVE_ROOM_FAILED';

            // 일반적인 오류 케이스별 메시지 개선
            if (strpos($e->getMessage(), 'not found') !== false) {
                $userMessage = '채팅방을 찾을 수 없습니다.';
                $errorCode = 'ROOM_NOT_FOUND';
            } elseif (strpos($e->getMessage(), 'not participant') !== false) {
                $userMessage = '채팅방에 참여하고 있지 않습니다.';
                $errorCode = 'NOT_PARTICIPANT';
            } elseif (strpos($e->getMessage(), 'already left') !== false) {
                $userMessage = '이미 채팅방에서 나간 상태입니다.';
                $errorCode = 'ALREADY_LEFT';
            }

            // API 요청인 경우 JSON 에러 응답
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $userMessage,
                    'error_code' => $errorCode,
                    'data' => [
                        'room_id' => $id,
                        'user_uuid' => $user->uuid,
                        'attempted_reason' => $reason
                    ]
                ], 400);
            }

            // 웹 요청인 경우 이전 페이지로 돌아가기
            return back()->with('error', $userMessage);
        }
    }
}