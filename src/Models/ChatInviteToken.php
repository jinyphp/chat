<?php

namespace Jiny\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

/**
 * ChatInviteToken 모델 - 채팅방 초대 토큰 관리
 *
 * [모델 역할 및 목적]
 * - 채팅방 초대 링크용 토큰 생성 및 관리
 * - 토큰 유효성 검증 및 사용 횟수 추적
 * - 토큰 만료 및 비활성화 관리
 *
 * [주요 컬럼]
 * - token: 고유 초대 토큰
 * - room_id: 대상 채팅방 ID
 * - created_by_uuid: 토큰 생성자 UUID
 * - expires_at: 만료 시간
 * - max_uses: 최대 사용 횟수 (null = 무제한)
 * - used_count: 현재 사용 횟수
 * - is_active: 활성화 상태
 *
 * [사용 예시]
 * ```php
 * // 초대 토큰 생성
 * $token = ChatInviteToken::createInviteToken($roomId, $creatorUuid, [
 *     'expires_in_hours' => 24,
 *     'max_uses' => 10
 * ]);
 *
 * // 토큰으로 참여 시도
 * $result = ChatInviteToken::joinWithToken($token, $userUuid);
 * ```
 */
class ChatInviteToken extends Model
{
    use HasFactory;

    protected $table = 'chat_invite_tokens';

    protected $fillable = [
        'token',
        'room_id',
        'room_uuid',
        'created_by_uuid',
        'expires_at',
        'max_uses',
        'used_count',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 채팅방과의 관계
     */
    public function room()
    {
        return $this->belongsTo(ChatRoom::class, 'room_id');
    }

    /**
     * 초대 토큰 생성
     */
    public static function createInviteToken($roomId, $roomUuid, $createdByUuid, $options = [])
    {
        $expiresInHours = $options['expires_in_hours'] ?? 24;
        $maxUses = $options['max_uses'] ?? null;
        $metadata = $options['metadata'] ?? [];

        $token = self::generateUniqueToken();

        return static::create([
            'token' => $token,
            'room_id' => $roomId,
            'room_uuid' => $roomUuid,
            'created_by_uuid' => $createdByUuid,
            'expires_at' => now()->addHours($expiresInHours),
            'max_uses' => $maxUses,
            'used_count' => 0,
            'is_active' => true,
            'metadata' => $metadata,
        ]);
    }

    /**
     * 고유 토큰 생성
     */
    protected static function generateUniqueToken()
    {
        do {
            $token = \Str::random(32);
        } while (static::where('token', $token)->exists());

        return $token;
    }

    /**
     * 토큰 유효성 검증
     */
    public function isValid()
    {
        // 비활성화된 토큰
        if (!$this->is_active) {
            return false;
        }

        // 만료된 토큰
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        // 사용 횟수 초과
        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /**
     * 토큰으로 채팅방 참여
     */
    public static function joinWithToken($tokenString, $userUuid)
    {
        $token = static::where('token', $tokenString)->first();

        if (!$token) {
            return [
                'success' => false,
                'message' => '유효하지 않은 초대 링크입니다.',
                'code' => 'INVALID_TOKEN'
            ];
        }

        if (!$token->isValid()) {
            return [
                'success' => false,
                'message' => '만료되었거나 사용할 수 없는 초대 링크입니다.',
                'code' => 'EXPIRED_TOKEN'
            ];
        }

        try {
            // 이미 참여 중인지 확인
            $existingParticipant = ChatParticipant::where('room_id', $token->room_id)
                ->where('user_uuid', $userUuid)
                ->where('status', 'active')
                ->first();

            if ($existingParticipant) {
                return [
                    'success' => false,
                    'message' => '이미 이 채팅방에 참여 중입니다.',
                    'code' => 'ALREADY_MEMBER',
                    'room_id' => $token->room_id
                ];
            }

            // 사용자 정보 확인
            $user = \Jiny\Auth\Facades\Shard::getUserByUuid($userUuid);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => '사용자 정보를 찾을 수 없습니다.',
                    'code' => 'USER_NOT_FOUND'
                ];
            }

            // 채팅방 참여자 추가
            ChatParticipant::create([
                'room_id' => $token->room_id,
                'room_uuid' => $token->room_uuid,
                'user_uuid' => $userUuid,
                'shard_id' => \Jiny\Auth\Facades\Shard::getShardNumber($userUuid),
                'email' => $user->email,
                'name' => $user->name,
                'avatar' => $user->avatar ?? null,
                'language' => $user->language ?? 'ko',
                'role' => 'member',
                'status' => 'active',
                'can_send_message' => true,
                'can_invite' => false,
                'can_moderate' => false,
                'notifications_enabled' => true,
                'joined_at' => now(),
                'invited_by_uuid' => $token->created_by_uuid,
                'join_reason' => 'invite_link',
            ]);

            // 토큰 사용 횟수 증가
            $token->increment('used_count');

            // 사용 횟수가 최대치에 도달하면 비활성화
            if ($token->max_uses && $token->used_count >= $token->max_uses) {
                $token->update(['is_active' => false]);
            }

            return [
                'success' => true,
                'message' => '채팅방에 성공적으로 참여했습니다.',
                'room_id' => $token->room_id,
                'room_uuid' => $token->room_uuid
            ];

        } catch (\Exception $e) {
            \Log::error('토큰으로 채팅방 참여 실패', [
                'token' => $tokenString,
                'user_uuid' => $userUuid,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '채팅방 참여 중 오류가 발생했습니다.',
                'code' => 'JOIN_ERROR'
            ];
        }
    }

    /**
     * 토큰 비활성화
     */
    public function deactivate()
    {
        $this->update(['is_active' => false]);
    }

    /**
     * 만료된 토큰 정리
     */
    public static function cleanupExpiredTokens()
    {
        return static::where('expires_at', '<', now())
            ->orWhere(function ($query) {
                $query->whereNotNull('max_uses')
                    ->whereColumn('used_count', '>=', 'max_uses');
            })
            ->update(['is_active' => false]);
    }

    /**
     * 채팅방의 활성 토큰 조회
     */
    public static function getActiveTokensForRoom($roomId)
    {
        return static::where('room_id', $roomId)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->where(function ($query) {
                $query->whereNull('max_uses')
                    ->orWhereColumn('used_count', '<', 'max_uses');
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }
}