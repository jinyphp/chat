<?php

namespace Jiny\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * ChatMessageRead 모델 - 메시지 읽음 상태 관리
 *
 * [모델 역할 및 목적]
 * - 사용자별 메시지 읽음 상태 추적
 * - 읽지 않은 메시지 수 계산 지원
 * - 메시지 전달 확인 기능
 * - 샤딩된 사용자 시스템 지원
 *
 * [주요 컬럼]
 * - message_id: 메시지 ID
 * - room_id: 채팅방 ID
 * - user_uuid: 읽은 사용자 UUID (샤딩 지원)
 * - shard_id: 사용자 샤드 ID
 * - read_at: 읽은 시간
 * - read_type: 읽음 타입 (delivered, read, seen)
 *
 * [읽음 타입]
 * - delivered: 메시지가 전달됨
 * - read: 메시지를 읽음
 * - seen: 메시지를 확인함
 *
 * [사용 예시]
 * ```php
 * // 메시지 읽음 상태 생성
 * ChatMessageRead::markAsRead($messageId, $userUuid);
 *
 * // 특정 사용자의 읽음 상태 조회
 * $readStatus = ChatMessageRead::where('message_id', $messageId)
 *     ->where('user_uuid', $userUuid)->first();
 *
 * // 메시지별 읽은 사용자 수 조회
 * $readCount = ChatMessageRead::where('message_id', $messageId)->count();
 * ```
 */
class ChatMessageRead extends Model
{
    use HasFactory;

    protected $table = 'chat_message_reads';

    protected $fillable = [
        'message_id',
        'room_id',
        'user_uuid',
        'shard_id',
        'user_email',
        'user_name',
        'read_at',
        'read_type',
        'device_type',
        'user_agent',
        'ip_address',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    protected $dates = [
        'read_at',
    ];

    /**
     * 메시지 관계
     */
    public function message()
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    /**
     * 채팅방 관계
     */
    public function room()
    {
        return $this->belongsTo(ChatRoom::class, 'room_id');
    }

    /**
     * 사용자 정보 조회 (샤딩 지원)
     */
    public function getUserAttribute()
    {
        return \Shard::user($this->user_uuid);
    }

    /**
     * 메시지 읽음 처리
     */
    public static function markAsRead($messageId, $userUuid, $readType = 'read', array $metadata = [])
    {
        // 메시지 조회
        $message = ChatMessage::find($messageId);
        if (!$message) {
            return false;
        }

        // 사용자 정보 조회
        $user = \Shard::user($userUuid);
        if (!$user) {
            return false;
        }

        // 자신이 보낸 메시지는 읽음 처리하지 않음
        if ($message->sender_uuid === $userUuid) {
            return false;
        }

        // 이미 읽음 처리된 경우 업데이트
        $existingRead = static::where('message_id', $messageId)
            ->where('user_uuid', $userUuid)
            ->first();

        if ($existingRead) {
            // 더 높은 레벨의 읽음 상태로 업데이트
            $readLevels = ['delivered' => 1, 'read' => 2, 'seen' => 3];
            $currentLevel = $readLevels[$existingRead->read_type] ?? 0;
            $newLevel = $readLevels[$readType] ?? 0;

            if ($newLevel > $currentLevel) {
                $existingRead->update([
                    'read_type' => $readType,
                    'read_at' => now(),
                ]);
            }

            return $existingRead;
        }

        // 새로운 읽음 상태 생성
        $readData = [
            'message_id' => $messageId,
            'room_id' => $message->room_id,
            'user_uuid' => $userUuid,
            'shard_id' => $user->shard_id ?? \Shard::getShardId($userUuid),
            'user_email' => $user->email,
            'user_name' => $user->name,
            'read_at' => now(),
            'read_type' => $readType,
        ];

        // 메타데이터 추가
        if (isset($metadata['device_type'])) {
            $readData['device_type'] = $metadata['device_type'];
        }
        if (isset($metadata['user_agent'])) {
            $readData['user_agent'] = $metadata['user_agent'];
        }
        if (isset($metadata['ip_address'])) {
            $readData['ip_address'] = $metadata['ip_address'];
        }

        return static::create($readData);
    }

    /**
     * 방의 모든 메시지를 읽음 처리
     */
    public static function markRoomAsRead($roomId, $userUuid, $upToMessageId = null)
    {
        $room = ChatRoom::find($roomId);
        if (!$room) {
            return false;
        }

        $user = \Shard::user($userUuid);
        if (!$user) {
            return false;
        }

        // 읽지 않은 메시지 조회
        $messagesQuery = $room->messages()
            ->where('sender_uuid', '!=', $userUuid)
            ->where('is_deleted', false)
            ->whereNotExists(function ($query) use ($userUuid) {
                $query->select('id')
                    ->from('chat_message_reads')
                    ->whereColumn('message_id', 'chat_messages.id')
                    ->where('user_uuid', $userUuid);
            });

        if ($upToMessageId) {
            $messagesQuery->where('id', '<=', $upToMessageId);
        }

        $unreadMessages = $messagesQuery->get();

        $readCount = 0;
        foreach ($unreadMessages as $message) {
            static::markAsRead($message->id, $userUuid);
            $readCount++;
        }

        // 참여자의 읽지 않은 메시지 수 업데이트
        $participant = $room->participants()
            ->where('user_uuid', $userUuid)
            ->first();

        if ($participant) {
            $participant->markAsRead($upToMessageId);
        }

        return $readCount;
    }

    /**
     * 메시지별 읽은 사용자 목록 조회
     */
    public static function getMessageReaders($messageId)
    {
        return static::where('message_id', $messageId)
            ->orderBy('read_at')
            ->get()
            ->map(function ($read) {
                return [
                    'user_uuid' => $read->user_uuid,
                    'user_name' => $read->user_name,
                    'read_at' => $read->read_at,
                    'read_type' => $read->read_type,
                ];
            });
    }

    /**
     * 사용자의 방별 읽지 않은 메시지 수 조회
     */
    public static function getUnreadCountByRoom($userUuid, $roomId = null)
    {
        $query = ChatMessage::query()
            ->select('room_id', \DB::raw('COUNT(*) as unread_count'))
            ->where('sender_uuid', '!=', $userUuid)
            ->where('is_deleted', false)
            ->whereNotExists(function ($subQuery) use ($userUuid) {
                $subQuery->select('id')
                    ->from('chat_message_reads')
                    ->whereColumn('message_id', 'chat_messages.id')
                    ->where('user_uuid', $userUuid);
            })
            ->groupBy('room_id');

        if ($roomId) {
            $query->where('room_id', $roomId);
            return $query->value('unread_count') ?? 0;
        }

        return $query->pluck('unread_count', 'room_id')->toArray();
    }

    /**
     * 읽음 타입별 스코프
     */
    public function scopeOfType($query, $readType)
    {
        return $query->where('read_type', $readType);
    }

    /**
     * 특정 사용자 스코프
     */
    public function scopeByUser($query, $userUuid)
    {
        return $query->where('user_uuid', $userUuid);
    }

    /**
     * 특정 샤드 스코프
     */
    public function scopeInShard($query, $shardId)
    {
        return $query->where('shard_id', $shardId);
    }

    /**
     * 기간별 스코프
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('read_at', [$startDate, $endDate]);
    }

    /**
     * 최근 읽음 스코프
     */
    public function scopeRecent($query, $minutes = 5)
    {
        return $query->where('read_at', '>', now()->subMinutes($minutes));
    }

    /**
     * 디바이스별 스코프
     */
    public function scopeByDevice($query, $deviceType)
    {
        return $query->where('device_type', $deviceType);
    }
}