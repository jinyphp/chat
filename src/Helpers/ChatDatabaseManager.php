<?php

namespace Jiny\Chat\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

/**
 * ChatDatabaseManager - 채팅방별 독립 SQLite 데이터베이스 관리
 *
 * 각 채팅방마다 독립적인 SQLite 파일을 생성하고 관리합니다.
 * 메시지, 번역 데이터, 파일 정보 등을 채팅방별로 독립적으로 저장합니다.
 */
class ChatDatabaseManager
{
    /**
     * 채팅방 코드를 기반으로 SQLite 파일 경로 생성
     * 새로운 경로 규칙: database/chat/year/month/day/
     */
    public static function getChatDatabasePath($roomCode, $createdAt = null)
    {
        $basePath = database_path('chat');

        // 생성일 기준으로 년/월/일 디렉토리 구조 생성
        $date = $createdAt ? \Carbon\Carbon::parse($createdAt) : now();
        $year = $date->format('Y');
        $month = $date->format('m');
        $day = $date->format('d');

        $path = $basePath . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month . DIRECTORY_SEPARATOR . $day;

        return $path;
    }

    /**
     * 채팅방 SQLite 데이터베이스 파일 전체 경로 반환
     * 새로운 파일명 규칙: room-{id}.sqlite
     */
    public static function getChatDatabaseFile($roomCode, $roomId = null, $createdAt = null)
    {
        $path = self::getChatDatabasePath($roomCode, $createdAt);

        // roomId가 없으면 에러 발생 - 항상 room-{id}.sqlite 형태로만 생성
        if (!$roomId) {
            throw new \Exception("Room ID is required for SQLite database file creation. RoomCode: {$roomCode}");
        }

        $fileName = "room-{$roomId}.sqlite";
        return $path . DIRECTORY_SEPARATOR . $fileName;
    }

    /**
     * 동적 데이터베이스 연결 생성
     */
    public static function createChatConnection($roomCode, $roomId = null, $createdAt = null)
    {
        $dbFile = self::getChatDatabaseFile($roomCode, $roomId, $createdAt);

        // roomId 필수 - 항상 room ID 기반 연결명 사용
        if (!$roomId) {
            throw new \Exception("Room ID is required for database connection. RoomCode: {$roomCode}");
        }

        $connectionName = 'chat_room_' . $roomId;

        // 연결 설정
        config(['database.connections.' . $connectionName => [
            'driver' => 'sqlite',
            'database' => $dbFile,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        return $connectionName;
    }

    /**
     * 채팅방 데이터베이스 생성
     */
    public static function createChatDatabase($roomCode, $roomId = null, $createdAt = null)
    {
        $path = self::getChatDatabasePath($roomCode, $createdAt);
        $dbFile = self::getChatDatabaseFile($roomCode, $roomId, $createdAt);

        // 디렉토리 생성
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        // 이미 존재하는 경우 종료
        if (file_exists($dbFile)) {
            return false;
        }

        // SQLite 파일 생성
        file_put_contents($dbFile, '');

        // 동적 연결 생성
        $connectionName = self::createChatConnection($roomCode, $roomId, $createdAt);

        // 테이블 생성
        self::createChatTables($connectionName, $roomCode);

        \Log::info('새로운 채팅방 데이터베이스 생성', [
            'room_code' => $roomCode,
            'room_id' => $roomId,
            'db_file' => $dbFile,
            'connection_name' => $connectionName,
        ]);

        return $connectionName;
    }

    /**
     * 채팅방 데이터베이스 테이블 생성
     */
    protected static function createChatTables($connectionName, $roomCode)
    {
        // 메시지 테이블
        Schema::connection($connectionName)->create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes(); // SoftDeletes 지원을 위한 deleted_at 컬럼 추가

            // 발신자 정보
            $table->string('sender_uuid')->nullable();
            $table->string('sender_email')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_avatar')->nullable();

            // 메시지 정보
            $table->enum('type', ['text', 'image', 'file', 'voice', 'video', 'system'])->default('text');
            $table->text('content')->nullable();
            $table->text('encrypted_content')->nullable();
            $table->json('media')->nullable();
            $table->json('metadata')->nullable();

            // 답글 및 스레드
            $table->unsignedBigInteger('reply_to_message_id')->nullable();
            $table->unsignedBigInteger('thread_root_id')->nullable();
            $table->integer('thread_count')->default(0);

            // 상태 정보
            $table->enum('status', ['sent', 'delivered', 'read', 'edited', 'deleted'])->default('sent');
            $table->boolean('is_edited')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_system')->default(false);

            // 편집/삭제 정보
            $table->timestamp('edited_at')->nullable();
            $table->string('edited_by_uuid')->nullable();
            $table->string('deleted_by_uuid')->nullable();
            $table->string('delete_reason')->nullable();

            // 읽음 정보
            $table->integer('read_count')->default(0);
            $table->timestamp('first_read_at')->nullable();
            $table->timestamp('last_read_at')->nullable();

            // 반응 및 멘션
            $table->json('reactions')->nullable();
            $table->integer('reaction_count')->default(0);
            $table->json('mentions')->nullable();
            $table->json('tags')->nullable();

            // 인덱스
            $table->index(['created_at']);
            $table->index(['sender_uuid']);
            $table->index(['type']);
            $table->index(['status']);
            $table->index(['thread_root_id']);
            $table->index(['deleted_at']); // SoftDeletes 인덱스 추가
        });

        // 메시지 읽음 상태 테이블
        Schema::connection($connectionName)->create('chat_message_reads', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('message_id');
            $table->string('user_uuid');
            $table->string('user_email')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamp('read_at');
            $table->enum('read_type', ['read', 'delivered'])->default('read');

            $table->index(['message_id']);
            $table->index(['user_uuid']);
            $table->unique(['message_id', 'user_uuid']);
        });

        // 메시지 번역 테이블
        Schema::connection($connectionName)->create('chat_message_translations', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('message_id');
            $table->string('language_code'); // ko, en, ja, zh, etc.
            $table->text('original_content');
            $table->text('translated_content');
            $table->text('encrypted_translated_content')->nullable();
            $table->string('translation_service')->nullable(); // google, papago, etc.
            $table->json('translation_metadata')->nullable();

            $table->index(['message_id']);
            $table->index(['language_code']);
            $table->unique(['message_id', 'language_code']);
        });

