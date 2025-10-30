<?php

namespace Jiny\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

/**
 * ChatRoom 모델 - 채팅방 관리
 *
 * [모델 역할 및 목적]
 * - 채팅방 생성, 수정, 삭제 관리
 * - 방 설정 및 메타데이터 관리
 * - 샤딩된 사용자 시스템 지원
 * - 방 타입별 처리 (공개, 비공개, 그룹, 개인)
 *
 * [주요 컬럼]
 * - uuid: 방 고유 식별자
 * - code: 사용자 친화적 방 코드
 * - title: 방 제목
 * - type: 방 타입 (public, private, group, direct)
 * - owner_uuid: 방장 UUID (샤딩 지원)
 * - participant_count: 참여자 수
 * - status: 방 상태
 *
 * [샤딩 지원]
 * - owner_uuid와 owner_shard_id로 방장 정보 관리
 * - Shard:: 파사드를 통한 사용자 정보 조회
 *
 * [사용 예시]
 * ```php
 * // 새 채팅방 생성
 * $room = ChatRoom::createRoom([
 *     'title' => '프로젝트 토론방',
 *     'type' => 'group',
 *     'owner_uuid' => $userUuid
 * ]);
 *
 * // 방 참여자 추가
 * $room->addParticipant($userUuid, 'member');
 *
 * // 방 설정 변경
 * $room->updateSettings(['allow_join' => false]);
 * ```
 */
class ChatRoom extends Model
{
    use HasFactory;

    protected $table = 'chat_rooms';

    protected $fillable = [
        'uuid',
        'code',
        'slug',
        'title',
        'description',
        'image',
        'type',
        'category',
        'tags',
        'password',
        'invite_code',
        'encryption_key',
        'owner_uuid',
        'owner_shard_id',
        'owner_email',
        'owner_name',
        'participant_count',
        'max_participants',
        'message_count',
        'message_retention_days',
        'daily_message_limit',
        'today_message_count',
        'last_reset_date',
        'status',
        'is_public',
        'allow_join',
        'allow_invite',
        'allow_file_upload',
        'allow_voice_message',
        'allow_image_upload',
        'max_file_size_mb',
        'require_approval',
        'auto_moderation',
        'blocked_words',
        'slow_mode_seconds',
        'show_member_list',
        'allow_mentions',
        'allow_reactions',
        'read_receipts',
        'ui_settings',
        'notification_settings',
        'last_activity_at',
        'last_message_at',
        'expires_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'blocked_words' => 'array',
        'ui_settings' => 'array',
        'notification_settings' => 'array',
        'is_public' => 'boolean',
        'allow_join' => 'boolean',
        'allow_invite' => 'boolean',
        'allow_file_upload' => 'boolean',
        'allow_voice_message' => 'boolean',
        'allow_image_upload' => 'boolean',
        'require_approval' => 'boolean',
        'auto_moderation' => 'boolean',
        'show_member_list' => 'boolean',
        'allow_mentions' => 'boolean',
        'allow_reactions' => 'boolean',
        'read_receipts' => 'boolean',
        'last_reset_date' => 'date',
        'last_activity_at' => 'datetime',
        'last_message_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $dates = [
        'last_activity_at',
        'last_message_at',
        'expires_at',
    ];

    /**
     * 부팅 시 자동으로 UUID 생성
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->code)) {
                $model->code = $model->generateUniqueCode();
            }
            if (empty($model->invite_code)) {
                $model->invite_code = $model->generateInviteCode();
            }
        });
    }

    /**
     * 참여자 관계
     */
    public function participants()
    {
        return $this->hasMany(ChatParticipant::class, 'room_id');
    }

    /**
     * 활성 참여자 관계
     */
    public function activeParticipants()
    {
        return $this->participants()->where('status', 'active');
    }

