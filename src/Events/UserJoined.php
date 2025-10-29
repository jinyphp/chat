<?php

namespace Jiny\Chat\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Jiny\Chat\Models\ChatParticipant;

/**
 * UserJoined 이벤트 - 사용자 채팅방 입장 알림
 *
 * [이벤트 역할]
 * - 새로운 사용자가 채팅방에 참여했을 때 실시간 알림
 * - 참여자 목록 실시간 업데이트
 * - 환영 메시지 또는 시스템 알림 표시
 *
 * [전송 데이터]
 * - participant: 참여자 정보
 * - room_id: 채팅방 ID
 * - join_message: 참여 메시지
 */
class UserJoined implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $participant;
    public $roomId;

    /**
     * 새 인스턴스 생성
     */
    public function __construct(ChatParticipant $participant)
    {
        $this->participant = $participant;
        $this->roomId = $participant->room_id;
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
        return 'UserJoined';
    }

    /**
     * 브로드캐스트할 데이터
     */
    public function broadcastWith()
    {
        return [
            'participant' => [
                'id' => $this->participant->id,
                'user_uuid' => $this->participant->user_uuid,
                'user_name' => $this->participant->user_name,
                'user_avatar' => $this->participant->user_avatar,
                'role' => $this->participant->role,
                'joined_at' => $this->participant->joined_at->toISOString(),
            ],
            'room_id' => $this->roomId,
            'message' => "{$this->participant->user_name}님이 채팅방에 참여했습니다.",
            'timestamp' => now()->toISOString(),
        ];
    }
}