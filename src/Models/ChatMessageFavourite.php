<?php

namespace Jiny\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * ChatMessageFavourite 모델 - 채팅 메시지 즐겨찾기 관리
 *
 * [모델 역할 및 목적]
 * - 사용자별 메시지 즐겨찾기 관리
 * - 메시지와 사용자 간의 즐겨찾기 관계 저장
 * - 샤딩된 사용자 시스템 지원
 *
 * [주요 컬럼]
 * - message_id: 즐겨찾기된 메시지 ID
 * - user_uuid: 사용자 UUID (샤딩 지원)
 * - room_id: 채팅방 ID
 * - room_uuid: 채팅방 UUID
 * - shard_id: 샤드 ID
 *
 * [사용 예시]
 * ```php
 * // 메시지 즐겨찾기 추가
 * ChatMessageFavourite::create([
 *     'message_id' => $messageId,
 *     'user_uuid' => $userUuid,
 *     'room_id' => $roomId,
 *     'room_uuid' => $roomUuid,
 *     'shard_id' => $shardId
 * ]);
 *
 * // 메시지 즐겨찾기 제거
 * ChatMessageFavourite::where('message_id', $messageId)
 *     ->where('user_uuid', $userUuid)
 *     ->delete();
 *
 * // 사용자의 즐겨찾기 메시지 목록 조회
 * $favourites = ChatMessageFavourite::where('user_uuid', $userUuid)
 *     ->where('room_id', $roomId)
 *     ->with('message')
 *     ->get();
 * ```
 */
class ChatMessageFavourite extends Model
{
    use HasFactory;

    protected $table = 'chat_message_favourites';

    protected $fillable = [
        'message_id',
        'user_uuid',
        'room_id',
        'room_uuid',
        'shard_id',
    ];

    protected $casts = [
        'message_id' => 'integer',
        'room_id' => 'integer',
        'shard_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 즐겨찾기된 메시지와의 관계
     */
    public function message()
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    /**
     * 채팅방과의 관계
     */
    public function room()
    {
        return $this->belongsTo(ChatRoom::class, 'room_id');
    }

    /**
     * 메시지 즐겨찾기 토글
     */
    public static function toggleFavourite($messageId, $userUuid, $roomId, $roomUuid, $shardId = null)
    {
        $favourite = static::where('message_id', $messageId)
            ->where('user_uuid', $userUuid)
            ->first();

        if ($favourite) {
            // 즐겨찾기 제거
            $favourite->delete();
            return false;
        } else {
            // 즐겨찾기 추가
            static::create([
                'message_id' => $messageId,
                'user_uuid' => $userUuid,
                'room_id' => $roomId,
                'room_uuid' => $roomUuid,
                'shard_id' => $shardId,
            ]);
            return true;
        }
    }

    /**
     * 사용자의 즐겨찾기 메시지 ID 목록 조회
     */
    public static function getUserFavouriteMessageIds($userUuid, $roomId)
    {
        return static::where('user_uuid', $userUuid)
            ->where('room_id', $roomId)
            ->pluck('message_id')
            ->toArray();
    }

    /**
     * 메시지의 즐겨찾기 수 조회
     */
    public static function getFavouriteCount($messageId)
    {
        return static::where('message_id', $messageId)->count();
    }
}