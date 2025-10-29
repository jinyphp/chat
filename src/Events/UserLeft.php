<?php

namespace Jiny\Chat\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * UserLeft 이벤트 - 사용자 채팅방 퇴장 알림
 *
 * [이벤트 역할]
 * - 사용자가 채팅방에서 나갔을 때 실시간 알림
 * - 참여자 목록에서 해당 사용자 제거
 * - 퇴장 메시지 또는 시스템 알림 표시
 *
 * [전송 데이터]
 * - user_info: 퇴장한 사용자 정보
 * - room_id: 채팅방 ID
 * - leave_reason: 퇴장 사유
 */
class UserLeft implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userUuid;
    public $userName;
    public $roomId;
    public $reason;

    /**
     * 새 인스턴스 생성
     */
    public function __construct($roomId, $userUuid, $userName, $reason = '사용자 요청')
    {
        $this->roomId = $roomId;
        $this->userUuid = $userUuid;
        $this->userName = $userName;
        $this->reason = $reason;
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
        return 'UserLeft';
    }

    /**
     * 브로드캐스트할 데이터
     */
    public function broadcastWith()
    {
        return [
            'user' => [
                'uuid' => $this->userUuid,
                'name' => $this->userName,
            ],
            'room_id' => $this->roomId,
            'reason' => $this->reason,
            'message' => "{$this->userName}님이 채팅방을 나갔습니다.",
            'timestamp' => now()->toISOString(),
        ];
    }
}