    /**
     * 메시지 관계
     */
    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'room_id');
    }

    /**
     * 최근 메시지 관계
     */
    public function latestMessage()
    {
        return $this->hasOne(ChatMessage::class, 'room_id')->latest();
    }

    /**
     * 방장 정보 조회 (샤딩 지원)
     */
    public function getOwnerAttribute()
    {
        if (!$this->owner_uuid) {
            return null;
        }

        // Shard 파사드를 사용하여 사용자 정보 조회
        return \Shard::user($this->owner_uuid);
    }

    /**
     * 새 채팅방 생성
     */
    public static function createRoom(array $data)
    {
        // 방장 정보 조회 및 설정
        if (isset($data['owner_uuid'])) {
            $owner = \Shard::user($data['owner_uuid']);
            if ($owner) {
                $data['owner_shard_id'] = $owner->shard_id ?? \Shard::getShardId($data['owner_uuid']);
                $data['owner_email'] = $owner->email;
                $data['owner_name'] = $owner->name;
            }
        }

        // 기본값 설정
        $data = array_merge([
            'type' => 'public',
            'status' => 'active',
            'is_public' => true,
            'allow_join' => true,
            'allow_invite' => true,
            'max_participants' => config('chat.room.max_participants', 100),
            'participant_count' => 0,
            'message_count' => 0,
        ], $data);

        $room = static::create($data);

        // 방장을 참여자로 추가
        if (isset($data['owner_uuid'])) {
            $room->addParticipant($data['owner_uuid'], 'owner');
        }

        return $room;
    }

    /**
     * 참여자 추가
     */
    public function addParticipant($userUuid, $role = 'member', array $permissions = [])
    {
        // 사용자 정보 조회
        $user = \Shard::user($userUuid);
        if (!$user) {
            throw new \Exception('User not found');
        }

        // 이미 참여 중인지 확인
        $existing = $this->participants()
            ->where('user_uuid', $userUuid)
            ->first();

        if ($existing) {
            if ($existing->status === 'left' || $existing->status === 'banned') {
                // 재참여 처리
                $existing->update([
                    'status' => 'active',
                    'role' => $role,
                    'joined_at' => now(),
                    'permissions' => $permissions,
                ]);
                return $existing;
            }
            return $existing; // 이미 활성 참여자
        }

        // 새 참여자 추가
        $participant = $this->participants()->create([
            'room_uuid' => $this->uuid,
            'user_uuid' => $userUuid,
            'shard_id' => $user->shard_id ?? \Shard::getShardId($userUuid),
            'email' => $user->email,
            'name' => $user->name,
            'avatar' => $user->avatar ?? null,
            'role' => $role,
            'status' => 'active',
            'permissions' => $permissions,
            'joined_at' => now(),
            'last_seen_at' => now(),
        ]);

        // 참여자 수 업데이트
        $this->increment('participant_count');
        $this->touch('last_activity_at');

        return $participant;
    }

    /**
     * 참여자 제거
     */
    public function removeParticipant($userUuid, $reason = null)
    {
        $participant = $this->participants()
            ->where('user_uuid', $userUuid)
            ->where('status', 'active')
            ->first();

        if ($participant) {
            $participant->update([
                'status' => 'left',
                'join_reason' => $reason,
            ]);

            $this->decrement('participant_count');
            $this->touch('last_activity_at');

            return true;
        }

        return false;
    }

    /**
     * 사용자 차단
     */
    public function banUser($userUuid, $bannedBy, $reason = null, $expiresAt = null)
    {
        $participant = $this->participants()
            ->where('user_uuid', $userUuid)
            ->first();

        if ($participant) {
            $participant->update([
                'status' => 'banned',
                'banned_at' => now(),
                'banned_by_uuid' => $bannedBy,
                'ban_reason' => $reason,
                'ban_expires_at' => $expiresAt,
            ]);

            if ($participant->status === 'active') {
                $this->decrement('participant_count');
            }

            return true;
        }

        return false;
    }

    /**
     * 비밀번호 설정
     */
    public function setPassword($password)
    {
        $this->update([
            'password' => Hash::make($password)
        ]);
    }

    /**
     * 비밀번호 확인
     */
    public function checkPassword($password)
    {
        return Hash::check($password, $this->password);
    }

    /**
     * 방 설정 업데이트
     */
    public function updateSettings(array $settings)
    {
        $currentSettings = $this->ui_settings ?? [];
        $newSettings = array_merge($currentSettings, $settings);

        $this->update(['ui_settings' => $newSettings]);
    }

    /**
     * 사용자가 참여 가능한지 확인
     */
    public function canJoin($userUuid)
    {
        // 방 상태 확인
        if ($this->status !== 'active') {
            return false;
        }

        // 참여 허용 여부 확인
        if (!$this->allow_join && $this->owner_uuid !== $userUuid) {
            return false;
        }

        // 최대 참여자 수 확인
        if ($this->participant_count >= $this->max_participants) {
            return false;
        }

        // 차단 여부 확인
        $participant = $this->participants()
            ->where('user_uuid', $userUuid)
            ->where('status', 'banned')
            ->first();

        if ($participant) {
            // 차단 만료 확인
            if ($participant->ban_expires_at && $participant->ban_expires_at->isPast()) {
                $participant->update(['status' => 'left']);
                return true;
            }
            return false;
        }

        return true;
    }

    /**
     * 고유 방 코드 생성
     */
    protected function generateUniqueCode()
    {
        do {
            $code = 'room_' . Str::random(8);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * 초대 코드 생성
     */
    protected function generateInviteCode()
    {
        do {
            $code = Str::random(12);
        } while (static::where('invite_code', $code)->exists());

        return $code;
    }

    /**
     * 활성 방 스코프
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * 공개 방 스코프
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true)->where('status', 'active');
    }

    /**
     * 방 타입별 스코프
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * 소유자별 스코프
     */
    public function scopeOwnedBy($query, $userUuid)
    {
        return $query->where('owner_uuid', $userUuid);
    }
}