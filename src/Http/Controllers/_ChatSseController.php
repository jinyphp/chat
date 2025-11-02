<?php

namespace Jiny\Chat\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatMessage;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Models\ChatRoomMessage;
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
            // 테스트 환경에서는 임시로 권한 체크를 건너뜀
            if ($user->uuid === 'test-user-001') {
                Log::info('테스트 사용자 SSE 연결 허용', ['room_id' => $roomId, 'user_uuid' => $user->uuid]);
                // 임시 참가자 객체 생성 (DB 저장 없이)
                $participant = (object) [
                    'room_id' => $roomId,
                    'user_uuid' => $user->uuid,
                    'role' => 'member',
                    'status' => 'active'
                ];
            } else {
                return response()->json(['error' => 'Access denied'], 403);
            }
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

            // 초기 연결 확인 메시지 (안전한 데이터로)
            $connectionData = [
                'type' => 'connected',
                'message' => 'SSE connection established',
                'room_id' => (int)$roomId,
                'user_uuid' => $user->uuid ?? 'unknown',
                'timestamp' => now()->toISOString()
            ];

            echo "data: " . json_encode($connectionData) . "\n\n";
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
                    try {
                        $this->checkBroadcastMessages($roomId, $user);
                    } catch (\Exception $e) {
                        Log::warning('브로드캐스트 메시지 확인 실패', ['error' => $e->getMessage(), 'room_id' => $roomId]);
                    }

                    // 새 메시지 확인 (독립 데이터베이스 또는 기존 방식)
                    $room = ChatRoom::find($roomId);
                    $newMessages = collect();

                    if ($room && $room->code) {
                        try {
                            // 독립 데이터베이스 사용 (새로운 경로 구조 지원)
                            $newMessages = ChatRoomMessage::forRoom($room->code, $room->id, $room->created_at)
                                ->where('id', '>', $lastCheckedId)
                                ->where('is_deleted', false)
                                ->orderBy('created_at', 'asc')
                                ->limit(50)
                                ->get();
                        } catch (\Exception $e) {
                            Log::error('독립 DB 메시지 조회 실패', ['error' => $e->getMessage(), 'room_id' => $roomId]);
                            // 기존 방식으로 fallback
                            $newMessages = ChatMessage::where('room_id', $roomId)
                                ->where('id', '>', $lastCheckedId)
                                ->where('is_deleted', false)
                                ->orderBy('created_at', 'asc')
                                ->limit(50)
                                ->get();
                        }
                    } else {
                        // 기존 방식 (하위 호환성)
                        $newMessages = ChatMessage::where('room_id', $roomId)
                            ->where('id', '>', $lastCheckedId)
                            ->where('is_deleted', false)
                            ->orderBy('created_at', 'asc')
                            ->limit(50)
                            ->get();
                    }

                    if ($newMessages->count() > 0) {
                        foreach ($newMessages as $message) {
                            // 자신의 메시지는 스킵 (이미 화면에 표시됨)
                            if ($message->sender_uuid === $user->uuid) {
                                $lastCheckedId = $message->id;
                                continue;
                            }

                            // 메시지 포맷팅
                            $formattedMessage = $this->formatMessage($message, $roomId, $user, $room);

                            // SSE 이벤트 전송 (데이터 검증 후)
                            $messageData = [
                                'type' => 'new_message',
                                'message' => $formattedMessage,
                                'room_id' => (int)$roomId
                            ];

                            // 데이터 무결성 확인
                            $safeMessageData = $this->sanitizeBroadcastData($messageData);

                            echo "event: new_message\n";
                            echo "data: " . json_encode($safeMessageData) . "\n\n";
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
                    try {
                        $this->checkParticipantUpdates($roomId, $user);
                    } catch (\Exception $e) {
                        Log::warning('참가자 상태 업데이트 실패', ['error' => $e->getMessage(), 'room_id' => $roomId]);
                    }

                    // Heartbeat (30초마다)
                    $heartbeatCounter++;
                    if ($heartbeatCounter >= 30) { // 30 * 1초 = 30초
                        $heartbeatData = [
                            'type' => 'heartbeat',
                            'timestamp' => now()->toISOString(),
                            'active_connections' => (int)$this->getActiveConnections($roomId),
                            'room_id' => (int)$roomId
                        ];

                        // JSON 인코딩 검증
                        $jsonData = json_encode($heartbeatData);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            echo "event: heartbeat\n";
                            echo "data: " . $jsonData . "\n\n";
                            flush();
                        } else {
                            Log::error('하트비트 JSON 인코딩 실패', [
                                'error' => json_last_error_msg(),
                                'data' => $heartbeatData
                            ]);
                        }

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
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    // 에러 발생 시 중단하지 않고 continue
                    sleep(5); // 오류 시 5초 대기
                    continue; // 에러 이벤트 전송 없이 계속 진행
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
    private function formatMessage($message, $roomId, $currentUser, $room = null)
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
            'id' => (int)$message->id,
            'content' => $message->content ?? '',
            'type' => $message->type ?? 'text',
            'sender_uuid' => $message->sender_uuid ?? '',
            'sender_name' => $participant && $participant->name ? $participant->name : ($message->sender_name ?? $message->sender_uuid ?? 'Unknown'),
            'sender_avatar' => $participant && $participant->avatar ? $participant->avatar : null,
            'created_at' => $timeDisplay,
            'created_at_full' => $message->created_at ? $message->created_at->format('Y-m-d H:i:s') : '',
            'is_mine' => ($message->sender_uuid ?? '') === ($currentUser->uuid ?? ''),
            'reply_to_message_id' => $message->reply_to_message_id ? (int)$message->reply_to_message_id : null,
        ];

        // 답장 메시지인 경우 원본 메시지 정보 추가
        if ($message->reply_to_message_id) {
            $originalMessage = null;

            if ($room && $room->code) {
                // 독립 데이터베이스에서 조회
                $originalMessage = ChatRoomMessage::forRoom($room->code)->find($message->reply_to_message_id);
            } else {
                // 기존 방식
                $originalMessage = ChatMessage::find($message->reply_to_message_id);
            }

            if ($originalMessage) {
                $originalParticipant = ChatParticipant::where('room_id', $roomId)
                    ->where('user_uuid', $originalMessage->sender_uuid)
                    ->first();

                $formattedMessage['reply_to'] = [
                    'id' => (int)$originalMessage->id,
                    'content' => $originalMessage->content ?? '',
                    'sender_name' => $originalParticipant && $originalParticipant->name ? $originalParticipant->name : ($originalMessage->sender_name ?? $originalMessage->sender_uuid ?? 'Unknown'),
                    'sender_uuid' => $originalMessage->sender_uuid ?? '',
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

        foreach ($broadcasts as $broadcastIndex => $broadcast) {
            try {
                // 브로드캐스트 데이터 유효성 검사
                if (!is_array($broadcast) || !isset($broadcast['timestamp']) || !isset($broadcast['sender_uuid'])) {
                    Log::warning('잘못된 브로드캐스트 데이터', ['broadcast' => $broadcast, 'index' => $broadcastIndex]);
                    continue;
                }

                // 타임스탬프 파싱
                try {
                    $broadcastTime = \Carbon\Carbon::parse($broadcast['timestamp']);
                } catch (\Exception $e) {
                    Log::warning('브로드캐스트 타임스탬프 파싱 실패', ['timestamp' => $broadcast['timestamp'], 'error' => $e->getMessage()]);
                    continue;
                }

                // 마지막 확인 시간 이후의 브로드캐스트만 처리
                if ($broadcastTime->greaterThan($lastCheckTime)) {
                    // 자신의 메시지는 스킵
                    if ($broadcast['sender_uuid'] === $user->uuid) {
                        continue;
                    }

                    // SSE 이벤트 전송 (안전한 JSON 인코딩)
                    $safeData = $this->sanitizeBroadcastData($broadcast);

                    // JSON 인코딩 검증
                    $jsonData = json_encode($safeData);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::error('브로드캐스트 JSON 인코딩 실패', [
                            'error' => json_last_error_msg(),
                            'data' => $safeData
                        ]);
                        continue;
                    }

                    echo "event: new_message\n";
                    echo "data: " . $jsonData . "\n\n";
                    flush();

                    Log::info('SSE 브로드캐스트 메시지 전송', [
                        'room_id' => $roomId,
                        'user_uuid' => $user->uuid,
                        'message_id' => $broadcast['message']['id'] ?? 'unknown',
                        'broadcast_time' => $broadcast['timestamp']
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('브로드캐스트 처리 중 오류', [
                    'room_id' => $roomId,
                    'broadcast_index' => $broadcastIndex,
                    'error' => $e->getMessage(),
                    'broadcast' => $broadcast
                ]);
                continue;
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
        try {
            // 타이핑 상태 확인
            $typingUsers = Cache::get("chat_typing:{$roomId}", []);

            // 타이핑 사용자 데이터 유효성 검사 및 정리
            if (is_array($typingUsers) && !empty($typingUsers)) {
                // 유효한 타이핑 사용자만 필터링
                $validTypingUsers = [];
                foreach ($typingUsers as $key => $userData) {
                    // 배열 형태의 사용자 데이터인 경우
                    if (is_array($userData) && isset($userData['user_uuid'])) {
                        $userUuid = $userData['user_uuid'];
                        $userName = $userData['user_name'] ?? 'Unknown';

                        // 현재 사용자가 아니고 유효한 UUID인 경우
                        if ($userUuid !== $user->uuid &&
                            !empty($userUuid) &&
                            $userUuid !== 'undefined' &&
                            $userUuid !== null) {

                            $validTypingUsers[] = [
                                'user_uuid' => $userUuid,
                                'user_name' => $userName,
                                'started_at' => $userData['started_at'] ?? now()->toISOString()
                            ];
                        }
                    }
                    // 단순 문자열 형태인 경우 (이전 버전 호환성)
                    elseif (is_string($userData) &&
                            $userData !== $user->uuid &&
                            !empty($userData) &&
                            $userData !== 'undefined') {

                        $validTypingUsers[] = [
                            'user_uuid' => $userData,
                            'user_name' => 'User',
                            'started_at' => now()->toISOString()
                        ];
                    }
                }

                if (!empty($validTypingUsers)) {
                    $typingData = [
                        'type' => 'typing_update',
                        'typing_users' => $validTypingUsers,
                        'room_id' => (int)$roomId
                    ];

                    // JSON 인코딩 검증
                    $jsonData = json_encode($typingData);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::error('타이핑 데이터 JSON 인코딩 실패', [
                            'error' => json_last_error_msg(),
                            'data' => $typingData
                        ]);
                        return;
                    }

                    echo "event: typing_update\n";
                    echo "data: " . $jsonData . "\n\n";
                    flush();
                }
            }
        } catch (\Exception $e) {
            Log::warning('참가자 상태 업데이트 확인 실패', [
                'room_id' => $roomId,
                'user_uuid' => $user->uuid,
                'error' => $e->getMessage()
            ]);
        }

        // 온라인 사용자 상태 확인 (선택적)
        $onlineUsers = $this->getActiveConnections($roomId);
        if ($onlineUsers > 0) {
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
                $connections = is_array($keys) ? count($keys) : (int)$keys;
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
     * 브로드캐스트 데이터 정리 (undefined 값 제거)
     */
    private function sanitizeBroadcastData($data)
    {
        if (!is_array($data)) {
            // 배열이 아닌 경우 타입에 따라 처리
            if ($data === null || $data === 'undefined' || $data === '') {
                return null;
            }
            return $data;
        }

        // undefined나 null 값들을 안전한 값으로 변경
        $sanitized = [];
        foreach ($data as $key => $value) {
            // 키 자체가 유효하지 않은 경우 스킵
            if ($key === null || $key === 'undefined' || $key === '') {
                continue;
            }

            // 값이 null, undefined, 빈 문자열인 경우
            if ($value === null || $value === 'undefined') {
                // 특정 필드들은 기본값 제공
                if (in_array($key, ['sender_name', 'content', 'type'])) {
                    $sanitized[$key] = $key === 'type' ? 'text' : ($key === 'sender_name' ? 'Unknown' : '');
                }
                // 나머지는 건너뛰기
                continue;
            }
            // 배열인 경우 재귀 처리
            elseif (is_array($value)) {
                $nestedSanitized = $this->sanitizeBroadcastData($value);
                // 빈 배열이 아닌 경우만 추가
                if (!empty($nestedSanitized) || $nestedSanitized === []) {
                    $sanitized[$key] = $nestedSanitized;
                }
            }
            // 문자열인 경우 undefined 체크
            elseif (is_string($value) && trim($value) === 'undefined') {
                continue;
            }
            // 유효한 값인 경우 그대로 추가
            else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
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

        // 사용자 데이터 유효성 검사
        $userUuid = $user->uuid ?? 'unknown';
        $userName = $user->name ?? 'Unknown User';

        // undefined 값들 필터링
        if ($userUuid === 'undefined' || $userUuid === null || empty($userUuid)) {
            return response()->json(['error' => 'Invalid user data'], 400);
        }

        $isTyping = $request->boolean('is_typing', false);
        $cacheKey = "chat_typing:{$roomId}";

        $typingUsers = Cache::get($cacheKey, []);

        if ($isTyping) {
            $typingUsers[$userUuid] = [
                'user_uuid' => $userUuid,
                'user_name' => $userName,
                'started_at' => now()->toISOString()
            ];
            Cache::put($cacheKey, $typingUsers, 10); // 10초 TTL

            Log::info('타이핑 상태 시작', [
                'room_id' => $roomId,
                'user_uuid' => $userUuid,
                'user_name' => $userName
            ]);
        } else {
            unset($typingUsers[$userUuid]);
            Cache::put($cacheKey, $typingUsers, 10);

            Log::info('타이핑 상태 종료', [
                'room_id' => $roomId,
                'user_uuid' => $userUuid
            ]);
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