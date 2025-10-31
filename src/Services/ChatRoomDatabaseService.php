<?php

namespace Jiny\Chat\Services;

use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatRoomMessage;
use Jiny\Chat\Models\ChatRoomFile;
use Jiny\Chat\Models\ChatRoomMessageTranslation;
use Jiny\Chat\Models\ChatRoomMessageFavourite;
use Jiny\Chat\Models\ChatRoomStats;
use Jiny\Chat\Helpers\ChatDatabaseManager;
use Illuminate\Http\UploadedFile;

/**
 * ChatRoomDatabaseService - 독립적인 채팅방 데이터베이스 서비스
 *
 * 각 채팅방별로 독립적인 SQLite 데이터베이스를 관리하는 서비스 클래스입니다.
 */
class ChatRoomDatabaseService
{
    /**
     * 새 채팅방 생성 (독립 데이터베이스 포함)
     */
    public function createChatRoom(array $data)
    {
        // 채팅방 생성
        $room = ChatRoom::createRoom($data);

        \Log::info('독립 데이터베이스 채팅방 생성 성공', [
            'room_id' => $room->id,
            'room_code' => $room->code,
            'room_uuid' => $room->uuid,
            'title' => $room->title,
            'database_size' => $room->getDatabaseSize(),
        ]);

        return $room;
    }

    /**
     * 메시지 전송
     */
    public function sendMessage($roomCode, $senderUuid, array $messageData)
    {
        $message = ChatRoomMessage::createMessage($roomCode, $senderUuid, $messageData);

        \Log::info('독립 데이터베이스 메시지 전송 성공', [
            'room_code' => $roomCode,
            'message_id' => $message->id,
            'sender_uuid' => $senderUuid,
            'type' => $message->type,
            'content_length' => strlen($message->content),
        ]);

        return $message;
    }

    /**
     * 파일 업로드
     */
    public function uploadFile($roomCode, UploadedFile $file, $uploaderUuid, $messageId = null)
    {
        $chatFile = ChatRoomFile::uploadFile($roomCode, $file, $uploaderUuid, $messageId);

        \Log::info('독립 데이터베이스 파일 업로드 성공', [
            'room_code' => $roomCode,
            'file_id' => $chatFile->id,
            'uploader_uuid' => $uploaderUuid,
            'original_name' => $chatFile->original_name,
            'file_size' => $chatFile->file_size,
            'file_type' => $chatFile->file_type,
        ]);

        return $chatFile;
    }

    /**
     * 메시지 번역
     */
    public function translateMessage($roomCode, $messageId, $languageCode, $translatedContent, $options = [])
    {
        $translation = ChatRoomMessageTranslation::translateMessage($roomCode, $messageId, $languageCode, $translatedContent, $options);

        \Log::info('독립 데이터베이스 메시지 번역 성공', [
            'room_code' => $roomCode,
            'message_id' => $messageId,
            'language_code' => $languageCode,
            'translation_service' => $options['service'] ?? 'manual',
        ]);

        return $translation;
    }

    /**
     * 메시지 자동 번역
     */
    public function autoTranslateMessage($roomCode, $messageId, $targetLanguage, $sourceLanguage = null)
    {
        return ChatRoomMessageTranslation::autoTranslate($roomCode, $messageId, $targetLanguage, $sourceLanguage);
    }

    /**
     * 즐겨찾기 토글
     */
    public function toggleFavourite($roomCode, $messageId, $userUuid, $note = null)
    {
        $result = ChatRoomMessageFavourite::toggleFavourite($roomCode, $messageId, $userUuid, $note);

        \Log::info('독립 데이터베이스 즐겨찾기 토글', [
            'room_code' => $roomCode,
            'message_id' => $messageId,
            'user_uuid' => $userUuid,
            'is_favourite' => $result,
        ]);

        return $result;
    }

