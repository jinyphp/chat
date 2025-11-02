<?php

namespace Jiny\Chat\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Jiny\Chat\Models\ChatRoom;

/**
 * MessageSent 이벤트 - 새 메시지 전송 시 발생
 *
 * [이벤트 역할]
 * - 새로운 채팅 메시지가 전송될 때 Server-Sent Events(SSE)로 실시간 브로드캐스트
 * - 해당 채팅방의 모든 참여자에게 실시간 알림
 * - SQLite 기반 메시지 저장과 연동
 *
 * [브로드캐스트 채널]
 * - chat-room.{roomId}: 해당 채팅방 참여자들에게만 전송
 * - SSE 스트림을 통한 즉시 메시지 전달
 *
 * [전송 데이터]
 * - message: 메시지 정보 (내용, 발신자, 시간 등)
 * - room_id: 채팅방 ID
 * - sender: 발신자 정보
 * - participants: 참여자 목록
 */
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $room;
    public $message;
    public $participants;

    /**
     * 새 인스턴스 생성
     */
    public function __construct(ChatRoom $room, array $message, array $participants = [])
    {
        $this->room = $room;
        $this->message = $message;
        $this->participants = $participants;
    }

    /**
     * 이벤트가 브로드캐스트될 채널들
     */
    public function broadcastOn()
    {
        return [
            new Channel("chat-room.{$this->room->id}"),
        ];
    }

    /**
     * 브로드캐스트 이벤트 이름
     */
    public function broadcastAs()
    {
        return 'message.sent';
    }

    /**
     * 브로드캐스트할 데이터
     */
    public function broadcastWith()
    {
        return [
            'type' => 'message',
            'room_id' => $this->room->id,
            'message' => [
                'id' => $this->message['id'],
                'room_id' => $this->message['room_id'],
                'user_uuid' => $this->message['user_uuid'],
                'user_name' => $this->getUserName($this->message['user_uuid']),
                'message' => $this->message['message'],
                'message_type' => $this->message['message_type'] ?? 'text',
                'reply_to_id' => $this->message['reply_to_id'] ?? null,
                'is_system' => (bool) ($this->message['is_system'] ?? false),
                'created_at' => $this->message['created_at'],
                'updated_at' => $this->message['updated_at']
            ],
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * 참여자 목록에서 사용자 이름 찾기
     */
    private function getUserName($userUuid): string
    {
        foreach ($this->participants as $participant) {
            if (is_array($participant) && $participant['user_uuid'] === $userUuid) {
                return $participant['name'];
            }
        }
        return 'Unknown User';
    }

    /**
     * SSE 형식으로 데이터 반환
     */
    public function toSseFormat(): string
    {
        $data = $this->broadcastWith();
        return "event: message.sent\n" .
               "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    }

    /**
     * 브로드캐스트할 사용자 결정 (선택사항)
     * 채팅방 참여자만 메시지를 받도록 제한
     */
    public function broadcastToEveryone()
    {
        return false;
    }
}