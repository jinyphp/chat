<?php

use Illuminate\Support\Facades\Route;
use Jiny\Chat\Http\Controllers\ChatRoomTestController;

/*
|--------------------------------------------------------------------------
| 독립 채팅방 데이터베이스 테스트 라우트
|--------------------------------------------------------------------------
|
| 각 채팅방별로 독립적인 SQLite 데이터베이스를 테스트하기 위한 라우트입니다.
|
*/

Route::prefix('chat/test')->name('chat.test.')->group(function () {

    // 테스트 페이지
    Route::get('/', [ChatRoomTestController::class, 'testPage'])
        ->name('page');

    // 채팅방 생성
    Route::post('/create-room', [ChatRoomTestController::class, 'createTestRoom'])
        ->name('create-room');

    // 메시지 전송
    Route::post('/{roomCode}/send-message', [ChatRoomTestController::class, 'sendTestMessage'])
        ->name('send-message');

    // 메시지 조회
    Route::get('/{roomCode}/messages', [ChatRoomTestController::class, 'getMessages'])
        ->name('messages');

    // 통계 조회
    Route::get('/{roomCode}/stats', [ChatRoomTestController::class, 'getRoomStats'])
        ->name('stats');

    // 연결 테스트
    Route::get('/{roomCode}/test-connection', [ChatRoomTestController::class, 'testConnection'])
        ->name('test-connection');

    // 데이터베이스 목록
    Route::get('/databases', [ChatRoomTestController::class, 'listAllDatabases'])
        ->name('databases');

    // 데이터 마이그레이션
    Route::post('/migrate/{roomId}', [ChatRoomTestController::class, 'migrateRoom'])
        ->name('migrate');

    // 데이터베이스 백업
    Route::post('/{roomCode}/backup', [ChatRoomTestController::class, 'backupDatabase'])
        ->name('backup');

    // 데이터베이스 최적화
    Route::post('/{roomCode}/optimize', [ChatRoomTestController::class, 'optimizeDatabase'])
        ->name('optimize');
});