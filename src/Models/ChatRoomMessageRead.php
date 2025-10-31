<?php

namespace Jiny\Chat\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * ChatRoomMessageRead - 채팅방별 독립 메시지 읽음 상태 모델
 */
class ChatRoomMessageRead extends ChatRoomModel
{
    use HasFactory;

    protected $table = 'chat_message_reads';

    protected $fillable = [
        'message_id',
        'user_uuid',
        'user_email',
        'user_name',
        'read_at',
        'read_type',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /**
     * 메시지 관계
     */
    public function message()
    {
        return $this->belongsTo(ChatRoomMessage::class, 'message_id');
    }

    /**
     * 읽음 상태 대량 생성
     */
    public static function markMessagesAsRead($roomCode, $userUuid, array $messageIds)
    {
        $user = \Shard::user($userUuid) ?? auth()->user();
        if (!$user) {
            return false;
        }

        $reads = [];
        $now = now();

        foreach ($messageIds as $messageId) {
            // 이미 읽은 메시지는 건너뛰기
            $existing = static::forRoom($roomCode)
                ->where('message_id', $messageId)
                ->where('user_uuid', $userUuid)
                ->exists();

            if (!$existing) {
                $reads[] = [
                    'message_id' => $messageId,
                    'user_uuid' => $userUuid,
                    'user_email' => $user->email,
                    'user_name' => $user->name,
                    'read_at' => $now,
                    'read_type' => 'read',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($reads)) {
            static::forRoom($roomCode)->insert($reads);

            // 메시지들의 읽음 수 업데이트
            foreach ($messageIds as $messageId) {
                ChatRoomMessage::forRoom($roomCode)
                    ->where('id', $messageId)
                    ->increment('read_count');
            }

            return count($reads);
        }

        return 0;
    }
}