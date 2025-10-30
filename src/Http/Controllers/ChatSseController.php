<?php

namespace Jiny\Chat\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatMessage;
use Jiny\Chat\Models\ChatParticipant;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChatSseController
{
    /**
     * SSE 스트림을 시작합니다.
     */
    public function stream(Request $request, $roomId)
    {
        // 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            // 임시 테스트 사용자
            $user = (object) [
                'uuid' => 'test-user-001',
                'name' => 'Test User',
                'email' => 'test@example.com'
            ];
        }

        // 채팅방 존재 확인
        $room = ChatRoom::find($roomId);
        if (!$room) {
            return response()->json(['error' => 'Chat room not found'], 404);
        }

        // 참가자 권한 확인
        $participant = ChatParticipant::where('room_id', $roomId)
            ->where('user_uuid', $user->uuid)
            ->where('status', 'active')
            ->first();

        if (!$participant) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        // 마지막 메시지 ID 가져오기
        $lastMessageId = $request->get('last_message_id', 0);

        // 사용자 연결 상태 추적을 위한 캐시 키
        $connectionKey = "chat_sse_connection:{$roomId}:{$user->uuid}";
        Cache::put($connectionKey, now(), 65); // 65초 TTL (heartbeat보다 약간 길게)

        Log::info('SSE 연결 시작', [
            'room_id' => $roomId,
            'user_uuid' => $user->uuid,
            'last_message_id' => $lastMessageId
        ]);

        return new StreamedResponse(function () use ($roomId, $user, $lastMessageId, $connectionKey) {
            // SSE 헤더 설정
            echo "retry: 5000\n";
            echo "data: " . json_encode(['type' => 'connected', 'message' => 'SSE connection established']) . "\n\n";
            flush();

            $lastCheckedId = $lastMessageId;
            $heartbeatCounter = 0;

            while (true) {
                // 연결 상태 확인 (클라이언트가 끊어졌는지 체크)
                if (connection_aborted()) {
                    Log::info('SSE 연결 종료 감지', [
                        'room_id' => $roomId,
                        'user_uuid' => $user->uuid
                    ]);
                    break;
                }

                try {
                    // 브로드캐스트 메시지 확인 (캐시 기반 실시간 전송)
                    $this->checkBroadcastMessages($roomId, $user);

                    // 새 메시지 확인 (데이터베이스 기반 백업)
                    $newMessages = ChatMessage::where('room_id', $roomId)
                        ->where('id', '>', $lastCheckedId)
                        ->where('is_deleted', false)
                        ->orderBy('created_at', 'asc')
                        ->limit(50) // 한 번에 최대 50개 메시지
                        ->get();

                    if ($newMessages->count() > 0) {
                        foreach ($newMessages as $message) {
                            // 자신의 메시지는 스킵 (이미 화면에 표시됨)
                            if ($message->sender_uuid === $user->uuid) {
                                $lastCheckedId = $message->id;
                                continue;
                            }

                            // 메시지 포맷팅
                            $formattedMessage = $this->formatMessage($message, $roomId, $user);

                            // SSE 이벤트 전송
                            echo "event: new_message\n";
                            echo "data: " . json_encode([
                                'type' => 'new_message',
                                'message' => $formattedMessage,
                                'room_id' => $roomId
                            ]) . "\n\n";
                            flush();

                            $lastCheckedId = $message->id;
                        }

                        Log::info('SSE 새 메시지 전송', [
                            'room_id' => $roomId,
                            'user_uuid' => $user->uuid,
                            'message_count' => $newMessages->count(),
                            'last_message_id' => $lastCheckedId
                        ]);
                    }

                    // 참가자 상태 업데이트 확인 (타이핑, 온라인 상태 등)
                    $this->checkParticipantUpdates($roomId, $user);

                    // Heartbeat (30초마다)
                    $heartbeatCounter++;
                    if ($heartbeatCounter >= 30) { // 30 * 1초 = 30초
                        echo "event: heartbeat\n";
                        echo "data: " . json_encode([
                            'type' => 'heartbeat',
                            'timestamp' => now()->toISOString(),
                            'active_connections' => $this->getActiveConnections($roomId)
                        ]) . "\n\n";
                        flush();

                        $heartbeatCounter = 0;

                        // 연결 상태 갱신
                        Cache::put($connectionKey, now(), 65);
                    }

                    // 1초 대기
                    sleep(1);

                } catch (\Exception $e) {
                    Log::error('SSE 스트림 오류', [
                        'room_id' => $roomId,
                        'user_uuid' => $user->uuid,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    // 오류 이벤트 전송
                    echo "event: error\n";
                    echo "data: " . json_encode([
                        'type' => 'error',
                        'message' => 'Server error occurred'
                    ]) . "\n\n";
                    flush();

                    sleep(5); // 오류 시 5초 대기
                }
            }

            // 연결 종료 시 캐시에서 제거
            Cache::forget($connectionKey);

            Log::info('SSE 연결 종료', [
                'room_id' => $roomId,
                'user_uuid' => $user->uuid
            ]);

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Cache-Control',
            'X-Accel-Buffering' => 'no', // Nginx 버퍼링 비활성화
        ]);
    }

    /**
     * 메시지 포맷팅
     */
    private function formatMessage($message, $roomId, $currentUser)
    {
        // 발신자 정보 조회
        $participant = ChatParticipant::where('room_id', $roomId)
            ->where('user_uuid', $message->sender_uuid)
            ->first();

        // 시간 포맷팅
        $createdAt = $message->created_at;
        $now = now();

        $todayDate = $now->format('Y-m-d');
        $yesterdayDate = $now->copy()->subDay()->format('Y-m-d');
        $messageDate = $createdAt->format('Y-m-d');

        if ($messageDate === $todayDate) {
            $timeDisplay = $createdAt->format('H:i');
        } elseif ($messageDate === $yesterdayDate) {
            $timeDisplay = '어제 ' . $createdAt->format('H:i');
        } elseif ($createdAt->year === $now->year) {
            $timeDisplay = $createdAt->format('n.j H:i');
        } else {
            $timeDisplay = $createdAt->format('Y.n.j H:i');
        }

        $formattedMessage = [
            'id' => $message->id,
            'content' => $message->content,
            'type' => $message->type ?? 'text',
            'sender_uuid' => $message->sender_uuid,
            'sender_name' => $participant ? $participant->name : $message->sender_uuid,
            'sender_avatar' => $participant ? $participant->avatar : null,
            'created_at' => $timeDisplay,
            'created_at_full' => $message->created_at->format('Y-m-d H:i:s'),
            'is_mine' => $message->sender_uuid === $currentUser->uuid,
            'reply_to_message_id' => $message->reply_to_message_id,
        ];

        // 답장 메시지인 경우 원본 메시지 정보 추가
        if ($message->reply_to_message_id) {
            $originalMessage = ChatMessage::find($message->reply_to_message_id);
            if ($originalMessage) {
                $originalParticipant = ChatParticipant::where('room_id', $roomId)
                    ->where('user_uuid', $originalMessage->sender_uuid)
                    ->first();

                $formattedMessage['reply_to'] = [
                    'id' => $originalMessage->id,
                    'content' => $originalMessage->content,
                    'sender_name' => $originalParticipant ? $originalParticipant->name : $originalMessage->sender_uuid,
                    'sender_uuid' => $originalMessage->sender_uuid,
                ];
            }
        }

        // 파일 메시지인 경우 파일 정보 추가
        if (in_array($message->type, ['image', 'document', 'video', 'audio'])) {
            $chatFile = \Jiny\Chat\Models\ChatFile::where('message_id', $message->id)
                ->where('is_deleted', false)
                ->first();

            if ($chatFile) {
                $formattedMessage['file'] = [
                    'uuid' => $chatFile->uuid,
                    'original_name' => $chatFile->original_name,
                    'file_size' => $chatFile->file_size_human,
                    'file_type' => $chatFile->file_type,
                    'icon_class' => $chatFile->icon_class,
                    'download_url' => $chatFile->download_url,
                ];
            }
        }

        return $formattedMessage;
    }

    /**
     * 브로드캐스트 메시지 확인 (캐시 기반 실시간 전송)
     */
    private function checkBroadcastMessages($roomId, $user)
    {
        $broadcastKey = "chat_broadcast:{$roomId}";
        $userLastCheckKey = "chat_sse_last_check:{$roomId}:{$user->uuid}";

        $broadcasts = Cache::get($broadcastKey, []);
        $lastCheckTime = Cache::get($userLastCheckKey, now()->subMinutes(5));

        foreach ($broadcasts as $broadcast) {
            $broadcastTime = \Carbon\Carbon::parse($broadcast['timestamp']);

            // 마지막 확인 시간 이후의 브로드캐스트만 처리
            if ($broadcastTime->greaterThan($lastCheckTime)) {
                // 자신의 메시지는 스킵
                if ($broadcast['sender_uuid'] === $user->uuid) {
                    continue;
                }

                // SSE 이벤트 전송
                echo "event: new_message\n";
                echo "data: " . json_encode($broadcast) . "\n\n";
                flush();

                Log::info('SSE 브로드캐스트 메시지 전송', [
                    'room_id' => $roomId,
                    'user_uuid' => $user->uuid,
                    'message_id' => $broadcast['message']['id'] ?? 'unknown',
                    'broadcast_time' => $broadcast['timestamp']
                ]);
            }
        }

        // 마지막 확인 시간 업데이트
        Cache::put($userLastCheckKey, now(), 300); // 5분 TTL
    }

    /**
     * 참가자 상태 업데이트 확인
     */
    private function checkParticipantUpdates($roomId, $user)
    {
        // 타이핑 상태 확인
        $typingUsers = Cache::get("chat_typing:{$roomId}", []);
        if (!empty($typingUsers)) {
            // 현재 사용자 제외한 타이핑 중인 사용자들
            $otherTypingUsers = array_filter($typingUsers, function($userUuid) use ($user) {
                return $userUuid !== $user->uuid;
            });

            if (!empty($otherTypingUsers)) {
                echo "event: typing_update\n";
                echo "data: " . json_encode([
                    'type' => 'typing_update',
                    'typing_users' => $otherTypingUsers,
                    'room_id' => $roomId
                ]) . "\n\n";
                flush();
            }
        }

        // 온라인 사용자 상태 확인 (선택적)
        $onlineUsers = $this->getActiveConnections($roomId);
        if (count($onlineUsers) > 0) {
            // 온라인 상태 이벤트는 필요시에만 전송
            // echo "event: online_users\n";
            // echo "data: " . json_encode(['type' => 'online_users', 'users' => $onlineUsers]) . "\n\n";
            // flush();
        }
    }

    /**
     * 활성 연결 수 확인
     */
    private function getActiveConnections($roomId)
    {
        $pattern = "chat_sse_connection:{$roomId}:*";
        $connections = [];

        try {
            // Redis가 사용 가능한 경우
            if (config('cache.default') === 'redis') {
                $keys = \Illuminate\Support\Facades\Redis::keys($pattern);
                $connections = count($keys);
            } else {
                // 파일 캐시 등 다른 드라이버의 경우 근사치
                $connections = 1; // 현재 연결만 계산
            }
        } catch (\Exception $e) {
            Log::warning('활성 연결 수 확인 실패', ['error' => $e->getMessage()]);
            $connections = 1;
        }

        return $connections;
    }

    /**
     * 사용자 타이핑 상태 업데이트
     */
    public function updateTyping(Request $request, $roomId)
    {
        $user = \JwtAuth::user($request);
        if (!$user) {
            $user = (object) ['uuid' => 'test-user-001', 'name' => 'Test User'];
        }

        $isTyping = $request->boolean('is_typing', false);
        $cacheKey = "chat_typing:{$roomId}";

        $typingUsers = Cache::get($cacheKey, []);

        if ($isTyping) {
            $typingUsers[$user->uuid] = [
                'user_uuid' => $user->uuid,
                'user_name' => $user->name,
                'started_at' => now()->toISOString()
            ];
            Cache::put($cacheKey, $typingUsers, 10); // 10초 TTL
        } else {
            unset($typingUsers[$user->uuid]);
            Cache::put($cacheKey, $typingUsers, 10);
        }

        return response()->json(['success' => true]);
    }

    /**
     * SSE 연결 상태 확인
     */
    public function status(Request $request, $roomId)
    {
        $user = \JwtAuth::user($request);
        if (!$user) {
            $user = (object) ['uuid' => 'test-user-001'];
        }

        $connectionKey = "chat_sse_connection:{$roomId}:{$user->uuid}";
        $isConnected = Cache::has($connectionKey);
        $activeConnections = $this->getActiveConnections($roomId);

        return response()->json([
            'connected' => $isConnected,
            'active_connections' => $activeConnections,
            'room_id' => $roomId,
            'user_uuid' => $user->uuid
        ]);
    }
}