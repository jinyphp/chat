<?php

namespace Jiny\Chat\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Jiny\Chat\Models\ChatMessage;

/**
 * MessageSent 이벤트 - 새 메시지 전송 시 발생
 *
 * [이벤트 역할]
 * - 새로운 채팅 메시지가 전송될 때 실시간으로 브로드캐스트
 * - 해당 채팅방의 모든 참여자에게 실시간 알림
 * - WebSocket을 통한 즉시 메시지 전달
 *
 * [브로드캐스트 채널]
 * - chat-room.{roomId}: 해당 채팅방 참여자들에게만 전송
 * - chat-user.{userUuid}: 개별 사용자 알림용
 *
 * [전송 데이터]
 * - message: 메시지 정보 (내용, 발신자, 시간 등)
 * - room_id: 채팅방 ID
 * - sender: 발신자 정보
 */
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $roomId;

    /**
     * 새 인스턴스 생성
     */
    public function __construct(ChatMessage $message)
    {
        $this->message = $message->load(['sender', 'replyTo']);
        $this->roomId = $message->room_id;
    }

    /**
     * 이벤트가 브로드캐스트될 채널들
     */
    public function broadcastOn()
    {
        return [
            new Channel("chat-room.{$this->roomId}"),
        ];
    }

    /**
     * 브로드캐스트 이벤트 이름
     */
    public function broadcastAs()
    {
        return 'MessageSent';
    }

    /**
     * 브로드캐스트할 데이터
     */
    public function broadcastWith()
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'content' => $this->message->content,
                'type' => $this->message->type,
                'sender_uuid' => $this->message->sender_uuid,
                'sender_name' => $this->message->sender_name,
                'sender_avatar' => $this->message->sender_avatar,
                'created_at' => $this->message->created_at->toISOString(),
                'is_edited' => $this->message->is_edited,
                'reply_to' => $this->message->replyTo ? [
                    'id' => $this->message->replyTo->id,
                    'content' => $this->message->replyTo->content,
                    'sender_name' => $this->message->replyTo->sender_name,
                ] : null,
                'reactions' => $this->message->reactions ?? [],
                'media' => $this->message->media,
            ],
            'room_id' => $this->roomId,
            'timestamp' => now()->toISOString(),
        ];
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