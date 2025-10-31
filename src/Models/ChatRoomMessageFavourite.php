<?php

namespace Jiny\Chat\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * ChatRoomMessageFavourite - 채팅방별 독립 메시지 즐겨찾기 모델
 */
class ChatRoomMessageFavourite extends ChatRoomModel
{
    use HasFactory;

    protected $table = 'chat_message_favourites';

    protected $fillable = [
        'message_id',
        'user_uuid',
        'user_email',
        'user_name',
        'note',
    ];

    /**
     * 메시지 관계
     */
    public function message()
    {
        return $this->belongsTo(ChatRoomMessage::class, 'message_id');
    }

    /**
     * 즐겨찾기 추가/제거 토글
     */
    public static function toggleFavourite($roomCode, $messageId, $userUuid, $note = null)
    {
        $existing = static::forRoom($roomCode)
            ->where('message_id', $messageId)
            ->where('user_uuid', $userUuid)
            ->first();

        if ($existing) {
            $existing->delete();
            return false; // 제거됨
        } else {
            $user = \Shard::user($userUuid) ?? auth()->user();
            if (!$user) {
                throw new \Exception('User not found');
            }

            static::forRoom($roomCode)->create([
                'message_id' => $messageId,
                'user_uuid' => $userUuid,
                'user_email' => $user->email,
                'user_name' => $user->name,
                'note' => $note,
            ]);

            return true; // 추가됨
        }
    }

    /**
     * 사용자의 즐겨찾기 목록 조회
     */
    public static function getUserFavourites($roomCode, $userUuid)
    {
        return static::forRoom($roomCode)
            ->where('user_uuid', $userUuid)
            ->with('message')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}