<?php

namespace Jiny\Chat\Http\Controllers\Home;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Models\ChatRoomMessage;
use Jiny\Chat\Models\ChatMessage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SSE 방식 채팅 컨트롤러
 * /home/chat/server/{id} 경로로 Server-Sent Events를 사용한 실시간 채팅
 */
class ChatServerController extends Controller
{
    /**
     * SSE 채팅방 뷰 표시
     */
    public function show($roomId)
    {
        // 채팅방 정보 로드
        $room = ChatRoom::find($roomId);

        if (!$room) {
            abort(404, '채팅방을 찾을 수 없습니다.');
        }

        // 사용자 인증 확인 (JWT 또는 세션)
        $user = \JwtAuth::user(request());
        if (!$user) {
            // 임시 테스트 사용자
            $user = (object) [
                'uuid' => 'test-user-001',
                'name' => 'Test User',
                'email' => 'test@example.com'
            ];
        }

        // 참여자 확인
        $participant = ChatParticipant::where('room_id', $roomId)
            ->where('user_uuid', $user->uuid)
            ->where('status', 'active')
            ->first();

        // 참여자가 아닌 경우 자동 참여 시도
        if (!$participant) {
            try {
                $participant = ChatParticipant::create([
                    'room_id' => $roomId,
                    'room_uuid' => $room->uuid,
                    'user_uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => 'member',
                    'status' => 'active',
                    'language' => 'ko',
                    'joined_at' => now(),
                ]);
            } catch (\Exception $e) {
                abort(403, '채팅방에 참여할 수 없습니다.');
            }
        }

        return view('jiny-chat::home.server.show', compact('room', 'user', 'participant'));
    }

    /**
     * SSE 스트림 제공
     */
    public function stream($roomId)
    {
        // 채팅방 존재 확인
        $room = ChatRoom::find($roomId);
        if (!$room) {
            abort(404);
        }

        // 사용자 인증
        $user = \JwtAuth::user(request());
        if (!$user) {
            $user = (object) [
                'uuid' => 'test-user-001',
                'name' => 'Test User',
                'email' => 'test@example.com'
            ];
        }

        return new StreamedResponse(function() use ($roomId, $room, $user) {
            // SSE 헤더 설정
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Nginx용

            // 초기 연결 확인 메시지
            $this->sendSSEMessage([
                'type' => 'connected',
                'message' => 'SSE 연결 성공',
                'timestamp' => now()->toISOString(),
                'room_id' => $roomId,
                'user_uuid' => $user->uuid
            ]);

            $lastMessageId = 0;
            $lastParticipantUpdate = now();
            $heartbeatInterval = 30; // 30초마다 하트비트
            $lastHeartbeat = time();

            // 무한 루프로 실시간 데이터 전송
            while (true) {
                $currentTime = time();

                try {
                    // 1. 새 메시지 확인
                    $this->checkNewMessages($room, $lastMessageId, $user);

                    // 2. 참여자 변경사항 확인 (30초마다)
                    if (now()->diffInSeconds($lastParticipantUpdate) >= 30) {
                        $this->sendParticipantUpdate($roomId);
                        $lastParticipantUpdate = now();
                    }

                    // 3. 하트비트 전송
                    if ($currentTime - $lastHeartbeat >= $heartbeatInterval) {
                        $this->sendHeartbeat();
                        $lastHeartbeat = $currentTime;
                    }

                    // 4. 클라이언트 연결 확인
                    if (connection_aborted()) {
                        break;
                    }

                } catch (\Exception $e) {
                    \Log::error('SSE 스트림 오류', [
                        'room_id' => $roomId,
                        'user_uuid' => $user->uuid,
                        'error' => $e->getMessage()
                    ]);

                    $this->sendSSEMessage([
                        'type' => 'error',
                        'message' => 'SSE 스트림 오류',
                        'error' => $e->getMessage()
                    ]);
                }

                // 1초 대기
                sleep(1);

                // 메모리 정리
                if ($currentTime % 60 == 0) {
                    gc_collect_cycles();
                }
            }

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no'
        ]);
    }

