<?php

namespace Jiny\Chat\Services;

use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Models\ChatMessage;
use Jiny\Chat\Models\ChatMessageRead;

/**
 * ChatService - 채팅 시스템 핵심 서비스
 *
 * [서비스 역할]
 * - 채팅방 생성 및 관리
 * - 메시지 전송 및 처리
 * - 사용자 권한 관리
 * - 실시간 이벤트 처리
 * - 샤딩된 사용자 시스템 지원
 *
 * [주요 기능]
 * - 방 생성/참여/탈퇴
 * - 메시지 전송/편집/삭제
 * - 읽음 상태 관리
 * - 참여자 권한 관리
 * - 실시간 알림
 *
 * [사용 예시]
 * ```php
 * $chatService = app('chat');
 *
 * // 방 생성
 * $room = $chatService->createRoom($userUuid, [
 *     'title' => '프로젝트 회의',
 *     'type' => 'group'
 * ]);
 *
 * // 메시지 전송
 * $message = $chatService->sendMessage($roomId, $userUuid, [
 *     'content' => '안녕하세요!'
 * ]);
 * ```
 */
class ChatService
{
    /**
     * 새 채팅방 생성
     */
    public function createRoom($ownerUuid, array $data)
    {
        // 사용자 인증 확인 - 임시로 우회 (개발용)
        // TODO: 실제 사용자 시스템 연동 시 수정 필요
        if (!$ownerUuid) {
            throw new \Exception('Owner UUID is required');
        }

        // 방 생성 데이터 준비
        $roomData = array_merge([
            'owner_uuid' => $ownerUuid,
            'type' => 'public',
            'status' => 'active',
        ], $data);

        // 방 생성
        $room = ChatRoom::createRoom($roomData);

        // 실시간 이벤트 발송
        $this->broadcastRoomCreated($room);

        return $room;
    }

    /**
     * 채팅방 참여
     */
    public function joinRoom($roomId, $userUuid, $inviteCode = null, $password = null)
    {
        // 사용자 인증 확인 - 임시로 우회 (개발용)
        if (!$userUuid) {
            throw new \Exception('User UUID is required');
        }

        // 임시 사용자 객체 생성 (개발용)
        $user = (object) [
            'uuid' => $userUuid,
            'name' => 'User',
            'email' => 'user@example.com'
        ];

        // 방 조회
        $room = ChatRoom::find($roomId);
        if (!$room) {
            throw new \Exception('Room not found');
        }

        // 초대 코드 확인
        if ($inviteCode && $room->invite_code !== $inviteCode) {
            throw new \Exception('Invalid invite code');
        }

        // 비밀번호 확인
        if ($room->password && !$room->checkPassword($password)) {
            throw new \Exception('Invalid password');
        }

        // 참여 가능 여부 확인
        if (!$room->canJoin($userUuid)) {
            throw new \Exception('Cannot join room');
        }

        // 참여자 추가
        $participant = $room->addParticipant($userUuid);

        // 시스템 메시지 생성
        ChatMessage::createSystemMessage(
            $room->id,
            "{$user->name}님이 채팅방에 참여했습니다.",
            ['user_uuid' => $userUuid, 'action' => 'join']
        );

        // 실시간 이벤트 발송
        $this->broadcastUserJoined($room, $participant);

        return $participant;
    }

    /**
     * 채팅방 탈퇴
     */
    public function leaveRoom($roomId, $userUuid, $reason = null)
    {
        // 사용자 인증 확인 - 임시로 우회 (개발용)
        if (!$userUuid) {
            throw new \Exception('User UUID is required');
        }

        // 임시 사용자 객체 생성 (개발용)
        $user = (object) [
            'uuid' => $userUuid,
            'name' => 'User',
            'email' => 'user@example.com'
        ];

        // 방 조회
        $room = ChatRoom::find($roomId);
        if (!$room) {
            throw new \Exception('Room not found');
        }

        // 참여자 제거
        $removed = $room->removeParticipant($userUuid, $reason);
        if (!$removed) {
            throw new \Exception('User is not a participant');
        }

        // 시스템 메시지 생성
        ChatMessage::createSystemMessage(
            $room->id,
            "{$user->name}님이 채팅방을 나갔습니다.",
            ['user_uuid' => $userUuid, 'action' => 'leave', 'reason' => $reason]
        );

        // 방장이 나간 경우 처리
        if ($room->owner_uuid === $userUuid && $room->participant_count > 0) {
            $this->transferOwnership($room);
        }

        // 실시간 이벤트 발송
        $this->broadcastUserLeft($room, $userUuid, $user->name, $reason);

        return true;
    }

