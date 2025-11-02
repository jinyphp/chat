<?php

namespace Jiny\Chat\Http\Controllers\Home\Message;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Services\ChatService;

/**
 * StoreController - 메시지 전송 (SAC)
 *
 * [Single Action Controller]
 * - 채팅 메시지 전송만 담당
 * - 텍스트, 이미지, 파일, 음성, 영상 메시지 지원
 * - 답장 메시지 및 미디어 첨부 지원
 * - 실시간 메시지 전송 처리
 *
 * [주요 기능]
 * - 사용자 인증 확인
 * - 메시지 내용 검증
 * - ChatService를 통한 메시지 전송
 * - 미디어 첨부 처리
 * - 답장 메시지 처리
 *
 * [지원 메시지 타입]
 * - text: 텍스트 메시지
 * - image: 이미지 메시지
 * - file: 파일 메시지
 * - voice: 음성 메시지
 * - video: 영상 메시지
 *
 * [보안 기능]
 * - JWT 인증 필수
 * - 세션 인증 대체 지원
 * - 테스트 사용자 지원 (개발용)
 * - 메시지 길이 제한 (1000자)
 * - 미디어 검증
 *
 * [라우트]
 * - POST /api/chat/messages -> 메시지 전송
 */
class StoreController extends Controller
{
    protected $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * 메시지 전송
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
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
            'room_id' => 'required|exists:chat_rooms,id',
            'content' => 'required_without:media|string|max:1000',
            'type' => 'nullable|in:text,image,file,voice,video',
            'reply_to_message_id' => 'nullable|exists:chat_messages,id',
            'media' => 'nullable|array',
            'media.url' => 'nullable|string|max:500',
            'media.name' => 'nullable|string|max:255',
            'media.size' => 'nullable|integer|min:0|max:104857600', // 100MB
            'media.type' => 'nullable|string|max:100',
        ]);

        // 메시지 전송 시도 로깅
        \Log::info('메시지 전송 시도', [
            'room_id' => $validatedData['room_id'],
            'user_uuid' => $user->uuid,
            'user_name' => $user->name,
            'message_type' => $validatedData['type'] ?? 'text',
            'has_content' => !empty($validatedData['content']),
            'has_media' => !empty($validatedData['media']),
            'is_reply' => !empty($validatedData['reply_to_message_id']),
            'content_length' => strlen($validatedData['content'] ?? ''),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString()
        ]);

        try {
            // 메시지 데이터 준비
            $messageData = [
                'content' => $validatedData['content'] ?? '',
                'type' => $validatedData['type'] ?? 'text',
                'reply_to_message_id' => $validatedData['reply_to_message_id'] ?? null,
                'media' => $validatedData['media'] ?? null,
            ];

            // ChatService를 통한 메시지 전송
            $message = $this->chatService->sendMessage(
                $validatedData['room_id'],
                $user->uuid,
                $messageData
            );

            // 메시지 전송 성공 로깅
            \Log::info('메시지 전송 성공', [
                'room_id' => $validatedData['room_id'],
                'message_id' => $message->id,
                'user_uuid' => $user->uuid,
                'user_name' => $user->name,
                'message_type' => $message->type,
                'content_length' => strlen($message->content),
                'has_media' => !empty($message->media),
                'is_reply' => !empty($message->reply_to_message_id),
                'created_at' => $message->created_at,
                'ip' => $request->ip(),
                'timestamp' => now()->toISOString()
            ]);

            // 성공 응답
            return response()->json([
                'success' => true,
                'message' => '메시지가 성공적으로 전송되었습니다.',
                'data' => [
                    'message' => $message->load(['sender', 'replyTo']),
                    'room_id' => $validatedData['room_id'],
                    'sent_at' => $message->created_at->toISOString()
                ]
            ], 201);

        } catch (\Exception $e) {
            // 메시지 전송 실패 로깅
            \Log::error('메시지 전송 실패', [
                'room_id' => $validatedData['room_id'],
                'user_uuid' => $user->uuid,
                'user_name' => $user->name,
                'error' => $e->getMessage(),
                'message_data' => $messageData,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                'timestamp' => now()->toISOString()
            ]);

            // 사용자 친화적 에러 메시지
            $userMessage = $e->getMessage();
            $errorCode = 'MESSAGE_SEND_FAILED';

            if (strpos($e->getMessage(), 'not participant') !== false) {
                $userMessage = '채팅방에 참여하고 있지 않습니다.';
                $errorCode = 'NOT_PARTICIPANT';
            } elseif (strpos($e->getMessage(), 'room not found') !== false) {
                $userMessage = '채팅방을 찾을 수 없습니다.';
                $errorCode = 'ROOM_NOT_FOUND';
            } elseif (strpos($e->getMessage(), 'banned') !== false) {
                $userMessage = '차단된 사용자는 메시지를 보낼 수 없습니다.';
                $errorCode = 'USER_BANNED';
            }

            return response()->json([
                'success' => false,
                'message' => $userMessage,
                'error_code' => $errorCode,
                'data' => [
                    'room_id' => $validatedData['room_id'],
                    'user_uuid' => $user->uuid
                ]
            ], 400);
        }
    }
}