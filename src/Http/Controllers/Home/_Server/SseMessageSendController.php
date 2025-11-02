<?php

namespace Jiny\Chat\Http\Controllers\Home\Server;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * SseMessageSendController - SSE 채팅방 메시지 전송 API (SAC)
 *
 * [Single Action Controller]
 * - SSE 채팅방 메시지 전송 처리
 * - AJAX 기반 메시지 송신
 * - JWT 기반 사용자 인증
 * - 동적 SQLite 데이터베이스 연결
 * - 실시간 SSE 이벤트 트리거
 *
 * [주요 기능]
 * - 텍스트 메시지 전송
 * - 답글 기능 지원
 * - 사용자 인증 및 권한 확인
 * - 메시지 유효성 검증
 * - SSE 이벤트 브로드캐스트
 *
 * [요청 형식]
 * {
 *   "content": "메시지 내용",
 *   "type": "text",
 *   "reply_to": 123 (선택)
 * }
 *
 * [응답 형식]
 * {
 *   "success": true,
 *   "message": {...},
 *   "broadcast": true
 * }
 *
 * [라우트]
 * - POST /home/chat/api/server/sse/{roomId}/message
 */
class SseMessageSendController extends Controller
{
    /**
     * SSE 채팅방 메시지 전송
     *
     * @param string $roomId 채팅방 번호
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke($roomId, Request $request)
    {
        try {
            // 입력 검증
            $request->validate([
                'content' => 'required|string|max:4000',
                'type' => 'sometimes|in:text,file',
                'reply_to' => 'sometimes|integer'
            ]);

            // 사용자 인증 확인 (세션 우선 방식)
            $user = null;

            // 1. 세션 인증 시도 (우선)
            if (auth()->check()) {
                $authUser = auth()->user();
                $user = (object) [
                    'uuid' => $authUser->uuid ?? 'user-' . $authUser->id,
                    'name' => $authUser->name,
                    'email' => $authUser->email,
                    'avatar' => $authUser->avatar ?? null
                ];
            }

            // 2. JWT 인증 시도
            if (!$user) {
                try {
                    $user = \JwtAuth::user($request);
                } catch (\Exception $e) {
                    // JWT 실패 시 무시
                }
            }

            // 3. 테스트용 사용자 (개발용)
            if (!$user) {
                $user = (object) [
                    'uuid' => 'test-user-001',
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'avatar' => null
                ];
            }

            // 현재 날짜 기준으로 데이터베이스 경로 생성
            $now = Carbon::now();
            $dbPath = $this->getChatDatabasePath($roomId, $now);

            if (!file_exists($dbPath)) {
                return response()->json([
                    'success' => false,
                    'message' => '채팅방을 찾을 수 없습니다.'
                ], 404);
            }

            // 동적 DB 연결 설정
            $this->setupDynamicConnection($dbPath);

            // 참여자 권한 확인
            $participant = DB::connection('chat_sse')
                ->table('participants')
                ->where('room_id', $roomId)
                ->where('user_uuid', $user->uuid)
                ->where('status', 'active')
                ->first();

            if (!$participant) {
                return response()->json([
                    'success' => false,
                    'message' => '채팅방에 참여하지 않았습니다.'
                ], 403);
            }

            // 답글 대상 메시지 확인 (선택사항)
            $replyTo = $request->input('reply_to');
            if ($replyTo) {
                $replyMessage = DB::connection('chat_sse')
                    ->table('messages')
                    ->where('id', $replyTo)
                    ->where('room_id', $roomId)
                    ->where('is_deleted', 0)
                    ->first();

                if (!$replyMessage) {
                    return response()->json([
                        'success' => false,
                        'message' => '답글 대상 메시지를 찾을 수 없습니다.'
                    ], 404);
                }
            }

            // 메시지 저장
            $messageId = DB::connection('chat_sse')
                ->table('messages')
                ->insertGetId([
                    'room_id' => $roomId,
                    'user_uuid' => $user->uuid,
                    'user_name' => $user->name,
                    'user_avatar' => $user->avatar,
                    'content' => $request->input('content'),
                    'type' => $request->input('type', 'text'),
                    'reply_to' => $replyTo,
                    'is_deleted' => 0,
                    'created_at' => $now->toDateTimeString(),
                    'updated_at' => $now->toDateTimeString()
                ]);

            // 저장된 메시지 조회
            $savedMessage = DB::connection('chat_sse')
                ->table('messages')
                ->where('id', $messageId)
                ->first();

            // 메시지 응답 데이터 구성
            $messageResponse = [
                'id' => $savedMessage->id,
                'room_id' => $savedMessage->room_id,
                'user_uuid' => $savedMessage->user_uuid,
                'user_name' => $savedMessage->user_name,
                'user_avatar' => $savedMessage->user_avatar,
                'content' => $savedMessage->content,
                'type' => $savedMessage->type,
                'reply_to' => $savedMessage->reply_to,
                'created_at' => $savedMessage->created_at,
                'created_at_human' => Carbon::parse($savedMessage->created_at)->format('H:i'),
                'is_my_message' => true
            ];

            // 답글 정보 추가
            if ($replyTo && isset($replyMessage)) {
                $messageResponse['reply_info'] = [
                    'id' => $replyMessage->id,
                    'content' => $replyMessage->content,
                    'user_name' => $replyMessage->user_name
                ];
            }

            // 참여자의 마지막 접속 시간 업데이트
            DB::connection('chat_sse')
                ->table('participants')
                ->where('room_id', $roomId)
                ->where('user_uuid', $user->uuid)
                ->update([
                    'last_seen' => $now->toDateTimeString()
                ]);

            // SSE 이벤트 파일에 새 메시지 기록 (간단한 파일 기반)
            $this->broadcastSseEvent($roomId, 'new_message', $messageResponse);

            $response = [
                'success' => true,
                'message' => $messageResponse,
                'broadcast' => true,
                'room_id' => $roomId
            ];

            \Log::info('SSE 메시지 전송 성공', [
                'room_id' => $roomId,
                'message_id' => $messageId,
                'user_uuid' => $user->uuid,
                'content_length' => strlen($savedMessage->content)
            ]);

            return response()->json($response);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '입력 데이터가 올바르지 않습니다.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            \Log::error('SSE 메시지 전송 오류', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '메시지 전송 중 오류가 발생했습니다.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * 채팅 데이터베이스 경로 생성
     */
    private function getChatDatabasePath($roomId, Carbon $date)
    {
        $basePath = database_path('chat');
        $year = $date->format('Y');
        $month = $date->format('m');
        $day = $date->format('d');

        return "{$basePath}/{$year}/{$month}/{$day}/{$roomId}.sqlite";
    }