    /**
     * 메시지 전송
     */
    public function sendMessage($roomId, $senderUuid, array $messageData)
    {
        // 사용자 인증 확인 - 임시로 우회 (개발용)
        if (!$senderUuid) {
            throw new \Exception('Sender UUID is required');
        }

        // 임시 사용자 객체 생성 (개발용)
        $sender = (object) [
            'uuid' => $senderUuid,
            'name' => 'User',
            'email' => 'user@example.com'
        ];

        // 스팸 검사 (설정이 활성화된 경우)
        if (config('chat.security.spam_detection')) {
            $this->checkSpam($senderUuid, $messageData['content'] ?? '');
        }

        // 메시지 전송
        $message = ChatMessage::sendMessage($roomId, $senderUuid, $messageData);

        // 실시간 이벤트 발송
        $this->broadcastMessage($message);

        // 알림 발송
        $this->sendNotifications($message);

        return $message;
    }

    /**
     * 메시지 편집
     */
    public function editMessage($messageId, $userUuid, $newContent)
    {
        // 사용자 인증 확인 - 임시로 우회 (개발용)
        if (!$userUuid) {
            throw new \Exception('User UUID is required');
        }

        // 임시 사용자 객체 생성 (개발용)
        $user = (object) [
            'uuid' => $userUuid,
            'name' => 'User',
            'email' => 'user@example.com'
        ];

        // 메시지 조회
        $message = ChatMessage::find($messageId);
        if (!$message) {
            throw new \Exception('Message not found');
        }

        // 메시지 편집
        $message->editMessage($newContent, $userUuid);

        // 실시간 이벤트 발송
        $this->broadcastMessageEdited($message);

        return $message;
    }

    /**
     * 메시지 삭제
     */
    public function deleteMessage($messageId, $userUuid, $reason = null)
    {
        // 사용자 인증 확인 - 임시로 우회 (개발용)
        if (!$userUuid) {
            throw new \Exception('User UUID is required');
        }

        // 임시 사용자 객체 생성 (개발용)
        $user = (object) [
            'uuid' => $userUuid,
            'name' => 'User',
            'email' => 'user@example.com'
        ];

        // 메시지 조회
        $message = ChatMessage::find($messageId);
        if (!$message) {
            throw new \Exception('Message not found');
        }

        // 메시지 삭제
        $message->deleteMessage($userUuid, $reason);

        // 실시간 이벤트 발송
        $this->broadcastMessageDeleted($message);

        return true;
    }

    /**
     * 메시지 읽음 처리
     */
    public function markAsRead($roomId, $userUuid, $messageId = null)
    {
        // 사용자 인증 확인 - 임시로 우회 (개발용)
        if (!$userUuid) {
            throw new \Exception('User UUID is required');
        }

        // 임시 사용자 객체 생성 (개발용)
        $user = (object) [
            'uuid' => $userUuid,
            'name' => 'User',
            'email' => 'user@example.com'
        ];

        if ($messageId) {
            // 특정 메시지 읽음 처리
            $message = ChatMessage::find($messageId);
            if (!$message || $message->room_id != $roomId) {
                throw new \Exception('Message not found');
            }

            $message->markAsRead($userUuid);
        } else {
            // 방의 모든 메시지 읽음 처리
            ChatMessageRead::markRoomAsRead($roomId, $userUuid);
        }

        // 실시간 이벤트 발송
        $this->broadcastReadStatus($roomId, $userUuid, $messageId);

        return true;
    }

