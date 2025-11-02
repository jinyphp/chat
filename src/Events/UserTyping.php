<?php

namespace Jiny\Chat\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * UserTyping 이벤트 - 사용자 타이핑 상태 알림
 *
 * [이벤트 역할]
 * - 사용자가 메시지를 입력 중일 때 실시간 표시
 * - 타이핑 시작/종료 상태를 다른 참여자들에게 전달
 * - 실시간 상호작용감 제공
 *
 * [상태 타입]
 * - start: 타이핑 시작
 * - stop: 타이핑 종료
 *
 * [자동 만료]
 * - 타이핑 이벤트는 3초 후 자동으로 만료됨
 * - 클라이언트에서 자동으로 타이핑 상태 해제 처리
 */
class UserTyping implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomId;
    public $userUuid;
    public $userName;
    public $action; // 'start' or 'stop'

    /**
     * 새 인스턴스 생성
     */
    public function __construct($roomId, $userUuid, $userName, $action = 'start')
    {
        $this->roomId = $roomId;
        $this->userUuid = $userUuid;
        $this->userName = $userName;
        $this->action = $action;
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
        return 'user.typing';
    }

    /**
     * 브로드캐스트할 데이터
     */
    public function broadcastWith()
    {
        return [
            'type' => 'typing',
            'room_id' => $this->roomId,
            'user_uuid' => $this->userUuid,
            'user_name' => $this->userName,
            'action' => $this->action,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * SSE 형식으로 데이터 반환
     */
    public function toSseFormat(): string
    {
        $data = $this->broadcastWith();
        return "event: user.typing\n" .
               "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    }

    /**
     * 발신자 본인은 타이핑 이벤트를 받지 않음
     */
    public function broadcastToEveryone()
    {
        return false;
    }
}