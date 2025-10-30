<?php

use Illuminate\Support\Facades\Route;
use Jiny\Chat\Http\Controllers\Home\ChatController;
use Jiny\Chat\Http\Controllers\Home\Room\CreateController;
use Jiny\Chat\Http\Controllers\Home\Room\StoreController;
use Jiny\Chat\Http\Controllers\MessageController;
use Jiny\Chat\Http\Controllers\ChatFileController;

/**
 * Jiny Chat 기능 라우트
 *
 * 실제 채팅 기능과 관련된 라우트들
 * - 채팅방 관리
 * - 메시지 송수신
 * - 참여자 관리
 * - 사용자 설정
 */

// 웹 인터페이스 라우트 (JWT 인증 필요)
Route::middleware(['web', 'jwt'])->prefix('home/chat')->name('home.chat.')->group(function () {
    // 채팅 메인 대시보드
    Route::get('/', [ChatController::class, 'index'])->name('index');



    // 채팅방 관련
    Route::prefix('rooms')->name('rooms.')->group(function () {
        // 채팅방 목록
        Route::get('/', [ChatController::class, 'rooms'])->name('index');

        // 채팅방 생성 (Single Action Controllers)
        Route::get('/create', CreateController::class)->name('create');
        Route::post('/create', StoreController::class)->name('store');

        // 채팅방 입장 및 관리
        Route::get('/{id}', [ChatController::class, 'room'])->name('show');
        Route::get('/{id}/edit', [ChatController::class, 'edit'])->name('edit');
        Route::post('/{id}/join', [ChatController::class, 'joinRoom'])->name('join');
        Route::post('/{id}/leave', [ChatController::class, 'leaveRoom'])->name('leave');
    });

    // 채팅방 별칭 라우트
    Route::get('/room/{id}', [ChatController::class, 'room'])->name('room');

    // 채팅방 설정 (owner 전용)
    Route::get('/room/{id}/settings', [ChatController::class, 'roomSettings'])->name('room.settings');
    Route::post('/room/{id}/settings', [ChatController::class, 'updateRoomSettings'])->name('room.settings.update');

    // 설정
    Route::get('/settings', [ChatController::class, 'settings'])->name('settings');
    Route::post('/settings', [ChatController::class, 'updateSettings'])->name('settings.update');

    // 초대 링크 관리
    Route::get('/invites', [ChatController::class, 'invites'])->name('invites');
    Route::post('/rooms/{id}/regenerate-invite', [ChatController::class, 'regenerateInviteCode'])->name('rooms.regenerate-invite');
});



// 웹 인터페이스 라우트 (JWT 인증 필요)
Route::middleware(['web', 'jwt'])->prefix('chat')->name('chat.')->group(function () {
    // 파일 다운로드 및 미리보기
    Route::get('/file/{fileUuid}/download', [ChatFileController::class, 'download'])->name('file.download');
    Route::get('/file/{fileUuid}/preview', [ChatFileController::class, 'preview'])->name('file.preview');
});

// API 라우트 (JWT 인증 필요)
Route::middleware(['api', 'jwt'])->prefix('api/chat')->name('api.chat.')->group(function () {

    // 채팅방 API
    Route::prefix('rooms')->name('rooms.')->group(function () {
        // 채팅방 목록 및 관리
        Route::get('/', [ChatController::class, 'rooms'])->name('index');
        Route::post('/', [ChatController::class, 'storeRoom'])->name('store');
        Route::get('/{id}', [ChatController::class, 'room'])->name('show');
        Route::post('/{id}/join', [ChatController::class, 'joinRoom'])->name('join');
        Route::post('/{id}/leave', [ChatController::class, 'leaveRoom'])->name('leave');

        // 채팅방 메시지 목록
        Route::get('/{id}/messages', [MessageController::class, 'index'])->name('messages');
        Route::get('/{id}/messages/search', [MessageController::class, 'search'])->name('messages.search');

        // 읽음 처리
        Route::post('/{id}/read', [MessageController::class, 'markRoomAsRead'])->name('read');
    });

    // 메시지 API
    Route::prefix('messages')->name('messages.')->group(function () {
        // 메시지 CRUD
        Route::post('/', [MessageController::class, 'store'])->name('store');
        Route::get('/{id}', [MessageController::class, 'show'])->name('show');
        Route::put('/{id}', [MessageController::class, 'update'])->name('update');
        Route::delete('/{id}', [MessageController::class, 'destroy'])->name('delete');

        // 메시지 상호작용
        Route::post('/{id}/read', [MessageController::class, 'markAsRead'])->name('read');
        Route::post('/{id}/reaction', [MessageController::class, 'toggleReaction'])->name('reaction');
        Route::post('/{id}/pin', [MessageController::class, 'togglePin'])->name('pin');
    });

    // 사용자 설정
    Route::get('/settings', [ChatController::class, 'settings'])->name('settings');
    Route::post('/settings', [ChatController::class, 'updateSettings'])->name('settings.update');
});


// 초대 링크 라우트 (인증 불필요)
Route::get('/chat/invite/{code}', function ($code, \Illuminate\Http\Request $request) {
    $room = \Jiny\Chat\Models\ChatRoom::where('invite_code', $code)
        ->where('status', 'active')
        ->first();

    if (!$room) {
        abort(404, '초대 링크가 유효하지 않습니다.');
    }

    // 로그인되어 있으면 바로 참여 처리
    $user = \JwtAuth::user($request);
    if ($user) {
        return redirect()->route('chat.room', $room->id)
            ->with('invite_code', $code);
    }

    // 로그인 페이지로 리다이렉트 (초대 코드 유지)
    return redirect()->route('login')
        ->with('redirect_to', route('chat.room', $room->id))
        ->with('invite_code', $code)
        ->with('message', "{$room->title} 채팅방에 초대되었습니다. 로그인 후 참여하세요.");

})->name('chat.invite');


// WebSocket 이벤트 처리 (Broadcasting)
if (config('broadcasting.default') !== 'null') {
    Broadcast::channel('chat-room.{roomId}', function ($user, $roomId) {
        // 사용자가 해당 채팅방에 참여하고 있는지 확인
        $participant = \Jiny\Chat\Models\ChatParticipant::where('room_id', $roomId)
            ->where('user_uuid', $user->uuid)
            ->where('status', 'active')
            ->first();

        return $participant ? [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'avatar' => $user->avatar,
        ] : false;
    });

    Broadcast::channel('chat-user.{userUuid}', function ($user, $userUuid) {
        // 사용자 개인 채널 (알림, 초대 등)
        return $user->uuid === $userUuid ? [
            'uuid' => $user->uuid,
            'name' => $user->name,
        ] : false;
    });
}
