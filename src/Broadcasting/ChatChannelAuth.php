<?php

namespace Jiny\Chat\Broadcasting;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;

/**
 * ChatChannelAuth - 채팅 채널 인증 클래스
 *
 * [클래스 역할]
 * - 브로드캐스팅 채널 접근 권한 확인
 * - JWT 토큰을 통한 사용자 인증
 * - 채팅방 참여자 권한 검증
 * - 프레즌스 채널 사용자 정보 제공
 *
 * [지원 채널]
 * - chat-room.{roomId}: 채팅방별 프라이빗 채널
 * - chat-user.{userUuid}: 개별 사용자 채널
 * - chat-presence.{roomId}: 온라인 사용자 목록 채널
 *
 * [인증 방식]
 * - JwtAuth:: 파사드를 통한 JWT 토큰 검증
 * - 샤딩된 사용자 시스템 지원
 * - 채팅방 참여자 상태 확인
 */
class ChatChannelAuth
{
    /**
     * 채팅방 채널 인증
     *
     * @param \Illuminate\Http\Request $request
     * @param string $roomId
     * @return array|bool
     */
    public function chatRoom($request, $roomId)
    {
        try {
            // JWT 토큰을 통한 사용자 인증
            $user = \JwtAuth::user($request);
            if (!$user) {
                return false;
            }

            // 채팅방 존재 확인
            $room = ChatRoom::find($roomId);
            if (!$room || $room->status !== 'active') {
                return false;
            }

            // 참여자 권한 확인
            $participant = $room->participants()
                ->where('user_uuid', $user->uuid)
                ->where('status', 'active')
                ->first();

            if (!$participant) {
                return false;
            }

            // 차단된 사용자 확인
            if ($participant->isBanned()) {
                return false;
            }

            // 인증 성공 - 사용자 정보 반환
            return [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'avatar' => $user->avatar ?? null,
                'role' => $participant->role,
                'joined_at' => $participant->joined_at->toISOString(),
            ];

        } catch (\Exception $e) {
            \Log::error('Chat room channel auth failed', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 사용자 개인 채널 인증
     *
     * @param \Illuminate\Http\Request $request
     * @param string $userUuid
     * @return array|bool
     */
    public function chatUser($request, $userUuid)
    {
        try {
            // JWT 토큰을 통한 사용자 인증
            $user = \JwtAuth::user($request);
            if (!$user) {
                return false;
            }

            // 본인의 채널인지 확인
            if ($user->uuid !== $userUuid) {
                return false;
            }

            // 인증 성공 - 사용자 정보 반환
            return [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'avatar' => $user->avatar ?? null,
            ];

        } catch (\Exception $e) {
            \Log::error('Chat user channel auth failed', [
                'user_uuid' => $userUuid,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 프레즌스 채널 인증 (온라인 사용자 목록)
     *
     * @param \Illuminate\Http\Request $request
     * @param string $roomId
     * @return array|bool
     */
    public function chatPresence($request, $roomId)
    {
        try {
            // JWT 토큰을 통한 사용자 인증
            $user = \JwtAuth::user($request);
            if (!$user) {
                return false;
            }

            // 채팅방 존재 확인
            $room = ChatRoom::find($roomId);
            if (!$room || $room->status !== 'active') {
                return false;
            }

            // 참여자 권한 확인
            $participant = $room->participants()
                ->where('user_uuid', $user->uuid)
                ->where('status', 'active')
                ->first();

            if (!$participant) {
                return false;
            }

            // 차단된 사용자 확인
            if ($participant->isBanned()) {
                return false;
            }

            // 사용자 활동 시간 업데이트
            $participant->updateActivity();

            // 프레즌스 채널용 사용자 정보 반환
            return [
                'id' => $user->uuid, // Presence 채널에서는 'id' 키 사용
                'name' => $user->name,
                'avatar' => $user->avatar ?? null,
                'role' => $participant->role,
                'online_status' => 'online',
                'last_activity' => now()->toISOString(),
            ];

        } catch (\Exception $e) {
            \Log::error('Chat presence channel auth failed', [
                'room_id' => $roomId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 타이핑 채널 인증
     *
     * @param \Illuminate\Http\Request $request
     * @param string $roomId
     * @return array|bool
     */
    public function chatTyping($request, $roomId)
    {
        // 타이핑 채널은 채팅방 채널과 동일한 인증 사용
        return $this->chatRoom($request, $roomId);
    }

    /**
     * 채널 이름을 파싱하여 적절한 인증 메서드 호출
     *
     * @param \Illuminate\Http\Request $request
     * @param string $channel
     * @return array|bool
     */
    public function authorize($request, $channel)
    {
        // 채널 이름 파싱
        if (preg_match('/^chat-room\.(.+)$/', $channel, $matches)) {
            return $this->chatRoom($request, $matches[1]);
        }

        if (preg_match('/^chat-user\.(.+)$/', $channel, $matches)) {
            return $this->chatUser($request, $matches[1]);
        }

        if (preg_match('/^chat-presence\.(.+)$/', $channel, $matches)) {
            return $this->chatPresence($request, $matches[1]);
        }

        if (preg_match('/^chat-typing\.(.+)$/', $channel, $matches)) {
            return $this->chatTyping($request, $matches[1]);
        }

        // 알 수 없는 채널
        return false;
    }

    /**
     * 레이트 리미팅 확인
     *
     * @param string $userUuid
     * @param string $action
     * @return bool
     */
    protected function checkRateLimit($userUuid, $action = 'default')
    {
        $key = "chat_rate_limit:{$userUuid}:{$action}";
        $maxAttempts = config('chat.security.rate_limiting.max_attempts', 60);
        $decayMinutes = config('chat.security.rate_limiting.decay_minutes', 1);

        return \RateLimiter::tooManyAttempts($key, $maxAttempts) === false;
    }

    /**
     * 레이트 리미팅 카운터 증가
     *
     * @param string $userUuid
     * @param string $action
     */
    protected function incrementRateLimit($userUuid, $action = 'default')
    {
        $key = "chat_rate_limit:{$userUuid}:{$action}";
        $decayMinutes = config('chat.security.rate_limiting.decay_minutes', 1);

        \RateLimiter::hit($key, $decayMinutes * 60);
    }
}