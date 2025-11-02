<?php

namespace Jiny\Chat\Http\Controllers\Home\Server;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Jiny\Chat\Models\ChatRoom;
use Carbon\Carbon;

/**
 * SseStreamController - Server-Side Events 스트림 컨트롤러 (SAC)
 *
 * [Single Action Controller]
 * - SSE 실시간 이벤트 스트림 제공
 * - 채팅방별 이벤트 스트리밍
 * - 새 메시지 실시간 전송
 * - 연결 상태 관리
 *
 * [주요 기능]
 * - text/event-stream 응답 제공
 * - 실시간 메시지 스트리밍
 * - 연결 유지 및 하트비트
 * - 이벤트 타입별 처리
 * - 클라이언트 연결 상태 추적
 *
 * [SSE 이벤트 타입]
 * - new_message: 새 메시지
 * - user_joined: 사용자 입장
 * - user_left: 사용자 퇴장
 * - heartbeat: 연결 유지
 *
 * [라우트]
 * - GET /home/chat/api/server/sse/{roomId}/stream
 */
class SseStreamController extends Controller
{
    private $maxExecutionTime = 300; // 5분
    private $heartbeatInterval = 30; // 30초

    /**
     * SSE 이벤트 스트림 제공
     *
     * @param string $roomId 채팅방 번호
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke($roomId, Request $request)
    {
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

        // 2. JWT 인증 시도 (Bearer 토큰)
        if (!$user) {
            $authHeader = $request->header('Authorization');
            if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
                try {
                    $token = substr($authHeader, 7);
                    $user = \JwtAuth::parseToken($token)->authenticate();
                } catch (\Exception $e) {
                    // JWT 실패 시 무시
                }
            }
        }

        // 3. JWT 요청 객체에서 직접 시도
        if (!$user) {
            try {
                $user = \JwtAuth::user($request);
            } catch (\Exception $e) {
                // JWT 실패 시 무시
            }
        }

        // 4. 테스트 사용자 (개발용)
        if (!$user) {
            $user = (object) [
                'uuid' => 'test-user-001',
                'name' => 'Test User',
                'email' => 'test@example.com',
                'avatar' => null
            ];
        }

        // 채팅방 조회
        $room = ChatRoom::with(['activeParticipants'])->find($roomId);

        if (!$room || $room->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => '채팅방을 찾을 수 없습니다.'
            ], 404);
        }

        // SQLite 채팅 데이터베이스 설정
        $now = Carbon::now();
        $dbPath = $this->getChatDatabasePath($roomId, $now);

        if (!file_exists($dbPath)) {
            return response()->json([
                'success' => false,
                'message' => '채팅 데이터베이스를 찾을 수 없습니다.'
            ], 404);
        }

        // 동적 DB 연결 설정
        $this->setupDynamicConnection($dbPath);

        // 참여자 권한 확인 (SQLite에서)
        $participant = DB::connection('chat_server')
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

        // SSE 응답 스트림 생성
        return response()->stream(function () use ($roomId, $user, $dbPath) {
            $this->streamEvents($roomId, $user, $dbPath);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no'
        ]);
    }

    /**
     * SSE 이벤트 스트리밍 처리
     */
    private function streamEvents($roomId, $user, $dbPath)
    {
        // 실행 시간 제한 설정
        set_time_limit($this->maxExecutionTime);
        ignore_user_abort(false);

        $startTime = time();
        $lastHeartbeat = time();
        $lastMessageId = 0;
        $lastParticipantCheck = time();

        // SQLite 연결 재설정
        $this->setupDynamicConnection($dbPath);

        // 최신 메시지 ID 조회
        $latestMessage = DB::connection('chat_server')
            ->table('messages')
            ->where('room_id', $roomId)
            ->where('is_deleted', 0)
            ->orderBy('id', 'desc')
            ->first();

        if ($latestMessage) {
            $lastMessageId = $latestMessage->id;
        }

        \Log::info('SSE 스트림 시작', [
            'room_id' => $roomId,
            'user_uuid' => $user->uuid,
            'last_message_id' => $lastMessageId
        ]);

        // 초기 연결 확인 메시지
        $this->sendSseEvent('status', [
            'message' => 'SSE 연결됨',
            'room_id' => $roomId,
            'user_uuid' => $user->uuid,
            'timestamp' => now()->toISOString()
        ]);

        // 스트리밍 루프 (최대 30분)
        while (connection_status() == CONNECTION_NORMAL && (time() - $startTime) < 1800) {
            $currentTime = time();

            // 연결 종료 조건 확인
            if (connection_aborted()) {
                \Log::info('SSE 클라이언트 연결 중단', ['room_id' => $roomId, 'user_uuid' => $user->uuid]);
                break;
            }

            // 최대 실행 시간 초과 확인
            if ($currentTime - $startTime > $this->maxExecutionTime) {
                \Log::info('SSE 최대 실행 시간 초과', ['room_id' => $roomId, 'user_uuid' => $user->uuid]);
                break;
            }

            // 하트비트 전송
            if ($currentTime - $lastHeartbeat >= $this->heartbeatInterval) {
                $this->sendSseEvent('heartbeat', [
                    'timestamp' => $currentTime,
                    'room_id' => $roomId
                ]);
                $lastHeartbeat = $currentTime;
            }

            // 새 메시지 확인
            $this->checkAndSendNewMessages($roomId, $user, $lastMessageId);

            // 10초마다 참여자 상태 확인
            if ($currentTime - $lastParticipantCheck >= 10) {
                $this->checkAndSendParticipants($roomId);
                $lastParticipantCheck = $currentTime;
            }

            // 참여자의 마지막 접속 시간 업데이트
            DB::connection('chat_server')
                ->table('participants')
                ->where('room_id', $roomId)
                ->where('user_uuid', $user->uuid)
                ->update([
                    'last_seen' => now()->toDateTimeString()
                ]);

            // 2초 대기
            sleep(2);
        }

        // 연결 종료 메시지
        $this->sendSseEvent('status', [
            'message' => 'SSE 연결 종료',
            'timestamp' => now()->toISOString()
        ]);

        \Log::info('SSE 스트림 종료', [
            'room_id' => $roomId,
            'user_uuid' => $user->uuid,
            'duration' => time() - $startTime
        ]);
    }

