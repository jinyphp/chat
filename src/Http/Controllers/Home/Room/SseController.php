<?php

namespace Jiny\Chat\Http\Controllers\Home\Room;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Http\JsonResponse;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Events\MessageSent;
use Jiny\Chat\Events\UserTyping;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SseController - Server-Sent Events 기반 실시간 채팅
 *
 * 주요 기능:
 * 1. SSE 스트림 연결 관리
 * 2. 실시간 메시지 브로드캐스팅
 * 3. 타이핑 상태 알림
 * 4. 사용자 연결 상태 관리
 * 5. Heartbeat를 통한 연결 유지
 */
class SseController extends Controller
{
    /**
     * 룸별 이벤트 큐 (메모리 기반)
     */
    private static $eventQueues = [];

    /**
     * 룸별 활성 연결 카운터
     */
    private static $activeConnections = [];
    /**
     * SSE 연결 생성 및 스트림 시작
     */
    public function stream(Request $request, $roomId)
    {
        // JWT 인증된 사용자 정보 가져오기
        $authUser = auth()->user();
        if (!$authUser) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 401);
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
                return response()->json([
                    'error' => 'Forbidden - Not a participant'
                ], 403);
            }

            // 3. 참여자 활동 시간 업데이트
            $participant->update([
                'last_seen_at' => now()
            ]);

            Log::info('SSE 연결 시작', [
                'room_id' => $roomId,
                'user_uuid' => $user->uuid,
                'user_name' => $user->name
            ]);

            // 4. 이벤트 리스너 등록
            $this->registerEventListeners($room->id);

            // 5. SSE 스트림 응답 생성
            $response = new StreamedResponse(function () use ($room, $user, $participant) {
                $this->handleSseStream($room, $user, $participant);
            });

            // 6. SSE 헤더 설정
            $response->headers->set('Content-Type', 'text/event-stream');
            $response->headers->set('Cache-Control', 'no-cache');
            $response->headers->set('Connection', 'keep-alive');
            $response->headers->set('X-Accel-Buffering', 'no'); // Nginx 버퍼링 방지

            return $response;

        } catch (\Exception $e) {
            Log::error('SSE 연결 실패', [
                'room_id' => $roomId,
                'user_uuid' => $user->uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'SSE 연결 실패: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * SSE 스트림 처리
     */
    private function handleSseStream($room, $user, $participant)
    {
        // 초기 연결 확인 메시지
        echo "event: connected\n";
        echo "data: " . json_encode([
            'type' => 'connected',
            'room_id' => $room->id,
            'user_uuid' => $user->uuid,
            'timestamp' => now()->toISOString()
        ], JSON_UNESCAPED_UNICODE) . "\n\n";

        if (ob_get_level()) {
            ob_end_flush();
        }
        flush();

        $lastHeartbeat = time();
        $heartbeatInterval = 30; // 30초마다 heartbeat
        $connectionTimeout = 300; // 5분 연결 타임아웃

        $startTime = time();

        // SSE 연결 시간 제한 (최대 5분으로 제한)
        $maxConnectionTime = 300;

        while (true) {
            // 연결 시간 체크 (5분 제한)
            if (time() - $startTime > $maxConnectionTime) {
                Log::info('SSE 연결 시간 제한 (5분)', [
                    'room_id' => $room->id,
                    'user_uuid' => $user->uuid
                ]);
                break;
            }

            // 클라이언트 연결 상태 확인
            if (connection_aborted()) {
                Log::info('SSE 클라이언트 연결 종료', [
                    'room_id' => $room->id,
                    'user_uuid' => $user->uuid
                ]);
                break;
            }

            // 큐된 이벤트 처리
            $this->processQueuedEvents($room->id);

            // Heartbeat 전송 (30초마다로 변경)
            if (time() - $lastHeartbeat >= 30) {
                echo "event: heartbeat\n";
                echo "data: " . json_encode([
                    'type' => 'heartbeat',
                    'timestamp' => now()->toISOString()
                ], JSON_UNESCAPED_UNICODE) . "\n\n";

                if (ob_get_level()) {
                    ob_end_flush();
                }
                flush();

                $lastHeartbeat = time();
            }

            // 짧은 대기 (CPU 사용량 최적화)
            usleep(1000000); // 1초 대기
        }

        // 연결 종료 시 참여자 상태 업데이트
        try {
            $participant->update(['last_seen_at' => now()]);
        } catch (\Exception $e) {
            Log::warning('연결 종료 시 참여자 상태 업데이트 실패', [
                'error' => $e->getMessage()
            ]);
        }

        // 이벤트 리스너 정리
        $this->cleanupEventListeners($room->id);
    }

    /**
     * 타이핑 상태 업데이트
     */
    public function typing(Request $request, $roomId)
    {
        // JWT 인증된 사용자 정보 가져오기
        $authUser = auth()->user();
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => '로그인이 필요합니다.'
            ], 401);
        }

        $user = (object) [
            'uuid' => $authUser->uuid ?? 'user-' . $authUser->id,
            'name' => $authUser->name ?? 'Unknown User',
        ];

        $validated = $request->validate([
            'is_typing' => 'required|boolean'
        ]);

        try {
            // 1. 채팅방 조회
            $room = ChatRoom::findOrFail($roomId);

            // 2. 사용자 참여 여부 확인
            $participant = ChatParticipant::where('room_id', $room->id)
                ->where('user_uuid', $user->uuid)
                ->where('status', 'active')
                ->first();

            if (!$participant) {
                return response()->json([
                    'success' => false,
                    'message' => '이 채팅방에 참여하지 않았습니다.'
                ], 403);
            }

            // 3. 타이핑 이벤트 브로드캐스트
            $action = $validated['is_typing'] ? 'start' : 'stop';

            event(new UserTyping(
                $room->id,
                $user->uuid,
                $user->name,
                $action
            ));

            Log::info('타이핑 상태 업데이트', [
                'room_id' => $roomId,
                'user_uuid' => $user->uuid,
                'action' => $action
            ]);

            return response()->json([
                'success' => true,
                'message' => '타이핑 상태가 업데이트되었습니다.'
            ]);

        } catch (\Exception $e) {
            Log::error('타이핑 상태 업데이트 실패', [
                'room_id' => $roomId,
                'user_uuid' => $user->uuid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '타이핑 상태 업데이트에 실패했습니다.'
            ], 500);
        }
    }

    /**
     * 참여자 목록 조회
     */
    public function participants(Request $request, $roomId)
    {
        // JWT 인증된 사용자 정보 가져오기
        $authUser = auth()->user();
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => '로그인이 필요합니다.'
            ], 401);
        }

        $user = (object) [
            'uuid' => $authUser->uuid ?? 'user-' . $authUser->id,
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
                return response()->json([
                    'success' => false,
                    'message' => '이 채팅방에 참여하지 않았습니다.'
                ], 403);
            }

            // 3. 활성 참여자 목록 조회
            $participants = ChatParticipant::where('room_id', $room->id)
                ->where('status', 'active')
                ->orderBy('last_seen_at', 'desc')
                ->get()
                ->map(function ($p) {
                    return [
                        'user_uuid' => $p->user_uuid,
                        'name' => $p->name,
                        'role' => $p->role,
                        'last_seen_at' => $p->last_seen_at,
                        'is_online' => $p->last_seen_at && $p->last_seen_at->diffInMinutes(now()) < 5
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'participants' => $participants,
                    'total_count' => count($participants),
                    'online_count' => count($participants->where('is_online', true))
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('참여자 목록 조회 실패', [
                'room_id' => $roomId,
                'user_uuid' => $user->uuid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '참여자 목록을 불러올 수 없습니다.'
            ], 500);
        }
    }

    /**
     * 룸별 이벤트 리스너 등록
     */
    private function registerEventListeners($roomId)
    {
        // 이미 등록된 리스너가 있으면 중복 등록 방지
        if (isset(self::$activeConnections[$roomId])) {
            self::$activeConnections[$roomId]++;
            return;
        }

        self::$activeConnections[$roomId] = 1;
        self::$eventQueues[$roomId] = [];

        // MessageSent 이벤트 리스너 등록
        Event::listen(MessageSent::class, function ($event) use ($roomId) {
            if ($event->room->id == $roomId) {
                self::$eventQueues[$roomId][] = [
                    'type' => 'message.sent',
                    'data' => $event->toSseFormat(),
                    'timestamp' => now()->toISOString()
                ];

                Log::info('SSE 이벤트 큐에 추가 (MessageSent)', [
                    'room_id' => $roomId,
                    'message_id' => $event->message['id'] ?? 'unknown',
                    'queue_size' => count(self::$eventQueues[$roomId])
                ]);
            }
        });

        // UserTyping 이벤트 리스너 등록
        Event::listen(UserTyping::class, function ($event) use ($roomId) {
            if ($event->roomId == $roomId) {
                self::$eventQueues[$roomId][] = [
                    'type' => 'user.typing',
                    'data' => $event->toSseFormat(),
                    'timestamp' => now()->toISOString()
                ];

                Log::info('SSE 이벤트 큐에 추가 (UserTyping)', [
                    'room_id' => $roomId,
                    'user_uuid' => $event->userUuid,
                    'action' => $event->action
                ]);
            }
        });

        Log::info('SSE 이벤트 리스너 등록 완료', [
            'room_id' => $roomId,
            'active_connections' => self::$activeConnections[$roomId]
        ]);
    }

    /**
     * 큐된 이벤트 처리 및 전송
     */
    private function processQueuedEvents($roomId)
    {
        if (!isset(self::$eventQueues[$roomId]) || empty(self::$eventQueues[$roomId])) {
            return;
        }

        $events = self::$eventQueues[$roomId];
        self::$eventQueues[$roomId] = []; // 큐 초기화

        foreach ($events as $event) {
            echo $event['data'];

            if (ob_get_level()) {
                ob_end_flush();
            }
            flush();

            Log::info('SSE 이벤트 전송 완료', [
                'room_id' => $roomId,
                'event_type' => $event['type'],
                'timestamp' => $event['timestamp']
            ]);
        }
    }

    /**
     * 연결 종료 시 리스너 정리
     */
    private function cleanupEventListeners($roomId)
    {
        if (isset(self::$activeConnections[$roomId])) {
            self::$activeConnections[$roomId]--;

            if (self::$activeConnections[$roomId] <= 0) {
                unset(self::$activeConnections[$roomId]);
                unset(self::$eventQueues[$roomId]);

                Log::info('SSE 이벤트 리스너 정리 완료', [
                    'room_id' => $roomId
                ]);
            }
        }
    }
}