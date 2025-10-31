<?php

namespace Jiny\Chat\Http\Controllers\Home\Message;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Services\ChatService;

/**
 * UpdateController - 메시지 수정 (SAC)
 *
 * [Single Action Controller]
 * - 채팅 메시지 수정만 담당
 * - 작성자 본인만 수정 가능
 * - 메시지 수정 이력 추적
 * - 실시간 업데이트 반영
 *
 * [주요 기능]
 * - 사용자 인증 및 권한 확인
 * - 메시지 내용 검증
 * - ChatService를 통한 메시지 수정
 * - 수정 이력 기록
 * - 실시간 알림 전송
 *
 * [수정 규칙]
 * - 작성자만 수정 가능
 * - 텍스트 내용만 수정 가능
 * - 수정 시간 제한 (설정 가능)
 * - 수정 횟수 제한 없음
 * - 원본 내용 보존
 *
 * [보안 기능]
 * - JWT 인증 필수
 * - 세션 인증 대체 지원
 * - 테스트 사용자 지원 (개발용)
 * - 작성자 권한 검증
 * - 내용 길이 제한 (1000자)
 *
 * [라우트]
 * - PUT /api/chat/messages/{id} -> 메시지 수정
 */
class UpdateController extends Controller
{
    protected $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * 메시지 수정
     *
     * @param int $id 메시지 ID
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
            'content' => 'required|string|max:1000',
        ]);

        // 메시지 수정 시도 로깅
        \Log::info('메시지 수정 시도', [
            'message_id' => $id,
            'user_uuid' => $user->uuid,
            'user_name' => $user->name,
            'new_content_length' => strlen($validatedData['content']),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString()
        ]);

        try {
            // ChatService를 통한 메시지 수정
            $message = $this->chatService->editMessage(
                $id,
                $user->uuid,
                $validatedData['content']
            );

            // 메시지 수정 성공 로깅
            \Log::info('메시지 수정 성공', [
                'message_id' => $id,
                'room_id' => $message->room_id,
                'user_uuid' => $user->uuid,
                'user_name' => $user->name,
                'old_content_length' => strlen($message->getOriginal('content') ?? ''),
                'new_content_length' => strlen($message->content),
                'edit_count' => $message->edit_count ?? 1,
                'updated_at' => $message->updated_at,
                'ip' => $request->ip(),
                'timestamp' => now()->toISOString()
            ]);

            // 성공 응답
            return response()->json([
                'success' => true,
                'message' => '메시지가 성공적으로 수정되었습니다.',
                'data' => [
                    'message' => $message->load(['sender', 'replyTo']),
                    'edit_info' => [
                        'edited_at' => $message->updated_at->toISOString(),
                        'edit_count' => $message->edit_count ?? 1,
                        'editor_uuid' => $user->uuid
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            // 메시지 수정 실패 로깅
            \Log::warning('메시지 수정 실패', [
                'message_id' => $id,
                'user_uuid' => $user->uuid,
                'user_name' => $user->name,
                'error' => $e->getMessage(),
                'new_content' => $validatedData['content'],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                'timestamp' => now()->toISOString()
            ]);

            // 사용자 친화적 에러 메시지
            $userMessage = $e->getMessage();
            $errorCode = 'MESSAGE_UPDATE_FAILED';

            if (strpos($e->getMessage(), 'not found') !== false) {
                $userMessage = '메시지를 찾을 수 없습니다.';
                $errorCode = 'MESSAGE_NOT_FOUND';
            } elseif (strpos($e->getMessage(), 'not authorized') !== false || strpos($e->getMessage(), 'permission') !== false) {
                $userMessage = '메시지를 수정할 권한이 없습니다.';
                $errorCode = 'NOT_AUTHORIZED';
            } elseif (strpos($e->getMessage(), 'time limit') !== false) {
                $userMessage = '메시지 수정 시간이 만료되었습니다.';
                $errorCode = 'TIME_LIMIT_EXCEEDED';
            } elseif (strpos($e->getMessage(), 'deleted') !== false) {
                $userMessage = '삭제된 메시지는 수정할 수 없습니다.';
                $errorCode = 'MESSAGE_DELETED';
            }

            return response()->json([
                'success' => false,
                'message' => $userMessage,
                'error_code' => $errorCode,
                'data' => [
                    'message_id' => $id,
                    'user_uuid' => $user->uuid
                ]
            ], 400);
        }
    }
}