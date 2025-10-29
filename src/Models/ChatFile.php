<?php

namespace Jiny\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChatFile extends Model
{
    protected $fillable = [
        'uuid',
        'message_id',
        'room_uuid',
        'uploader_uuid',
        'original_name',
        'file_name',
        'file_path',
        'file_type',
        'mime_type',
        'file_size',
        'storage_path',
        'metadata',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
        'file_size' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * 메시지와의 관계
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    /**
     * 파일 타입 확인
     */
    public function isImage(): bool
    {
        return $this->file_type === 'image';
    }

    public function isDocument(): bool
    {
        return $this->file_type === 'document';
    }

    public function isVideo(): bool
    {
        return $this->file_type === 'video';
    }

    public function isAudio(): bool
    {
        return $this->file_type === 'audio';
    }

    /**
     * 파일 크기를 사람이 읽기 쉬운 형태로 변환
     */
    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * 다운로드 URL 생성
     */
    public function getDownloadUrlAttribute(): string
    {
        return route('chat.file.download', $this->uuid);
    }

    /**
     * 파일 타입에 따른 아이콘 클래스
     */
    public function getIconClassAttribute(): string
    {
        return match ($this->file_type) {
            'image' => 'fas fa-image text-success',
            'document' => 'fas fa-file-alt text-primary',
            'video' => 'fas fa-video text-danger',
            'audio' => 'fas fa-music text-warning',
            default => 'fas fa-file text-secondary',
        };
    }

    /**
     * 계층화된 저장 경로 생성
     */
    public static function generateStoragePath(string $roomUuid): string
    {
        $hash = hash('md5', $roomUuid);
        $level1 = substr($hash, 0, 2);
        $level2 = substr($hash, 2, 1);
        $level3 = substr($hash, 3, 2);

        $dateFolder = now()->format('Y/m/d');

        return "{$level1}/{$level2}/{$level3}/{$dateFolder}";
    }

    /**
     * MIME 타입에서 파일 타입 결정
     */
    public static function determineFileType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        } else {
            return 'document';
        }
    }
}