    /**
     * 메시지 목록 조회
     */
    public function getMessages($roomCode, $limit = 50, $offset = 0)
    {
        return ChatRoomMessage::forRoom($roomCode)
            ->where('is_deleted', false)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * 스레드 메시지 조회
     */
    public function getThreadMessages($roomCode, $threadRootId)
    {
        return ChatRoomMessage::forRoom($roomCode)
            ->inThread($threadRootId)
            ->where('is_deleted', false)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * 파일 목록 조회
     */
    public function getFiles($roomCode, $fileType = null, $limit = 50)
    {
        $query = ChatRoomFile::forRoom($roomCode)->notExpired();

        if ($fileType) {
            $query->ofType($fileType);
        }

        return $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 사용자 즐겨찾기 목록 조회
     */
    public function getUserFavourites($roomCode, $userUuid)
    {
        return ChatRoomMessageFavourite::getUserFavourites($roomCode, $userUuid);
    }

    /**
     * 채팅방 통계 조회
     */
    public function getRoomStats($roomCode, $days = 7)
    {
        return [
            'peak_hours' => ChatRoomStats::getPeakHours($roomCode, $days),
            'active_users' => ChatRoomStats::getActiveUsers($roomCode, $days),
            'monthly_summary' => ChatRoomStats::getMonthlySummary($roomCode, now()->year, now()->month),
        ];
    }

    /**
     * 데이터베이스 관리 기능
     */
    public function getDatabaseSize($roomCode)
    {
        return ChatDatabaseManager::getChatDatabaseSize($roomCode);
    }

    public function backupDatabase($roomCode, $backupPath = null)
    {
        return ChatDatabaseManager::backupChatDatabase($roomCode, $backupPath);
    }

    public function optimizeDatabase($roomCode)
    {
        return ChatDatabaseManager::optimizeChatDatabase($roomCode);
    }

    public function deleteDatabase($roomCode)
    {
        return ChatDatabaseManager::deleteChatDatabase($roomCode);
    }

    /**
     * 모든 채팅방 데이터베이스 목록 조회
     */
    public function getAllDatabases()
    {
        return ChatDatabaseManager::getAllChatDatabases();
    }

    /**
     * 메시지 검색
     */
    public function searchMessages($roomCode, $keyword, $limit = 50)
    {
        return ChatRoomMessage::forRoom($roomCode)
            ->where('content', 'LIKE', "%{$keyword}%")
            ->where('is_deleted', false)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 기간별 메시지 조회
     */
    public function getMessagesByPeriod($roomCode, $startDate, $endDate)
    {
        return ChatRoomMessage::forRoom($roomCode)
            ->betweenDates($startDate, $endDate)
            ->where('is_deleted', false)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * 사용자별 메시지 조회
     */
    public function getMessagesByUser($roomCode, $userUuid, $limit = 50)
    {
        return ChatRoomMessage::forRoom($roomCode)
            ->bySender($userUuid)
            ->where('is_deleted', false)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 데이터베이스 연결 테스트
     */
    public function testDatabaseConnection($roomCode)
    {
        try {
            $connectionName = ChatDatabaseManager::getChatConnection($roomCode);

            // 간단한 쿼리 실행 테스트
            $result = ChatRoomMessage::forRoom($roomCode)->count();

            return [
                'success' => true,
                'connection_name' => $connectionName,
                'message_count' => $result,
                'database_size' => $this->getDatabaseSize($roomCode),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 채팅방 데이터 마이그레이션 (기존 단일 테이블에서 독립 데이터베이스로)
     */
    public function migrateChatRoomData($roomId)
    {
        try {
            // 기존 채팅방 조회
            $room = ChatRoom::find($roomId);
            if (!$room || !$room->code) {
                throw new \Exception('채팅방을 찾을 수 없거나 room code가 없습니다.');
            }

            // 독립 데이터베이스가 없으면 생성
            $dbFile = ChatDatabaseManager::getChatDatabaseFile($room->code, $room->id, $room->created_at);
            if (!file_exists($dbFile)) {
                ChatDatabaseManager::createChatDatabase($room->code, $room->id, $room->created_at);
            }

            // 기존 메시지 마이그레이션
            $existingMessages = \Jiny\Chat\Models\ChatMessage::where('room_id', $roomId)->get();
            $migratedMessages = 0;

            foreach ($existingMessages as $message) {
                $messageData = [
                    'sender_uuid' => $message->sender_uuid,
                    'sender_email' => $message->sender_email,
                    'sender_name' => $message->sender_name,
                    'content' => $message->content,
                    'type' => $message->type,
                    'status' => $message->status,
                    'created_at' => $message->created_at,
                    'updated_at' => $message->updated_at,
                ];

                ChatRoomMessage::forRoom($room->code)->create($messageData);
                $migratedMessages++;
            }

            \Log::info('채팅방 데이터 마이그레이션 완료', [
                'room_id' => $roomId,
                'room_code' => $room->code,
                'migrated_messages' => $migratedMessages,
            ]);

            return [
                'success' => true,
                'migrated_messages' => $migratedMessages,
                'database_size' => $this->getDatabaseSize($room->code),
            ];

        } catch (\Exception $e) {
            \Log::error('채팅방 데이터 마이그레이션 실패', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}