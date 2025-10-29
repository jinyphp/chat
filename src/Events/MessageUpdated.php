<?php

namespace Jiny\Chat\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Jiny\Chat\Models\ChatMessage;

/**
 * MessageUpdated 이벤트 - 메시지 수정 시 발생
 *
 * [이벤트 역할]
 * - 메시지가 편집될 때 실시간으로 브로드캐스트
 * - 반응(reaction) 추가/제거 시에도 사용
 * - 메시지 삭제 시에도 사용 가능
 *
 * [업데이트 타입]
 * - edited: 메시지 내용 편집
 * - reaction: 반응 추가/제거
 * - deleted: 메시지 삭제
 * - pinned: 메시지 고정/해제
 */
class MessageUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $roomId;
    public $updateType;

    /**
     * 새 인스턴스 생성
     */
    public function __construct(ChatMessage $message, $updateType = 'edited')
    {
        $this->message = $message->load(['sender', 'replyTo']);
        $this->roomId = $message->room_id;
        $this->updateType = $updateType;
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
        return 'MessageUpdated';
    }

    /**
     * 브로드캐스트할 데이터
     */
    public function broadcastWith()
    {
        $data = [
            'message' => [
                'id' => $this->message->id,
                'content' => $this->message->content,
                'type' => $this->message->type,
                'sender_uuid' => $this->message->sender_uuid,
                'sender_name' => $this->message->sender_name,
                'created_at' => $this->message->created_at->toISOString(),
                'updated_at' => $this->message->updated_at->toISOString(),
                'is_edited' => $this->message->is_edited,
                'is_deleted' => $this->message->is_deleted,
                'is_pinned' => $this->message->is_pinned,
                'reactions' => $this->message->reactions ?? [],
                'media' => $this->message->media,
            ],
            'room_id' => $this->roomId,
            'update_type' => $this->updateType,
            'timestamp' => now()->toISOString(),
        ];

        // 삭제된 메시지의 경우 내용을 숨김
        if ($this->message->is_deleted) {
            $data['message']['content'] = '삭제된 메시지입니다.';
            $data['message']['media'] = null;
        }

        return $data;
    }
}