    /**
     * 새 메시지 확인 및 전송
     */
    private function checkAndSendNewMessages($roomId, $user, &$lastMessageId)
    {
        try {
            // 새 메시지 확인
            $newMessages = DB::connection('chat_server')
                ->table('messages')
                ->where('room_id', $roomId)
                ->where('id', '>', $lastMessageId)
                ->where('is_deleted', 0)
                ->orderBy('id', 'asc')
                ->get();

            foreach ($newMessages as $message) {
                // 자신이 보낸 메시지는 제외 (이미 UI에 표시됨)
                if ($message->user_uuid === $user->uuid) {
                    $lastMessageId = $message->id;
                    continue;
                }

                $messageData = [
                    'id' => $message->id,
                    'content' => $message->content,
                    'type' => $message->type ?? 'text',
                    'user_uuid' => $message->user_uuid,
                    'user_name' => $message->user_name,
                    'user_avatar' => $message->user_avatar,
                    'created_at' => $message->created_at,
                    'created_at_human' => Carbon::parse($message->created_at)->format('H:i'),
                    'is_my_message' => false,
                    'reply_to' => null,
                    'files' => []
                ];

                // 파일 첨부 정보
                if ($message->file_path) {
                    $isImage = in_array(strtolower(pathinfo($message->file_name, PATHINFO_EXTENSION)),
                                      ['jpg', 'jpeg', 'png', 'gif', 'webp']);

                    $messageData['files'] = [[
                        'id' => $message->id,
                        'name' => $message->file_name,
                        'path' => $message->file_path,
                        'size' => $message->file_size,
                        'type' => $message->file_type,
                        'is_image' => $isImage,
                        'download_url' => asset('storage/' . $message->file_path),
                        'preview_url' => $isImage ? asset('storage/' . $message->file_path) : null
                    ]];
                }

                $this->sendSseEvent('message', [
                    'type' => 'message',
                    'message' => $messageData
                ]);

                $lastMessageId = $message->id;
            }

        } catch (\Exception $e) {
            \Log::error('SSE 새 메시지 확인 오류', [
                'room_id' => $roomId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 참여자 상태 확인 및 전송
     */
    private function checkAndSendParticipants($roomId)
    {
        try {
            $participants = DB::connection('chat_server')
                ->table('participants')
                ->where('room_id', $roomId)
                ->where('status', 'active')
                ->orderBy('last_seen', 'desc')
                ->get();

            $this->sendSseEvent('participants', [
                'type' => 'participants',
                'participants' => $participants->toArray()
            ]);

        } catch (\Exception $e) {
            \Log::error('SSE 참여자 상태 확인 오류', [
                'room_id' => $roomId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * SSE 이벤트 전송
     */
    private function sendSseEvent($eventType, $data, $timestamp = null)
    {
        $timestamp = $timestamp ?: time();

        // SSE 형식으로 이벤트 데이터 구성
        $eventData = [
            'type' => $eventType,
            'data' => $data,
            'timestamp' => $timestamp
        ];

        // SSE 형식으로 출력
        echo "event: {$eventType}\n";
        echo "data: " . json_encode($eventData) . "\n";
        echo "id: {$timestamp}\n";
        echo "\n";

        // 즉시 플러시
        if (ob_get_level()) {
            ob_flush();
        }
        flush();

        \Log::debug('SSE 이벤트 전송', [
            'event_type' => $eventType,
            'timestamp' => $timestamp
        ]);
    }

    /**
     * 채팅 데이터베이스 경로 생성 (room-{id}.sqlite 형식)
     */
    private function getChatDatabasePath($roomId, Carbon $date)
    {
        $basePath = database_path('chat');
        $year = $date->format('Y');
        $month = $date->format('m');
        $day = $date->format('d');

        return "{$basePath}/{$year}/{$month}/{$day}/room-{$roomId}.sqlite";
    }

    /**
     * 동적 DB 연결 설정
     */
    private function setupDynamicConnection($dbPath)
    {
        config([
            'database.connections.chat_server' => [
                'driver' => 'sqlite',
                'database' => $dbPath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]
        ]);

        DB::purge('chat_server');
    }

    /**
     * SSE 연결 상태 확인
     */
    private function isConnectionActive()
    {
        return !connection_aborted() && !connection_status();
    }
}