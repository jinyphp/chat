<?php

namespace Jiny\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Jiny\Chat\Helpers\ChatDatabaseManager;

/**
 * ChatRoomModel - 채팅방별 독립 데이터베이스 모델 베이스 클래스
 *
 * 각 채팅방마다 독립적인 SQLite 데이터베이스를 사용하는 모델들의 베이스 클래스입니다.
 */
abstract class ChatRoomModel extends Model
{
    /**
     * 현재 사용 중인 채팅방 코드
     */
    protected static $currentRoomCode = null;

    /**
     * 채팅방 코드 설정
     */
    public static function setRoomCode($roomCode)
    {
        static::$currentRoomCode = $roomCode;
    }

    /**
     * 현재 채팅방 코드 가져오기
     */
    public static function getRoomCode()
    {
        return static::$currentRoomCode;
    }

    /**
     * 현재 Room ID (새로운 경로 구조용)
     */
    protected static $currentRoomId = null;

    /**
     * 현재 생성일 (새로운 경로 구조용)
     */
    protected static $currentCreatedAt = null;

    /**
     * Room ID와 생성일 설정
     */
    public static function setRoomInfo($roomCode, $roomId = null, $createdAt = null)
    {
        static::$currentRoomCode = $roomCode;
        static::$currentRoomId = $roomId;
        static::$currentCreatedAt = $createdAt;
    }

    /**
     * 동적 연결명 생성
     */
    public function getConnectionName()
    {
        if (static::$currentRoomCode) {
            return ChatDatabaseManager::getChatConnection(
                static::$currentRoomCode,
                static::$currentRoomId,
                static::$currentCreatedAt
            );
        }

        return parent::getConnectionName();
    }

    /**
     * 특정 채팅방에 대한 새 쿼리 빌더 생성
     */
    public static function forRoom($roomCode, $roomId = null, $createdAt = null)
    {
        $instance = new static;
        static::setRoomInfo($roomCode, $roomId, $createdAt);

        // 데이터베이스 연결 확인 및 생성 (새로운 경로 구조 지원)
        $connectionName = ChatDatabaseManager::getChatConnection($roomCode, $roomId, $createdAt);
        $instance->setConnection($connectionName);

        return $instance->newQuery();
    }

    /**
     * 채팅방 전환
     */
    public function switchToRoom($roomCode)
    {
        static::setRoomCode($roomCode);
        $connectionName = ChatDatabaseManager::getChatConnection($roomCode);
        $this->setConnection($connectionName);

        return $this;
    }

    /**
     * 부팅 시 동적 연결 설정
     */
    protected static function boot()
    {
        parent::boot();

        // 모델 생성 시 현재 설정된 채팅방 코드 사용
        static::creating(function ($model) {
            if (static::$currentRoomCode) {
                $connectionName = ChatDatabaseManager::getChatConnection(static::$currentRoomCode);
                $model->setConnection($connectionName);
            }
        });
    }
}