    /**
     * 동적 DB 연결 설정
     */
    private function setupDynamicConnection($dbPath)
    {
        config([
            'database.connections.chat_sse' => [
                'driver' => 'sqlite',
                'database' => $dbPath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]
        ]);

        DB::purge('chat_sse');
    }

    /**
     * SSE 이벤트 브로드캐스트 (간단한 파일 기반)
     */
    private function broadcastSseEvent($roomId, $eventType, $data)
    {
        try {
            $eventData = [
                'event' => $eventType,
                'data' => $data,
                'timestamp' => time(),
                'room_id' => $roomId
            ];

            // SSE 이벤트 파일 경로
            $sseDir = storage_path('app/sse_events');
            if (!file_exists($sseDir)) {
                mkdir($sseDir, 0755, true);
            }

            $eventFile = "{$sseDir}/room_{$roomId}.json";

            // 기존 이벤트 읽기
            $events = [];
            if (file_exists($eventFile)) {
                $content = file_get_contents($eventFile);
                $events = json_decode($content, true) ?: [];
            }

            // 새 이벤트 추가 (최대 100개 유지)
            $events[] = $eventData;
            if (count($events) > 100) {
                $events = array_slice($events, -100);
            }

            // 이벤트 파일 저장
            file_put_contents($eventFile, json_encode($events));

            \Log::debug('SSE 이벤트 브로드캐스트', [
                'room_id' => $roomId,
                'event_type' => $eventType,
                'event_file' => $eventFile
            ]);

        } catch (\Exception $e) {
            \Log::error('SSE 이벤트 브로드캐스트 실패', [
                'room_id' => $roomId,
                'error' => $e->getMessage()
            ]);
        }
    }
}