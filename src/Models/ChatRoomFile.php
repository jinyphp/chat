<?php

namespace Jiny\Chat\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

/**
 * ChatRoomFile - 채팅방별 독립 파일 모델
 */
class ChatRoomFile extends ChatRoomModel
{
    use HasFactory;

    protected $table = 'chat_files';

    protected $fillable = [
        'message_id',
        'uploader_uuid',
        'uploader_name',
        'original_name',
        'stored_name',
        'file_path',
        'file_type',
        'mime_type',
        'file_size',
        'file_hash',
        'width',
        'height',
        'duration',
        'thumbnail_path',
        'preview_path',
        'is_public',
        'expires_at',
        'access_permissions',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'expires_at' => 'datetime',
        'access_permissions' => 'array',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        // No UUID auto-generation for independent database
    }

    /**
     * 메시지 관계
     */
    public function message()
    {
        return $this->belongsTo(ChatRoomMessage::class, 'message_id');
    }

    /**
     * 파일 업로드 및 저장
     */
    public static function uploadFile($roomCode, UploadedFile $file, $uploaderUuid, $messageId = null, $roomId = null, $createdAt = null)
    {
        // 사용자 조회 (테스트 환경 호환성을 위해 여러 방법 시도)
        $uploader = null;

        // 1. Shard 시스템 시도
        if (class_exists('\Shard') && method_exists('\Shard', 'user')) {
            try {
                $uploader = \Shard::user($uploaderUuid);
            } catch (\Exception $e) {
                // Shard 조회 실패시 무시
            }
        }

        // 2. 일반 User 모델 시도
        if (!$uploader) {
            $uploader = \App\Models\User::where('uuid', $uploaderUuid)->first();
        }

        // 3. 인증된 사용자 시도
        if (!$uploader) {
            $uploader = auth()->user();
        }

        if (!$uploader) {
            throw new \Exception('Uploader not found');
        }

        // 파일 정보 추출
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();
        $fileHash = hash_file('sha256', $file->getPathname());

        // 저장 파일명 생성
        $storedName = uniqid() . '_' . time() . '.' . $extension;

        // 파일 타입 결정
        $fileType = static::determineFileType($mimeType);

        // 채팅방별 저장 경로
        $storagePath = "chat/{$roomCode}/files/" . date('Y/m');
        $filePath = $file->storeAs($storagePath, $storedName, 'public');

        // 파일 정보 저장
        $fileData = [
            'message_id' => $messageId,
            'uploader_uuid' => $uploaderUuid,
            'uploader_name' => $uploader->name,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'file_path' => $filePath,
            'file_type' => $fileType,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'file_hash' => $fileHash,
        ];

        // 이미지/비디오인 경우 메타데이터 추출
        if (in_array($fileType, ['image', 'video'])) {
            $metadata = static::extractMediaMetadata($file);
            $fileData = array_merge($fileData, $metadata);
        }

        // 메시지가 없는 경우 파일 메시지 생성
        if (!$messageId) {
            $messageData = [
                'content' => $originalName,
                'type' => $fileType,
                'sender_uuid' => $uploaderUuid,
                'sender_name' => $uploader->name,
            ];

            $message = ChatRoomMessage::forRoom($roomCode, $roomId, $createdAt)->create($messageData);
            $fileData['message_id'] = $message->id;
        }

        $chatFile = static::forRoom($roomCode, $roomId, $createdAt)->create($fileData);

        // 메시지와 연결
        if (isset($message)) {
            $chatFile->message = $message;
        }

        // 썸네일 생성
        if ($fileType === 'image') {
            static::generateThumbnail($chatFile);
        }

        // 채팅방 통계 업데이트
        static::updateFileStats($roomCode, $fileSize, $roomId, $createdAt);

        return $chatFile;
    }

