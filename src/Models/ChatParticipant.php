<?php

namespace Jiny\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * ChatParticipant 모델 - 채팅방 참여자 관리
 *
 * [모델 역할 및 목적]
 * - 채팅방별 참여자 정보 관리
 * - 사용자별 참여 상태 및 권한 관리
 * - 읽지 않은 메시지 수 추적
 * - 샤딩된 사용자 시스템 완벽 지원
 *
 * [주요 컬럼]
 * - room_id: 채팅방 ID
 * - user_uuid: 사용자 UUID (샤딩 지원)
 * - shard_id: 사용자 샤드 ID
 * - role: 방 내 역할 (owner, admin, moderator, member)
 * - status: 참여 상태 (active, inactive, banned, left)
 * - last_read_at: 마지막 읽은 시간
 * - unread_count: 읽지 않은 메시지 수
 *
 * [권한 시스템]
 * - 역할 기반 권한 관리
 * - 세부 권한 설정 지원
 * - 알림 설정 관리
 *
 * [사용 예시]
 * ```php
 * // 참여자 정보 조회
 * $participant = ChatParticipant::where('room_id', $roomId)
 *     ->where('user_uuid', $userUuid)->first();
 *
 * // 읽지 않은 메시지 수 업데이트
 * $participant->updateUnreadCount();
 *
 * // 마지막 읽은 시간 업데이트
 * $participant->markAsRead($messageId);
 * ```
 */
class ChatParticipant extends Model
{
    use HasFactory;

    protected $table = 'chat_participants';

    protected $fillable = [
        'room_id',
        'room_uuid',
        'user_uuid',
        'shard_id',
        'email',
        'name',
        'avatar',
        'language',
        'role',
        'status',
        'permissions',
        'can_send_message',
        'can_invite',
        'can_moderate',
        'notifications_enabled',
        'notification_settings',
        'last_read_at',
        'last_read_message_id',
        'unread_count',
        'joined_at',
        'last_seen_at',
        'invited_by_uuid',
        'join_reason',
        'banned_at',
        'banned_by_uuid',
        'ban_reason',
        'ban_expires_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'notification_settings' => 'array',
        'can_send_message' => 'boolean',
        'can_invite' => 'boolean',
        'can_moderate' => 'boolean',
        'notifications_enabled' => 'boolean',
        'last_read_at' => 'datetime',
        'joined_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'banned_at' => 'datetime',
        'ban_expires_at' => 'datetime',
    ];

    protected $dates = [
        'last_read_at',
        'joined_at',
        'last_seen_at',
        'banned_at',
        'ban_expires_at',
    ];

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
     * 초대한 사용자 정보 조회
     */
    public function getInvitedByAttribute()
    {
        if (!$this->invited_by_uuid) {
            return null;
        }
        return \Shard::user($this->invited_by_uuid);
    }

    /**
     * 차단한 사용자 정보 조회
     */
    public function getBannedByAttribute()
    {
        if (!$this->banned_by_uuid) {
            return null;
        }
        return \Shard::user($this->banned_by_uuid);
    }

    /**
     * 읽지 않은 메시지 수 업데이트
     */
    public function updateUnreadCount()
    {
        $unreadCount = $this->room->messages()
            ->where('created_at', '>', $this->last_read_at ?? $this->joined_at)
            ->where('sender_uuid', '!=', $this->user_uuid) // 자신이 보낸 메시지는 제외
            ->where('is_deleted', false)
            ->count();

        $this->update(['unread_count' => $unreadCount]);

        return $unreadCount;
    }

    /**
     * 메시지를 읽음으로 표시
     */
    public function markAsRead($messageId = null)
    {
        $updateData = [
            'last_read_at' => now(),
            'last_seen_at' => now(),
            'unread_count' => 0,
        ];

        if ($messageId) {
            $updateData['last_read_message_id'] = $messageId;
        } else {
            // 가장 최근 메시지 ID 조회
            $latestMessage = $this->room->messages()->latest()->first();
            if ($latestMessage) {
                $updateData['last_read_message_id'] = $latestMessage->id;
            }
        }

        $this->update($updateData);

        return $this;
    }

