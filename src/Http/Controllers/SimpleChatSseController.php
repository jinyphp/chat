<?php

namespace Jiny\Chat\Http\Controllers;

use Illuminate\Http\Request;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatMessage;
use Jiny\Chat\Models\ChatParticipant;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;

/**
 * 간단하고 안정적인 SSE 컨트롤러
 * 복잡한 기능을 제거하고 핵심 기능만 구현
 */
class SimpleChatSseController
{
    /**
     * SSE 스트림을 시작합니다.
     */
    public function stream(Request $request, $roomId)
    {
        // 간단한 사용자 인증 (테스트용)
        $user = (object) [
            'uuid' => 'test-user-001',
            'name' => 'Test User',
            'email' => 'test@example.com'
        ];

        // 채팅방 존재 확인
        $room = ChatRoom::find($roomId);
        if (!$room) {
            return response()->json(['error' => 'Chat room not found'], 404);
        }

        Log::info('Simple SSE 연결 시작', [
            'room_id' => $roomId,
            'user_uuid' => $user->uuid
        ]);

        return new StreamedResponse(function () use ($roomId, $user) {
            // SSE 헤더 설정
            echo "retry: 5000\n";
            echo "data: " . json_encode([
                'type' => 'connected',
                'message' => 'SSE connection established',
                'room_id' => (int)$roomId,
                'timestamp' => now()->toISOString()
            ]) . "\n\n";
            flush();

            $lastMessageId = 0;
            $loopCount = 0;

            while (true) {
                // 연결 상태 확인
                if (connection_aborted()) {
                    Log::info('Simple SSE 연결 종료', ['room_id' => $roomId, 'user_uuid' => $user->uuid]);
                    break;
                }

                try {
                    // 새 메시지 확인 (간단한 방식)
                    $newMessages = ChatMessage::where('room_id', $roomId)
                        ->where('id', '>', $lastMessageId)
                        ->where('is_deleted', false)
                        ->orderBy('created_at', 'asc')
                        ->limit(10)
                        ->get();

                    if ($newMessages->count() > 0) {
                        foreach ($newMessages as $message) {
                            // 자신의 메시지는 스킵
                            if ($message->sender_uuid === $user->uuid) {
                                $lastMessageId = $message->id;
                                continue;
                            }

                            // 간단한 메시지 포맷팅
                            $formattedMessage = [
                                'id' => (int)$message->id,
                                'content' => $message->content ?? '',
                                'type' => $message->type ?? 'text',
                                'sender_uuid' => $message->sender_uuid ?? '',
                                'sender_name' => $message->sender_name ?? 'Unknown',
                                'created_at' => $message->created_at ? $message->created_at->format('H:i') : '',
                                'is_mine' => false
                            ];

                            // SSE 이벤트 전송
                            echo "event: new_message\n";
                            echo "data: " . json_encode([
                                'type' => 'new_message',
                                'message' => $formattedMessage,
                                'room_id' => (int)$roomId
                            ]) . "\n\n";
                            flush();

                            $lastMessageId = $message->id;
                        }

                        Log::info('Simple SSE 새 메시지 전송', [
                            'room_id' => $roomId,
                            'message_count' => $newMessages->count(),
                            'last_message_id' => $lastMessageId
                        ]);
                    }

                    // Heartbeat (30초마다)
                    $loopCount++;
                    if ($loopCount >= 30) {
                        echo "event: heartbeat\n";
                        echo "data: " . json_encode([
                            'type' => 'heartbeat',
                            'timestamp' => now()->toISOString(),
                            'room_id' => (int)$roomId
                        ]) . "\n\n";
                        flush();

                        $loopCount = 0;
                    }

                    // 1초 대기
                    sleep(1);

                } catch (\Exception $e) {
                    Log::error('Simple SSE 오류', [
                        'room_id' => $roomId,
                        'error' => $e->getMessage()
                    ]);

                    // 오류 시 5초 대기 후 계속
                    sleep(5);
                }
            }

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Cache-Control',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * SSE 연결 상태 확인
     */
    public function status(Request $request, $roomId)
    {
        return response()->json([
            'connected' => true,
            'room_id' => (int)$roomId,
            'timestamp' => now()->toISOString()
        ]);
    }
}