    /**
     * 새 메시지 확인 및 전송
     */
    private function checkNewMessages($room, &$lastMessageId, $user)
    {
        if ($room->code) {
            // 독립 데이터베이스 사용
            $newMessages = ChatRoomMessage::forRoom($room->code)
                ->where('id', '>', $lastMessageId)
                ->where('is_deleted', false)
                ->orderBy('created_at', 'asc')
                ->get();
        } else {
            // 기존 방식
            $newMessages = ChatMessage::where('room_id', $room->id)
                ->where('id', '>', $lastMessageId)
                ->orderBy('created_at', 'asc')
                ->get();
        }

        foreach ($newMessages as $message) {
            // 참여자 정보 조회
            $participant = ChatParticipant::where('room_id', $room->id)
                ->where('user_uuid', $message->sender_uuid)
                ->first();

            $formattedMessage = [
                'id' => $message->id,
                'content' => $message->content,
                'type' => $message->type ?? 'text',
                'sender_uuid' => $message->sender_uuid,
                'sender_name' => $participant ? $participant->name : $message->sender_uuid,
                'sender_avatar' => $participant ? $participant->avatar : null,
                'created_at' => $message->created_at->format('H:i'),
                'created_at_full' => $message->created_at->format('Y-m-d H:i:s'),
                'is_mine' => $message->sender_uuid === $user->uuid,
            ];

            $this->sendSSEMessage([
                'type' => 'new_message',
                'message' => $formattedMessage,
                'room_id' => $room->id
            ], 'new_message');

            $lastMessageId = $message->id;
        }
    }

    /**
     * 참여자 업데이트 전송
     */
    private function sendParticipantUpdate($roomId)
    {
        $participants = ChatParticipant::where('room_id', $roomId)
            ->where('status', 'active')
            ->orderBy('joined_at', 'asc')
            ->get()
            ->toArray();

        $this->sendSSEMessage([
            'type' => 'participants_update',
            'participants' => $participants,
            'count' => count($participants)
        ], 'participants_update');
    }

    /**
     * 하트비트 전송
     */
    private function sendHeartbeat()
    {
        $this->sendSSEMessage([
            'type' => 'heartbeat',
            'timestamp' => now()->toISOString()
        ], 'heartbeat');
    }

    /**
     * SSE 메시지 전송
     */
    private function sendSSEMessage($data, $event = 'message')
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        ob_flush();
        flush();
    }

    /**
     * 메시지 전송 API (SSE용)
     */
    public function sendMessage(Request $request, $roomId)
    {
        try {
            $room = ChatRoom::find($roomId);
            if (!$room) {
                return response()->json(['success' => false, 'message' => '채팅방을 찾을 수 없습니다.'], 404);
            }

            $user = \JwtAuth::user($request);
            if (!$user) {
                $user = (object) [
                    'uuid' => 'test-user-001',
                    'name' => 'Test User',
                    'email' => 'test@example.com'
                ];
            }

            $content = trim($request->input('content'));
            if (empty($content)) {
                return response()->json(['success' => false, 'message' => '메시지 내용이 없습니다.'], 400);
            }

            // 메시지 생성
            $messageData = [
                'content' => $content,
                'type' => 'text',
            ];

            if ($room->code) {
                $message = $room->sendMessage($user->uuid, $messageData);
            } else {
                $messageData['room_id'] = $roomId;
                $messageData['room_uuid'] = $room->uuid;
                $messageData['sender_uuid'] = $user->uuid;
                $messageData['created_at'] = now();
                $messageData['updated_at'] = now();

                $message = ChatMessage::create($messageData);
            }

            return response()->json([
                'success' => true,
                'message' => '메시지가 전송되었습니다.',
                'data' => [
                    'id' => $message->id,
                    'content' => $message->content,
                    'created_at' => $message->created_at->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('SSE 메시지 전송 실패', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
                'content' => $request->input('content')
            ]);

            return response()->json([
                'success' => false,
                'message' => '메시지 전송에 실패했습니다: ' . $e->getMessage()
            ], 500);
        }
    }
}