    /**
     * 특정 메시지까지 읽음 처리
     */
    public function markAsReadUntil($messageId)
    {
        $message = ChatMessage::find($messageId);
        if (!$message || $message->room_id !== $this->room_id) {
            return false;
        }

        // 해당 메시지 이후의 읽지 않은 메시지 수 계산
        $unreadCount = $this->room->messages()
            ->where('id', '>', $messageId)
            ->where('sender_uuid', '!=', $this->user_uuid)
            ->where('is_deleted', false)
            ->count();

        $this->update([
            'last_read_at' => $message->created_at,
            'last_read_message_id' => $messageId,
            'unread_count' => $unreadCount,
            'last_seen_at' => now(),
        ]);

        return true;
    }

    /**
     * 권한 확인
     */
    public function hasPermission($permission)
    {
        // 역할 기반 기본 권한
        $rolePermissions = $this->getRolePermissions();

        // 커스텀 권한 설정
        $customPermissions = $this->permissions ?? [];

        return isset($rolePermissions[$permission]) && $rolePermissions[$permission] ||
               isset($customPermissions[$permission]) && $customPermissions[$permission];
    }

    /**
     * 역할별 기본 권한 조회
     */
    protected function getRolePermissions()
    {
        $permissions = [
            'owner' => [
                'can_send_message' => true,
                'can_invite' => true,
                'can_moderate' => true,
                'can_delete_room' => true,
                'can_modify_room' => true,
                'can_ban_users' => true,
                'can_assign_roles' => true,
            ],
            'admin' => [
                'can_send_message' => true,
                'can_invite' => true,
                'can_moderate' => true,
                'can_delete_room' => false,
                'can_modify_room' => true,
                'can_ban_users' => true,
                'can_assign_roles' => false,
            ],
            'moderator' => [
                'can_send_message' => true,
                'can_invite' => true,
                'can_moderate' => true,
                'can_delete_room' => false,
                'can_modify_room' => false,
                'can_ban_users' => false,
                'can_assign_roles' => false,
            ],
            'member' => [
                'can_send_message' => true,
                'can_invite' => false,
                'can_moderate' => false,
                'can_delete_room' => false,
                'can_modify_room' => false,
                'can_ban_users' => false,
                'can_assign_roles' => false,
            ],
        ];

        return $permissions[$this->role] ?? $permissions['member'];
    }

    /**
     * 권한 업데이트
     */
    public function updatePermissions(array $permissions)
    {
        $currentPermissions = $this->permissions ?? [];
        $newPermissions = array_merge($currentPermissions, $permissions);

        $this->update(['permissions' => $newPermissions]);

        return $this;
    }

    /**
     * 알림 설정 업데이트
     */
    public function updateNotificationSettings(array $settings)
    {
        $currentSettings = $this->notification_settings ?? [];
        $newSettings = array_merge($currentSettings, $settings);

        $this->update(['notification_settings' => $newSettings]);

        return $this;
    }

    /**
     * 차단 상태 확인
     */
    public function isBanned()
    {
        if ($this->status !== 'banned') {
            return false;
        }

        // 차단 만료 확인
        if ($this->ban_expires_at && $this->ban_expires_at->isPast()) {
            $this->update(['status' => 'left']);
            return false;
        }

        return true;
    }

    /**
     * 온라인 상태 확인
     */
    public function isOnline()
    {
        if (!$this->last_seen_at) {
            return false;
        }

        // 5분 이내 활동이 있으면 온라인으로 간주
        return $this->last_seen_at->diffInMinutes(now()) <= 5;
    }

    /**
     * 활동 시간 업데이트
     */
    public function updateActivity()
    {
        $this->update(['last_seen_at' => now()]);
    }

    /**
     * 활성 참여자 스코프
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * 역할별 스코프
     */
    public function scopeWithRole($query, $role)
    {
        return $query->where('role', $role);
    }

    /**
     * 온라인 사용자 스코프
     */
    public function scopeOnline($query)
    {
        return $query->where('last_seen_at', '>', now()->subMinutes(5));
    }

    /**
     * 특정 사용자 스코프
     */
    public function scopeForUser($query, $userUuid)
    {
        return $query->where('user_uuid', $userUuid);
    }

    /**
     * 샤드별 스코프
     */
    public function scopeInShard($query, $shardId)
    {
        return $query->where('shard_id', $shardId);
    }
}