<?php

namespace Jiny\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ChatMessage 모델 - 채팅 메시지 관리
 *
 * [모델 역할 및 목적]
 * - 채팅 메시지 저장 및 관리
 * - 메시지 타입별 처리 (텍스트, 이미지, 파일, 시스템)
 * - 메시지 상태 및 읽음 처리
 * - 답글 및 스레드 기능 지원
 * - 샤딩된 사용자 시스템 지원
 *
 * [주요 컬럼]
 * - room_id: 채팅방 ID
 * - sender_uuid: 발신자 UUID (샤딩 지원)
 * - type: 메시지 타입 (text, image, file, voice, video, system)
 * - content: 메시지 내용
 * - reply_to_message_id: 답장 대상 메시지 ID
 * - status: 메시지 상태 (sent, delivered, read, edited, deleted)
 *
 * [메시지 타입]
 * - text: 일반 텍스트 메시지
 * - image: 이미지 메시지
 * - file: 파일 메시지
 * - voice: 음성 메시지
 * - video: 동영상 메시지
 * - system: 시스템 메시지
 * - notification: 알림 메시지
 *
 * [사용 예시]
 * ```php
 * // 새 메시지 전송
 * $message = ChatMessage::sendMessage($roomId, $userUuid, [
 *     'type' => 'text',
 *     'content' => '안녕하세요!'
 * ]);
 *
 * // 답글 메시지 전송
 * $reply = ChatMessage::sendReply($originalMessageId, $userUuid, [
 *     'content' => '네, 안녕하세요!'
 * ]);
 *
 * // 메시지 읽음 처리
 * $message->markAsRead($userUuid);
 * ```
 */
class ChatMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'chat_messages';

    protected $fillable = [
        'room_id',
        'room_uuid',
        'sender_uuid',
        'sender_shard_id',
        'sender_email',
        'sender_name',
        'sender_avatar',
        'type',
        'content',
        'encrypted_content',
        'media',
        'metadata',
        'reply_to_message_id',
        'thread_root_id',
        'thread_count',
        'status',
        'is_edited',
        'is_deleted',
        'is_pinned',
        'is_system',
        'edited_at',
        'edited_by_uuid',
        'deleted_at',
        'deleted_by_uuid',
        'delete_reason',
        'read_count',
        'first_read_at',
        'last_read_at',
        'reactions',
        'reaction_count',
        'mentions',
        'tags',
    ];

    protected $casts = [
        'media' => 'array',
        'metadata' => 'array',
        'is_edited' => 'boolean',
        'is_deleted' => 'boolean',
        'is_pinned' => 'boolean',
        'is_system' => 'boolean',
        'reactions' => 'array',
        'mentions' => 'array',
        'tags' => 'array',
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
        'first_read_at' => 'datetime',
        'last_read_at' => 'datetime',
    ];

    protected $dates = [
        'edited_at',
        'deleted_at',
        'first_read_at',
        'last_read_at',
    ];

    /**
     * 채팅방 관계
     */
    public function room()
    {
        return $this->belongsTo(ChatRoom::class, 'room_id');
    }

    /**
     * 답장 대상 메시지 관계
     */
    public function replyTo()
    {
        return $this->belongsTo(ChatMessage::class, 'reply_to_message_id');
    }

    /**
     * 이 메시지에 대한 답글들
     */
    public function replies()
    {
        return $this->hasMany(ChatMessage::class, 'reply_to_message_id');
    }

    /**
     * 스레드 루트 메시지
     */
    public function threadRoot()
    {
        return $this->belongsTo(ChatMessage::class, 'thread_root_id');
    }

    /**
     * 스레드 메시지들
     */
    public function threadMessages()
    {
        return $this->hasMany(ChatMessage::class, 'thread_root_id');
    }

    /**
     * 메시지 읽음 상태
     */
    public function reads()
    {
        return $this->hasMany(ChatMessageRead::class, 'message_id');
    }

    /**
     * 발신자 정보 조회 (샤딩 지원)
     */
    public function getSenderAttribute()
    {
        if (!$this->sender_uuid) {
            return null;
        }
        return \Shard::user($this->sender_uuid);
    }

    /**
     * 편집한 사용자 정보 조회
     */
    public function getEditedByAttribute()
    {
        if (!$this->edited_by_uuid) {
            return null;
        }
        return \Shard::user($this->edited_by_uuid);
    }

    /**
     * 삭제한 사용자 정보 조회
     */
    public function getDeletedByAttribute()
    {
        if (!$this->deleted_by_uuid) {
            return null;
        }
        return \Shard::user($this->deleted_by_uuid);
    }

    /**
     * 새 메시지 전송
     */
    public static function sendMessage($roomId, $senderUuid, array $data)
    {
        // 방 존재 확인
        $room = ChatRoom::find($roomId);
        if (!$room) {
            throw new \Exception('Room not found');
        }

        // 발신자 정보 조회
        $sender = \Shard::user($senderUuid);
        if (!$sender) {
            throw new \Exception('Sender not found');
        }

        // 참여자 권한 확인
        $participant = $room->participants()
            ->where('user_uuid', $senderUuid)
            ->where('status', 'active')
            ->first();

        if (!$participant || !$participant->can_send_message) {
            throw new \Exception('No permission to send message');
        }

        // 메시지 데이터 준비
        $messageData = array_merge([
            'room_id' => $roomId,
            'room_uuid' => $room->uuid,
            'sender_uuid' => $senderUuid,
            'sender_shard_id' => $sender->shard_id ?? \Shard::getShardId($senderUuid),
            'sender_email' => $sender->email,
            'sender_name' => $sender->name,
            'sender_avatar' => $sender->avatar ?? null,
            'type' => 'text',
            'status' => 'sent',
            'is_system' => false,
        ], $data);

        // 답글인 경우 스레드 정보 설정
        if (isset($data['reply_to_message_id'])) {
            $replyToMessage = static::find($data['reply_to_message_id']);
            if ($replyToMessage && $replyToMessage->room_id === $roomId) {
                $messageData['thread_root_id'] = $replyToMessage->thread_root_id ?? $replyToMessage->id;
            }
        }

        // 메시지 생성
        $message = static::create($messageData);

        // 방 통계 업데이트
        $room->increment('message_count');
        $room->update([
            'last_message_at' => now(),
            'last_activity_at' => now(),
        ]);

        // 스레드 카운트 업데이트
        if ($message->thread_root_id && $message->thread_root_id !== $message->id) {
            static::where('id', $message->thread_root_id)->increment('thread_count');
        }

        // 참여자 활동 시간 업데이트
        $participant->updateActivity();

        // 다른 참여자들의 읽지 않은 메시지 수 업데이트
        $room->participants()
            ->where('user_uuid', '!=', $senderUuid)
            ->where('status', 'active')
            ->each(function ($p) {
                $p->increment('unread_count');
            });

        return $message;
    }

    /**
     * 시스템 메시지 생성
     */
    public static function createSystemMessage($roomId, $content, array $metadata = [])
    {
        $room = ChatRoom::find($roomId);
        if (!$room) {
            throw new \Exception('Room not found');
        }

        return static::create([
            'room_id' => $roomId,
            'room_uuid' => $room->uuid,
            'type' => 'system',
            'content' => $content,
            'metadata' => $metadata,
            'status' => 'sent',
            'is_system' => true,
        ]);
    }

    /**
     * 메시지 편집
     */
    public function editMessage($newContent, $editedBy)
    {
        // 편집 권한 확인 (발신자 본인 또는 관리자)
        if ($this->sender_uuid !== $editedBy) {
            $participant = $this->room->participants()
                ->where('user_uuid', $editedBy)
                ->first();

            if (!$participant || !$participant->hasPermission('can_moderate')) {
                throw new \Exception('No permission to edit message');
            }
        }

        // 시스템 메시지는 편집 불가
        if ($this->is_system) {
            throw new \Exception('Cannot edit system message');
        }

        $this->update([
            'content' => $newContent,
            'is_edited' => true,
            'edited_at' => now(),
            'edited_by_uuid' => $editedBy,
            'status' => 'edited',
        ]);

        return $this;
    }

    /**
     * 메시지 삭제 (소프트 삭제)
     */
    public function deleteMessage($deletedBy, $reason = null)
    {
        // 삭제 권한 확인
        if ($this->sender_uuid !== $deletedBy) {
            $participant = $this->room->participants()
                ->where('user_uuid', $deletedBy)
                ->first();

            if (!$participant || !$participant->hasPermission('can_moderate')) {
                throw new \Exception('No permission to delete message');
            }
        }

        $this->update([
            'is_deleted' => true,
            'deleted_at' => now(),
            'deleted_by_uuid' => $deletedBy,
            'delete_reason' => $reason,
            'status' => 'deleted',
        ]);

        // 방 메시지 수 감소
        $this->room->decrement('message_count');

        return $this;
    }

    /**
     * 메시지 읽음 처리
     */
    public function markAsRead($userUuid)
    {
        // 자신이 보낸 메시지는 읽음 처리하지 않음
        if ($this->sender_uuid === $userUuid) {
            return false;
        }

        // 이미 읽은 메시지인지 확인
        $existingRead = $this->reads()->where('user_uuid', $userUuid)->first();
        if ($existingRead) {
            return false;
        }

        // 사용자 정보 조회
        $user = \Shard::user($userUuid);
        if (!$user) {
            return false;
        }

        // 읽음 상태 생성
        ChatMessageRead::create([
            'message_id' => $this->id,
            'room_id' => $this->room_id,
            'user_uuid' => $userUuid,
            'shard_id' => $user->shard_id ?? \Shard::getShardId($userUuid),
            'user_email' => $user->email,
            'user_name' => $user->name,
            'read_at' => now(),
            'read_type' => 'read',
        ]);

        // 읽음 수 업데이트
        $this->increment('read_count');

        // 첫 번째 읽음인 경우
        if (!$this->first_read_at) {
            $this->update(['first_read_at' => now()]);
        }

        $this->update(['last_read_at' => now()]);

        return true;
    }

    /**
     * 메시지에 반응 추가
     */
    public function addReaction($userUuid, $emoji)
    {
        $reactions = $this->reactions ?? [];
        $reactions[$emoji] = $reactions[$emoji] ?? [];

        // 이미 반응했는지 확인
        if (!in_array($userUuid, $reactions[$emoji])) {
            $reactions[$emoji][] = $userUuid;

            $this->update([
                'reactions' => $reactions,
                'reaction_count' => $this->reaction_count + 1,
            ]);
        }

        return $this;
    }

    /**
     * 메시지 반응 제거
     */
    public function removeReaction($userUuid, $emoji)
    {
        $reactions = $this->reactions ?? [];

        if (isset($reactions[$emoji])) {
            $key = array_search($userUuid, $reactions[$emoji]);
            if ($key !== false) {
                unset($reactions[$emoji][$key]);
                $reactions[$emoji] = array_values($reactions[$emoji]); // 배열 재정렬

                // 빈 이모지 배열 제거
                if (empty($reactions[$emoji])) {
                    unset($reactions[$emoji]);
                }

                $this->update([
                    'reactions' => $reactions,
                    'reaction_count' => max(0, $this->reaction_count - 1),
                ]);
            }
        }

        return $this;
    }

    /**
     * 메시지 고정/고정 해제
     */
    public function togglePin($userUuid)
    {
        // 권한 확인
        $participant = $this->room->participants()
            ->where('user_uuid', $userUuid)
            ->first();

        if (!$participant || !$participant->hasPermission('can_moderate')) {
            throw new \Exception('No permission to pin message');
        }

        $this->update(['is_pinned' => !$this->is_pinned]);

        return $this;
    }

    /**
     * 텍스트 메시지 스코프
     */
    public function scopeText($query)
    {
        return $query->where('type', 'text');
    }

    /**
     * 시스템 메시지 스코프
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * 삭제되지 않은 메시지 스코프
     */
    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }

    /**
     * 발신자별 스코프
     */
    public function scopeBySender($query, $senderUuid)
    {
        return $query->where('sender_uuid', $senderUuid);
    }

    /**
     * 기간별 스코프
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * 스레드 메시지 스코프
     */
    public function scopeInThread($query, $threadRootId)
    {
        return $query->where('thread_root_id', $threadRootId);
    }
}