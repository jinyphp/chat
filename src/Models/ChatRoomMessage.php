<?php

namespace Jiny\Chat\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ChatRoomMessage - 채팅방별 독립 메시지 모델
 *
 * 각 채팅방마다 독립적인 SQLite 데이터베이스의 메시지를 관리합니다.
 */
class ChatRoomMessage extends ChatRoomModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'chat_messages';

    protected $fillable = [
        'sender_uuid',
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
        'deleted_by_uuid',
        'delete_reason',
        'read_count',
        'first_read_at',
        'last_read_at',
        'reactions',
        'reaction_count',
        'likes',
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
        'likes' => 'array',
        'mentions' => 'array',
        'tags' => 'array',
        'edited_at' => 'datetime',
        'first_read_at' => 'datetime',
        'last_read_at' => 'datetime',
    ];

    /**
     * 답장 대상 메시지 관계
     */
    public function replyTo()
    {
        return $this->belongsTo(ChatRoomMessage::class, 'reply_to_message_id');
    }

    /**
     * 이 메시지에 대한 답글들
     */
    public function replies()
    {
        return $this->hasMany(ChatRoomMessage::class, 'reply_to_message_id');
    }

    /**
     * 스레드 루트 메시지
     */
    public function threadRoot()
    {
        return $this->belongsTo(ChatRoomMessage::class, 'thread_root_id');
    }

    /**
     * 스레드 메시지들
     */
    public function threadMessages()
    {
        return $this->hasMany(ChatRoomMessage::class, 'thread_root_id');
    }

    /**
     * 메시지 읽음 상태
     */
    public function reads()
    {
        return $this->hasMany(ChatRoomMessageRead::class, 'message_id');
    }

    /**
     * 메시지 번역
     */
    public function translations()
    {
        return $this->hasMany(ChatRoomMessageTranslation::class, 'message_id');
    }

    /**
     * 메시지 파일들
     */
    public function files()
    {
        return $this->hasMany(ChatRoomFile::class, 'message_id');
    }

    /**
     * 메시지 즐겨찾기
     */
    public function favourites()
    {
        return $this->hasMany(ChatRoomMessageFavourite::class, 'message_id');
    }

    /**
     * 새 메시지 생성
     */
    public static function createMessage($roomCode, $senderUuid, array $data, $roomId, $createdAt = null)
    {
        \Log::info('ChatRoomMessage::createMessage 호출 - 파라미터 확인', [
            'room_code' => $roomCode,
            'room_id' => $roomId,
            'room_id_type' => gettype($roomId),
            'room_id_is_null' => is_null($roomId),
            'room_id_is_empty' => empty($roomId),
            'room_id_is_numeric' => is_numeric($roomId),
            'created_at' => $createdAt,
            'sender_uuid' => $senderUuid,
            'data_keys' => array_keys($data)
        ]);

        // roomId 필수 검증 - 더 상세한 검사
        if (!$roomId || empty($roomId)) {
            \Log::error('ChatRoomMessage::createMessage - roomId 검증 실패', [
                'room_code' => $roomCode,
                'room_id' => $roomId,
                'room_id_type' => gettype($roomId),
                'room_id_var_dump' => var_export($roomId, true)
            ]);
            throw new \Exception("Room ID is required for message creation. RoomCode: {$roomCode}, RoomId: " . var_export($roomId, true));
        }

        // 발신자 정보 조회
        $sender = null;
        try {
            $sender = \Shard::user($senderUuid) ?? auth()->user();
        } catch (\Exception $e) {
            // 샤드 시스템에서 사용자를 찾을 수 없는 경우
            \Log::warning('사용자 조회 실패, 기본값 사용', [
                'sender_uuid' => $senderUuid,
                'error' => $e->getMessage()
            ]);
        }

        // 테스트 사용자 또는 사용자를 찾을 수 없는 경우 기본값 사용
        if (!$sender) {
            if (str_starts_with($senderUuid, 'test-user-')) {
                $sender = (object) [
                    'uuid' => $senderUuid,
                    'email' => 'test@example.com',
                    'name' => 'Test User',
                    'avatar' => null
                ];
            } else {
                throw new \Exception('Sender not found and not a test user');
            }
        }

        // 메시지 데이터 준비
        $messageData = array_merge([
            'sender_uuid' => $senderUuid,
            'sender_email' => $sender->email ?? 'unknown@example.com',
            'sender_name' => $sender->name ?? 'Unknown User',
            'sender_avatar' => $sender->avatar ?? null,
            'type' => 'text',
            'status' => 'sent',
            'is_system' => false,
        ], $data);

        // 답글인 경우 스레드 정보 설정
        if (isset($data['reply_to_message_id'])) {
            $replyToMessage = static::forRoom($roomCode, $roomId, $createdAt)->find($data['reply_to_message_id']);
            if ($replyToMessage) {
                $messageData['thread_root_id'] = $replyToMessage->thread_root_id ?? $replyToMessage->id;
            }
        }

        // 메시지 생성
        $message = static::forRoom($roomCode, $roomId, $createdAt)->create($messageData);

        // 스레드 카운트 업데이트
        if ($message->thread_root_id && $message->thread_root_id !== $message->id) {
            static::forRoom($roomCode, $roomId, $createdAt)->where('id', $message->thread_root_id)->increment('thread_count');
        }

        // 파일 타입 메시지인 경우 ChatRoomFile 레코드 생성
        if (in_array($message->type, ['image', 'video', 'audio', 'file', 'document']) && isset($data['media'])) {
            try {
                $media = $data['media'];

                $fileData = [
                    'message_id' => $message->id,
                    'uploader_uuid' => $senderUuid,
                    'uploader_name' => $sender->name ?? 'Unknown User',
                    'original_name' => $media['original_name'] ?? 'unknown',
                    'stored_name' => $media['file_name'] ?? basename($media['file_path'] ?? ''),
                    'file_path' => $media['file_path'] ?? '',
                    'file_type' => $media['file_type'] ?? $message->type,
                    'mime_type' => $media['mime_type'] ?? '',
                    'file_size' => $media['file_size'] ?? 0,
                    'is_public' => true,
                ];

                // ChatRoomFile 생성
                \Jiny\Chat\Models\ChatRoomFile::forRoom($roomCode, $roomId, $createdAt)->create($fileData);

                \Log::info('ChatRoomFile 레코드 생성 완료', [
                    'message_id' => $message->id,
                    'file_path' => $fileData['file_path'],
                    'file_type' => $fileData['file_type']
                ]);

            } catch (\Exception $e) {
                \Log::error('ChatRoomFile 생성 실패', [
                    'message_id' => $message->id,
                    'error' => $e->getMessage(),
                    'media_data' => $data['media'] ?? null
                ]);
            }
        }

        // 채팅방 통계 업데이트
        static::updateRoomStats($roomCode, $roomId, $createdAt);

        return $message;
    }

    /**
     * 시스템 메시지 생성
     */
    public static function createSystemMessage($roomCode, $content, $roomId, $createdAt = null, array $metadata = [])
    {
        // roomId 필수 검증
        if (!$roomId) {
            throw new \Exception("Room ID is required for system message creation. RoomCode: {$roomCode}");
        }

        return static::forRoom($roomCode, $roomId, $createdAt)->create([
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
        $this->update([
            'is_deleted' => true,
            'deleted_at' => now(),
            'deleted_by_uuid' => $deletedBy,
            'delete_reason' => $reason,
            'status' => 'deleted',
        ]);

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
        $user = \Shard::user($userUuid) ?? auth()->user();
        if (!$user) {
            return false;
        }

        // 읽음 상태 생성
        ChatRoomMessageRead::forRoom(static::getRoomCode())->create([
            'message_id' => $this->id,
            'user_uuid' => $userUuid,
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
                $reactions[$emoji] = array_values($reactions[$emoji]);

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
     * 채팅방 통계 업데이트
     */
    protected static function updateRoomStats($roomCode, $roomId, $createdAt = null)
    {
        // roomId 필수 검증
        if (!$roomId) {
            throw new \Exception("Room ID is required for room stats update. RoomCode: {$roomCode}");
        }

        $stats = ChatRoomStats::forRoom($roomCode, $roomId, $createdAt)->whereDate('date', now())->first();

        if ($stats) {
            $stats->increment('message_count');
        } else {
            ChatRoomStats::forRoom($roomCode, $roomId, $createdAt)->create([
                'date' => now()->toDateString(),
                'message_count' => 1,
                'participant_count' => 0,
                'file_count' => 0,
                'file_size_total' => 0,
                'hourly_stats' => json_encode(array_fill(0, 24, 0)),
                'user_activity' => json_encode([]),
            ]);
        }
    }

    /**
     * 특정 언어로 번역된 메시지 내용 가져오기
     */
    public function getTranslatedContent($languageCode, $encryptionKey = null)
    {
        $translation = $this->translations()->where('language_code', $languageCode)->first();

        if ($translation) {
            $content = $translation->translated_content;

            // 암호화된 경우 복호화
            if ($encryptionKey && $translation->encrypted_translated_content) {
                $content = $this->decryptMessage($translation->encrypted_translated_content, $encryptionKey);
            }

            return $content;
        }

        // 번역이 없으면 원본 내용 반환
        return $this->content;
    }

    /**
     * 메시지 암호화
     */
    public function encryptMessage($content, $key)
    {
        if (!$key) {
            return $content;
        }

        return base64_encode(str_rot13($content . $key));
    }

    /**
     * 메시지 복호화
     */
    public function decryptMessage($encryptedContent, $key)
    {
        if (!$key) {
            return $encryptedContent;
        }

        $decrypted = str_rot13(base64_decode($encryptedContent));
        return str_replace($key, '', $decrypted);
    }

    /**
     * 스코프: 텍스트 메시지
     */
    public function scopeText($query)
    {
        return $query->where('type', 'text');
    }

    /**
     * 스코프: 시스템 메시지
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * 스코프: 삭제되지 않은 메시지 (SoftDeletes와 is_deleted 플래그 모두 고려)
     */
    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false)->whereNull('deleted_at');
    }

    /**
     * 스코프: 발신자별
     */
    public function scopeBySender($query, $senderUuid)
    {
        return $query->where('sender_uuid', $senderUuid);
    }

    /**
     * 스코프: 기간별
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * 스코프: 스레드 메시지
     */
    public function scopeInThread($query, $threadRootId)
    {
        return $query->where('thread_root_id', $threadRootId);
    }
}