    /**
     * 사용자 권한 변경
     */
    public function changeUserRole($roomId, $targetUserUuid, $newRole, $changedBy)
    {
        // 권한 변경자 인증 확인
        if (!$changedBy) {
            throw new \Exception('Changer UUID is required');
        }
        $changer = (object) ['uuid' => $changedBy, 'name' => 'User'];

        // 방 조회
        $room = ChatRoom::find($roomId);
        if (!$room) {
            throw new \Exception('Room not found');
        }

        // 권한 확인
        $changerParticipant = $room->participants()
            ->where('user_uuid', $changedBy)
            ->first();

        if (!$changerParticipant || !$changerParticipant->hasPermission('can_assign_roles')) {
            throw new \Exception('No permission to change roles');
        }

        // 대상 참여자 조회
        $targetParticipant = $room->participants()
            ->where('user_uuid', $targetUserUuid)
            ->first();

        if (!$targetParticipant) {
            throw new \Exception('Target user is not a participant');
        }

        // 역할 변경
        $oldRole = $targetParticipant->role;
        $targetParticipant->update(['role' => $newRole]);

        // 시스템 메시지 생성
        $targetUser = (object) ['uuid' => $targetUserUuid, 'name' => 'User'];
        ChatMessage::createSystemMessage(
            $room->id,
            "{$targetUser->name}님의 역할이 {$oldRole}에서 {$newRole}로 변경되었습니다.",
            [
                'user_uuid' => $targetUserUuid,
                'action' => 'role_change',
                'old_role' => $oldRole,
                'new_role' => $newRole,
                'changed_by' => $changedBy
            ]
        );

        // 실시간 이벤트 발송
        $this->broadcastRoleChanged($room, $targetParticipant, $oldRole);

        return $targetParticipant;
    }

    /**
     * 사용자 차단
     */
    public function banUser($roomId, $targetUserUuid, $bannedBy, $reason = null, $expiresAt = null)
    {
        // 차단자 인증 확인
        if (!$bannedBy) {
            throw new \Exception('Banner UUID is required');
        }
        $banner = (object) ['uuid' => $bannedBy, 'name' => 'User'];

        // 방 조회
        $room = ChatRoom::find($roomId);
        if (!$room) {
            throw new \Exception('Room not found');
        }

        // 권한 확인
        $bannerParticipant = $room->participants()
            ->where('user_uuid', $bannedBy)
            ->first();

        if (!$bannerParticipant || !$bannerParticipant->hasPermission('can_ban_users')) {
            throw new \Exception('No permission to ban users');
        }

        // 사용자 차단
        $banned = $room->banUser($targetUserUuid, $bannedBy, $reason, $expiresAt);
        if (!$banned) {
            throw new \Exception('Failed to ban user');
        }

        // 시스템 메시지 생성
        $targetUser = (object) ['uuid' => $targetUserUuid, 'name' => 'User'];
        ChatMessage::createSystemMessage(
            $room->id,
            "{$targetUser->name}님이 채팅방에서 차단되었습니다.",
            [
                'user_uuid' => $targetUserUuid,
                'action' => 'ban',
                'reason' => $reason,
                'banned_by' => $bannedBy,
                'expires_at' => $expiresAt
            ]
        );

        // 실시간 이벤트 발송
        $this->broadcastUserBanned($room, $targetUserUuid);

        return true;
    }

    /**
     * 방장 권한 이양
     */
    protected function transferOwnership($room)
    {
        // 가장 오래된 관리자 또는 멤버를 새 방장으로 선정
        $newOwner = $room->participants()
            ->where('status', 'active')
            ->whereIn('role', ['admin', 'moderator', 'member'])
            ->orderBy('joined_at')
            ->first();

        if ($newOwner) {
            $oldOwnerUuid = $room->owner_uuid;
            $newOwnerUser = (object) ['uuid' => $newOwner->user_uuid, 'name' => 'User'];

            // 방장 정보 업데이트
            $room->update([
                'owner_uuid' => $newOwner->user_uuid,
                'owner_shard_id' => $newOwner->shard_id,
                'owner_email' => $newOwner->email,
                'owner_name' => $newOwner->name,
            ]);

            // 새 방장의 역할 변경
            $newOwner->update(['role' => 'owner']);

            // 시스템 메시지 생성
            ChatMessage::createSystemMessage(
                $room->id,
                "{$newOwnerUser->name}님이 새로운 방장이 되었습니다.",
                [
                    'action' => 'ownership_transfer',
                    'old_owner' => $oldOwnerUuid,
                    'new_owner' => $newOwner->user_uuid
                ]
            );

            // 실시간 이벤트 발송
            $this->broadcastOwnershipTransfer($room, $newOwner);
        }
    }