        // 파일 정보 테이블
        Schema::connection($connectionName)->create('chat_files', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('uploader_uuid');
            $table->string('uploader_name')->nullable();

            // 파일 정보
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('file_path');
            $table->string('file_type');
            $table->string('mime_type');
            $table->bigInteger('file_size');
            $table->string('file_hash')->nullable();

            // 이미지/비디오 메타데이터
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('duration')->nullable(); // 초 단위

            // 썸네일 정보
            $table->string('thumbnail_path')->nullable();
            $table->string('preview_path')->nullable();

            // 접근 제어
            $table->boolean('is_public')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->json('access_permissions')->nullable();

            $table->index(['message_id']);
            $table->index(['uploader_uuid']);
            $table->index(['file_type']);
            $table->index(['created_at']);
        });

        // 메시지 즐겨찾기 테이블
        Schema::connection($connectionName)->create('chat_message_favourites', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('message_id');
            $table->string('user_uuid');
            $table->string('user_email')->nullable();
            $table->string('user_name')->nullable();
            $table->text('note')->nullable(); // 사용자 메모

            $table->index(['message_id']);
            $table->index(['user_uuid']);
            $table->unique(['message_id', 'user_uuid']);
        });

        // 채팅방 통계 테이블
        Schema::connection($connectionName)->create('chat_room_stats', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->date('date');
            $table->integer('message_count')->default(0);
            $table->integer('participant_count')->default(0);
            $table->integer('file_count')->default(0);
            $table->bigInteger('file_size_total')->default(0);
            $table->json('hourly_stats')->nullable(); // 시간별 통계
            $table->json('user_activity')->nullable(); // 사용자별 활동

            $table->unique(['date']);
            $table->index(['date']);
        });

        // 초기 통계 데이터 생성
        $connection = DB::connection($connectionName);
        $connection->table('chat_room_stats')->insert([
            'date' => now()->toDateString(),
            'message_count' => 0,
            'participant_count' => 0,
            'file_count' => 0,
            'file_size_total' => 0,
            'hourly_stats' => json_encode(array_fill(0, 24, 0)),
            'user_activity' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 채팅방 데이터베이스 연결 가져오기
     */
    public static function getChatConnection($roomCode, $roomId = null, $createdAt = null)
    {
        // roomId 필수 - 항상 room ID 기반 연결명 사용
        if (!$roomId) {
            throw new \Exception("Room ID is required for database connection. RoomCode: {$roomCode}");
        }

        $connectionName = 'chat_room_' . $roomId;
        $dbFile = self::getChatDatabaseFile($roomCode, $roomId, $createdAt);

        \Log::info('ChatDatabaseManager::getChatConnection 호출', [
            'room_code' => $roomCode,
            'room_id' => $roomId,
            'room_id_type' => gettype($roomId),
            'room_id_is_null' => is_null($roomId),
            'room_id_is_empty' => empty($roomId),
            'created_at' => $createdAt,
            'connection_name' => $connectionName,
            'db_file' => $dbFile
        ]);

        // 연결이 설정되어 있지 않은 경우 생성
        if (!config("database.connections.{$connectionName}")) {
            // 파일이 존재하지 않으면 생성
            if (!file_exists($dbFile)) {
                return self::createChatDatabase($roomCode, $roomId, $createdAt);
            }

            // 연결만 생성
            return self::createChatConnection($roomCode, $roomId, $createdAt);
        }

        return $connectionName;
    }

    /**
     * 채팅방 데이터베이스 삭제
     */
    public static function deleteChatDatabase($roomCode, $roomId = null, $createdAt = null)
    {
        $dbFile = self::getChatDatabaseFile($roomCode, $roomId, $createdAt);

        if (file_exists($dbFile)) {
            unlink($dbFile);

            // 연결 설정 제거 - roomId 필수
            if ($roomId) {
                $connectionName = 'chat_room_' . $roomId;
                config(["database.connections.{$connectionName}" => null]);
            }

            return true;
        }

        return false;
    }

    /**
     * 채팅방 데이터베이스 백업
     */
    public static function backupChatDatabase($roomCode, $roomId = null, $createdAt = null, $backupPath = null)
    {
        $dbFile = self::getChatDatabaseFile($roomCode, $roomId, $createdAt);

        if (!file_exists($dbFile)) {
            return false;
        }

        if (!$backupPath) {
            $backupPath = database_path('chat/backups');
        }

        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $filePrefix = $roomId ? "room-{$roomId}" : $roomCode;
        $backupFile = $backupPath . DIRECTORY_SEPARATOR . $filePrefix . '_' . date('Y-m-d_H-i-s') . '.sqlite';

        return copy($dbFile, $backupFile);
    }

    /**
     * 채팅방 데이터베이스 최적화
     */
    public static function optimizeChatDatabase($roomCode, $roomId = null, $createdAt = null)
    {
        $connectionName = self::getChatConnection($roomCode, $roomId, $createdAt);
        $connection = DB::connection($connectionName);

        // VACUUM으로 데이터베이스 최적화
        $connection->statement('VACUUM');

        // ANALYZE로 통계 업데이트
        $connection->statement('ANALYZE');

        return true;
    }

    /**
     * 채팅방 데이터베이스 크기 조회
     */
    public static function getChatDatabaseSize($roomCode, $roomId = null, $createdAt = null)
    {
        $dbFile = self::getChatDatabaseFile($roomCode, $roomId, $createdAt);

        if (file_exists($dbFile)) {
            return filesize($dbFile);
        }

        return 0;
    }

    /**
     * 모든 채팅방 데이터베이스 목록 조회
     */
    public static function getAllChatDatabases()
    {
        $basePath = database_path('chat');
        $databases = [];

        if (!is_dir($basePath)) {
            return $databases;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'sqlite') {
                $fileName = $file->getBasename('.sqlite');

                // 새로운 형식 room-{id}.sqlite 파싱
                if (preg_match('/^room-(\d+)$/', $fileName, $matches)) {
                    $roomId = $matches[1];
                    $roomCode = null; // DB에서 조회 필요
                } else {
                    $roomCode = $fileName;
                    $roomId = null;
                }

                // 경로에서 년/월/일 정보 추출
                $pathParts = explode(DIRECTORY_SEPARATOR, $file->getPath());
                $day = array_pop($pathParts);
                $month = array_pop($pathParts);
                $year = array_pop($pathParts);

                $databases[] = [
                    'room_id' => $roomId,
                    'room_code' => $roomCode,
                    'file_name' => $fileName,
                    'file_path' => $file->getPathname(),
                    'size' => $file->getSize(),
                    'created_date' => "{$year}-{$month}-{$day}",
                    'modified_at' => date('Y-m-d H:i:s', $file->getMTime()),
                ];
            }
        }

        return $databases;
    }

    /**
     * 특정 날짜의 채팅방 데이터베이스 목록 조회
     */
    public static function getChatDatabasesByDate($year, $month, $day)
    {
        $path = database_path("chat/{$year}/{$month}/{$day}");
        $databases = [];

        if (!is_dir($path)) {
            return $databases;
        }

        $files = glob($path . DIRECTORY_SEPARATOR . '*.sqlite');

        foreach ($files as $file) {
            $fileName = basename($file, '.sqlite');

            if (preg_match('/^room-(\d+)$/', $fileName, $matches)) {
                $roomId = $matches[1];
                $roomCode = null;
            } else {
                $roomCode = $fileName;
                $roomId = null;
            }

            $databases[] = [
                'room_id' => $roomId,
                'room_code' => $roomCode,
                'file_name' => $fileName,
                'file_path' => $file,
                'size' => filesize($file),
                'created_date' => "{$year}-{$month}-{$day}",
                'modified_at' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }

        return $databases;
    }

    /**
     * 특정 월의 채팅방 통계 조회
     */
    public static function getMonthlyStats($year, $month)
    {
        $path = database_path("chat/{$year}/{$month}");
        $stats = [
            'total_rooms' => 0,
            'total_size' => 0,
            'daily_stats' => [],
        ];

        if (!is_dir($path)) {
            return $stats;
        }

        $days = glob($path . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);

        foreach ($days as $dayPath) {
            $day = basename($dayPath);
            $databases = self::getChatDatabasesByDate($year, $month, $day);

            $dayStats = [
                'date' => "{$year}-{$month}-{$day}",
                'room_count' => count($databases),
                'total_size' => array_sum(array_column($databases, 'size')),
            ];

            $stats['daily_stats'][] = $dayStats;
            $stats['total_rooms'] += $dayStats['room_count'];
            $stats['total_size'] += $dayStats['total_size'];
        }

        return $stats;
    }
}