    /**
     * 파일 타입 결정
     */
    protected static function determineFileType($mimeType)
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        } elseif (in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ])) {
            return 'document';
        } elseif (str_starts_with($mimeType, 'text/')) {
            return 'text';
        } else {
            return 'other';
        }
    }

    /**
     * 미디어 메타데이터 추출
     */
    protected static function extractMediaMetadata(UploadedFile $file)
    {
        $metadata = [];

        try {
            if (str_starts_with($file->getMimeType(), 'image/')) {
                $imageInfo = getimagesize($file->getPathname());
                if ($imageInfo) {
                    $metadata['width'] = $imageInfo[0];
                    $metadata['height'] = $imageInfo[1];
                }
            }
            // 비디오 메타데이터는 FFmpeg 등을 사용하여 추출 가능
        } catch (\Exception $e) {
            \Log::warning('Failed to extract media metadata: ' . $e->getMessage());
        }

        return $metadata;
    }

    /**
     * 썸네일 생성
     */
    protected static function generateThumbnail($chatFile)
    {
        try {
            if ($chatFile->file_type === 'image') {
                $sourcePath = Storage::disk('public')->path($chatFile->file_path);
                $thumbnailDir = dirname($chatFile->file_path) . '/thumbnails';
                $thumbnailName = 'thumb_' . $chatFile->stored_name;
                $thumbnailPath = $thumbnailDir . '/' . $thumbnailName;

                // 썸네일 디렉토리 생성
                Storage::disk('public')->makeDirectory($thumbnailDir);

                // 간단한 썸네일 생성 (실제로는 Image Intervention 등 사용 권장)
                $thumbnailFullPath = Storage::disk('public')->path($thumbnailPath);

                if (function_exists('imagecreatefromjpeg')) {
                    static::createSimpleThumbnail($sourcePath, $thumbnailFullPath, 200, 200);
                    $chatFile->update(['thumbnail_path' => $thumbnailPath]);
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to generate thumbnail: ' . $e->getMessage());
        }
    }

    /**
     * 간단한 썸네일 생성 함수
     */
    protected static function createSimpleThumbnail($source, $destination, $width, $height)
    {
        $imageInfo = getimagesize($source);
        $mimeType = $imageInfo['mime'];

        // 원본 이미지 로드
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($source);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($source);
                break;
            default:
                return false;
        }

        if (!$sourceImage) return false;

        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);

        // 비율 계산
        $ratio = min($width / $originalWidth, $height / $originalHeight);
        $newWidth = intval($originalWidth * $ratio);
        $newHeight = intval($originalHeight * $ratio);

        // 썸네일 이미지 생성
        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

        // 썸네일 저장
        $result = imagejpeg($thumbnail, $destination, 80);

        // 메모리 해제
        imagedestroy($sourceImage);
        imagedestroy($thumbnail);

        return $result;
    }

    /**
     * 파일 통계 업데이트
     */
    protected static function updateFileStats($roomCode, $fileSize, $roomId = null, $createdAt = null)
    {
        if (class_exists('Jiny\Chat\Models\ChatRoomStats')) {
            $stats = ChatRoomStats::forRoom($roomCode, $roomId, $createdAt)->whereDate('date', now())->first();

            if ($stats) {
                $stats->increment('file_count');
                $stats->increment('file_size_total', $fileSize);
            } else {
                ChatRoomStats::forRoom($roomCode, $roomId, $createdAt)->create([
                    'date' => now()->toDateString(),
                    'message_count' => 0,
                    'participant_count' => 0,
                    'file_count' => 1,
                    'file_size_total' => $fileSize,
                    'hourly_stats' => json_encode(array_fill(0, 24, 0)),
                    'user_activity' => json_encode([]),
                ]);
            }
        }
    }

    /**
     * 파일 삭제
     */
    public function deleteFile()
    {
        try {
            // 실제 파일 삭제
            if (Storage::disk('public')->exists($this->file_path)) {
                Storage::disk('public')->delete($this->file_path);
            }

            // 썸네일 삭제
            if ($this->thumbnail_path && Storage::disk('public')->exists($this->thumbnail_path)) {
                Storage::disk('public')->delete($this->thumbnail_path);
            }

            // 미리보기 파일 삭제
            if ($this->preview_path && Storage::disk('public')->exists($this->preview_path)) {
                Storage::disk('public')->delete($this->preview_path);
            }

            // DB 레코드 삭제
            $this->delete();

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to delete file: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 파일 다운로드 URL 생성
     */
    public function getDownloadUrl()
    {
        return Storage::disk('public')->url($this->file_path);
    }

    /**
     * 썸네일 URL 생성
     */
    public function getThumbnailUrl()
    {
        if ($this->thumbnail_path) {
            return Storage::disk('public')->url($this->thumbnail_path);
        }

        return null;
    }

    /**
     * 파일 크기를 읽기 쉬운 형태로 변환
     */
    public function getReadableFileSize()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * 스코프: 파일 타입별
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('file_type', $type);
    }

    /**
     * 스코프: 업로더별
     */
    public function scopeByUploader($query, $uploaderUuid)
    {
        return $query->where('uploader_uuid', $uploaderUuid);
    }

    /**
     * 스코프: 공개 파일
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * 스코프: 만료되지 않은 파일
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * 파일 ID로 파일 검색 (모든 SQLite 데이터베이스 검색)
     */
    public static function findById($fileId)
    {
        try {
            // 모든 채팅방을 조회하여 각 SQLite 데이터베이스에서 파일 검색
            $chatRooms = \Jiny\Chat\Models\ChatRoom::all();

            foreach ($chatRooms as $room) {
                try {
                    $file = static::forRoom(
                        $room->code,
                        $room->id,
                        $room->created_at
                    )->find($fileId);

                    if ($file) {
                        // 파일을 찾았으면 room 정보를 추가
                        $file->room_code = $room->code;
                        $file->room_id = $room->id;
                        $file->room_created_at = $room->created_at;
                        return $file;
                    }
                } catch (\Exception $e) {
                    // 특정 방의 데이터베이스에서 오류가 발생해도 계속 검색
                    \Log::debug('Failed to search file in room: ' . $room->code, [
                        'error' => $e->getMessage(),
                        'file_id' => $fileId
                    ]);
                    continue;
                }
            }

            return null;
        } catch (\Exception $e) {
            \Log::error('Failed to find file by ID', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}