    /**
     * 스팸 검사
     */
    protected function checkSpam($userUuid, $content)
    {
        $rateLimit = config('chat.security.rate_limiting');
        if (!$rateLimit['enabled']) {
            return;
        }

        // 1분 내 메시지 수 확인
        $recentMessages = ChatMessage::where('sender_uuid', $userUuid)
            ->where('created_at', '>', now()->subMinute())
            ->count();

        if ($recentMessages >= $rateLimit['max_messages_per_minute']) {
            throw new \Exception('메시지 전송 한도를 초과했습니다. 잠시 후 다시 시도해주세요.');
        }

        // TODO: 추가 스팸 검사 로직 구현
        // - 중복 메시지 검사
        // - 욕설 필터링
        // - 링크 스팸 검사 등
    }

    /**
     * 실시간 이벤트: 방 생성
     */
    protected function broadcastRoomCreated($room)
    {
        // 방 생성은 일반적으로 브로드캐스트하지 않음 (필요시 구현)
    }

    /**
     * 실시간 이벤트: 사용자 참여
     */
    protected function broadcastUserJoined($room, $participant)
    {
        try {
            event(new \Jiny\Chat\Events\UserJoined($participant));
        } catch (\Exception $e) {
            \Log::error('Failed to broadcast user joined event', [
                'room_id' => $room->id,
                'participant_id' => $participant->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 실시간 이벤트: 사용자 탈퇴
     */
    protected function broadcastUserLeft($room, $userUuid, $userName = null, $reason = '사용자 요청')
    {
        try {
            // 사용자 이름 조회 (제공되지 않은 경우)
            if (!$userName) {
                $userName = 'User';
            }

            event(new \Jiny\Chat\Events\UserLeft($room->id, $userUuid, $userName, $reason));
        } catch (\Exception $e) {
            \Log::error('Failed to broadcast user left event', [
                'room_id' => $room->id,
                'user_uuid' => $userUuid,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 실시간 이벤트: 메시지 전송
     */
    protected function broadcastMessage($message)
    {
        try {
            event(new \Jiny\Chat\Events\MessageSent($message));
        } catch (\Exception $e) {
            \Log::error('Failed to broadcast message sent event', [
                'message_id' => $message->id,
                'room_id' => $message->room_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 실시간 이벤트: 메시지 편집
     */
    protected function broadcastMessageEdited($message)
    {
        try {
            event(new \Jiny\Chat\Events\MessageUpdated($message, 'edited'));
        } catch (\Exception $e) {
            \Log::error('Failed to broadcast message edited event', [
                'message_id' => $message->id,
                'room_id' => $message->room_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 실시간 이벤트: 메시지 삭제
     */
    protected function broadcastMessageDeleted($message)
    {
        try {
            event(new \Jiny\Chat\Events\MessageUpdated($message, 'deleted'));
        } catch (\Exception $e) {
            \Log::error('Failed to broadcast message deleted event', [
                'message_id' => $message->id,
                'room_id' => $message->room_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 실시간 이벤트: 읽음 상태
     */
    protected function broadcastReadStatus($roomId, $userUuid, $messageId)
    {
        // TODO: 방 참여자들에게 읽음 상태 알림
        // broadcast(new MessageRead($roomId, $userUuid, $messageId));
    }

    /**
     * 실시간 이벤트: 역할 변경
     */
    protected function broadcastRoleChanged($room, $participant, $oldRole)
    {
        // TODO: 방 참여자들에게 역할 변경 알림
        // broadcast(new RoleChanged($room, $participant, $oldRole));
    }

    /**
     * 실시간 이벤트: 사용자 차단
     */
    protected function broadcastUserBanned($room, $userUuid)
    {
        // TODO: 방 참여자들에게 사용자 차단 알림
        // broadcast(new UserBanned($room, $userUuid));
    }

    /**
     * 실시간 이벤트: 방장 이양
     */
    protected function broadcastOwnershipTransfer($room, $newOwner)
    {
        // TODO: 방 참여자들에게 방장 이양 알림
        // broadcast(new OwnershipTransferred($room, $newOwner));
    }

    /**
     * 알림 발송
     */
    protected function sendNotifications($message)
    {
        // TODO: 푸시 알림, 이메일 알림 등 구현
        // 멘션된 사용자에게 특별 알림
        // 오프라인 사용자에게 푸시 알림
    }
}