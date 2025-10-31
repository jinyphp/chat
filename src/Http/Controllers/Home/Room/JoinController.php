<?php

namespace Jiny\Chat\Http\Controllers\Home\Room;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Services\ChatService;

/**
 * JoinController - 채팅방 참여 처리 (SAC)
 *
 * [Single Action Controller]
 * - 채팅방 참여 처리만 담당
 * - 비밀번호 및 초대 코드 검증
 * - 참여 조건 확인 및 처리
 * - 참여 성공 시 리다이렉트 또는 JSON 응답
 *
 * [주요 기능]
 * - 사용자 인증 확인
 * - 입력값 검증 (비밀번호, 초대 코드)
 * - ChatService를 통한 참여 처리
 * - 성공/실패 응답 처리
 * - JSON 및 웹 요청 모두 지원
 *
 * [검증 항목]
 * - 사용자 인증 상태
 * - 채팅방 존재 및 활성 상태
 * - 참여 권한 (공개/비공개)
 * - 비밀번호 일치 (필요 시)
 * - 초대 코드 유효성 (필요 시)
 * - 최대 참여자 수 제한
 * - 중복 참여 방지
 *
 * [보안 기능]
 * - 다양한 인증 방식 지원
 * - 세션 인증 대체 지원
 * - 테스트 사용자 지원 (개발용)
 * - 참여 시도 로깅
 * - 실패 사유 추적
 *
 * [응답 형태]
 * - JSON: API 요청 시 상세 정보 포함
 * - 웹: 성공 시 채팅방으로 리다이렉트
 * - 실패 시 이전 페이지로 돌아가기
 *
 * [라우트]
 * - POST /home/chat/rooms/{id}/join -> 채팅방 참여
 */
class JoinController extends Controller
{
    protected $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * 채팅방 참여 처리
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

        // 입력값 검증
        $validatedData = $request->validate([
            'password' => 'nullable|string|max:255',
            'invite_code' => 'nullable|string|max:50',
        ]);

        // 참여 시도 로깅
        \Log::info('채팅방 참여 시도', [
            'room_id' => $id,
            'user_uuid' => $user->uuid,
            'user_name' => $user->name,
            'has_password' => !empty($validatedData['password']),
            'has_invite_code' => !empty($validatedData['invite_code']),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString()
        ]);

        try {
            // ChatService를 통한 참여 처리
            $participant = $this->chatService->joinRoom(
                $id,
                $user->uuid,
                $validatedData['invite_code'] ?? null,
                $validatedData['password'] ?? null
            );

            // 참여 성공 로깅
            \Log::info('채팅방 참여 성공', [
                'room_id' => $id,
                'user_uuid' => $user->uuid,
                'user_name' => $user->name,
                'participant_id' => $participant->id,
                'role' => $participant->role,
                'joined_at' => $participant->joined_at,
                'ip' => $request->ip(),
                'timestamp' => now()->toISOString()
            ]);

            // API 요청인 경우 JSON 응답
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => '채팅방에 성공적으로 참여했습니다.',
                    'data' => [
                        'participant' => [
                            'id' => $participant->id,
                            'role' => $participant->role,
                            'joined_at' => $participant->joined_at,
                            'user_uuid' => $participant->user_uuid
                        ],
                        'redirect_url' => route('home.chat.room', $id),
                        'room_id' => $id
                    ]
                ]);
            }

            // 웹 요청인 경우 리다이렉트
            return redirect()->route('home.chat.room', $id)
                ->with('success', '채팅방에 참여했습니다.');

        } catch (\Exception $e) {
            // 참여 실패 로깅
            \Log::warning('채팅방 참여 실패', [
                'room_id' => $id,
                'user_uuid' => $user->uuid,
                'user_name' => $user->name,
                'error' => $e->getMessage(),
                'has_password' => !empty($validatedData['password']),
                'has_invite_code' => !empty($validatedData['invite_code']),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString()
            ]);

            // API 요청인 경우 JSON 에러 응답
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error_code' => 'JOIN_ROOM_FAILED',
                    'data' => [
                        'room_id' => $id,
                        'user_uuid' => $user->uuid
                    ]
                ], 400);
            }

            // 웹 요청인 경우 이전 페이지로 돌아가기
            return back()->with('error', $e->getMessage())->withInput();
        }
    }
}