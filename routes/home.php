<?php

use Illuminate\Support\Facades\Route;
use Jiny\Chat\Http\Controllers\Home\ChatController;
use Jiny\Chat\Http\Controllers\Home\Room\CreateController;
use Jiny\Chat\Http\Controllers\Home\Room\StoreController;
use Jiny\Chat\Http\Controllers\Home\ChatServerController;
use Jiny\Chat\Http\Controllers\Home\Dashboard\IndexController;
use Jiny\Chat\Http\Controllers\Home\Room\IndexController as RoomIndexController;
use Jiny\Chat\Http\Controllers\Home\Room\SettingsController;
use Jiny\Chat\Http\Controllers\Home\Room\UpdateSettingsController;
use Jiny\Chat\Http\Controllers\Home\Room\ShowController;
use Jiny\Chat\Http\Controllers\Home\Room\EditController;
use Jiny\Chat\Http\Controllers\Home\Room\JoinController;
use Jiny\Chat\Http\Controllers\Home\Room\LeaveController;
use Jiny\Chat\Http\Controllers\Home\Invite\IndexController as InviteIndexController;
use Jiny\Chat\Http\Controllers\Home\Invite\RegenerateController;
use Jiny\Chat\Http\Controllers\Home\Setting\IndexController as SettingIndexController;
use Jiny\Chat\Http\Controllers\Home\Setting\UpdateController as SettingUpdateController;
use Jiny\Chat\Http\Controllers\Home\ChatFile\DownloadController;
use Jiny\Chat\Http\Controllers\Home\ChatFile\PreviewController;
use Jiny\Chat\Http\Controllers\Home\Message\StoreController as MessageStoreController;
use Jiny\Chat\Http\Controllers\Home\Message\IndexController as MessageIndexController;
use Jiny\Chat\Http\Controllers\Home\Message\UpdateController as MessageUpdateController;
use Jiny\Chat\Http\Controllers\Home\Message\DestroyController as MessageDestroyController;
use Jiny\Chat\Http\Controllers\Home\Message\ShowController as MessageShowController;
use Jiny\Chat\Http\Controllers\Home\Message\MarkAsReadController;
use Jiny\Chat\Http\Controllers\Home\Message\MarkRoomAsReadController;
use Jiny\Chat\Http\Controllers\Home\Message\ToggleReactionController;
use Jiny\Chat\Http\Controllers\Home\Message\TogglePinController;
use Jiny\Chat\Http\Controllers\Home\Message\SearchController;

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

    // 채팅 메인 대시보드 (SAC)
    Route::get('/', IndexController::class)->name('index');



    // 채팅방 관련
    Route::prefix('rooms')->name('rooms.')->group(function () {
        // 채팅방 목록 (SAC)
        Route::get('/', RoomIndexController::class)->name('index');

        // 채팅방 생성 (Single Action Controllers)
        Route::get('/create', CreateController::class)->name('create');
        Route::post('/create', StoreController::class)->name('store');

        // 채팅방 입장 및 관리 - SAC
        Route::get('/{id}', ShowController::class)->name('show');
        Route::get('/{id}/edit', EditController::class)->name('edit');
        Route::post('/{id}/join', JoinController::class)->name('join');
        Route::post('/{id}/leave', LeaveController::class)->name('leave');
    });

    // 채팅방 별칭 라우트 (Livewire Polling 방식) - SAC
    Route::get('/room/{id}', ShowController::class)->name('room');

    // 웹 기반 API 엔드포인트 (세션 인증 사용)
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/rooms', RoomIndexController::class)->name('rooms');
    });


    // 채팅방 설정 (owner 전용) - SAC
    Route::get('/room/{id}/settings', SettingsController::class)->name('room.settings');
    Route::post('/room/{id}/settings', UpdateSettingsController::class)->name('room.settings.update');

    // 설정 - SAC
    Route::get('/settings', SettingIndexController::class)->name('settings');
    Route::post('/settings', SettingUpdateController::class)->name('settings.update');

    // 초대 링크 관리 - SAC
    Route::get('/invites', InviteIndexController::class)->name('invites');
    Route::post('/rooms/{id}/regenerate-invite', RegenerateController::class)->name('rooms.regenerate-invite');

});



// 웹 인터페이스 라우트 (JWT 인증 필요)
Route::middleware(['web', 'jwt'])->prefix('chat')->name('chat.')->group(function () {
    // 파일 다운로드 및 미리보기 - SAC
    Route::get('/file/{fileUuid}/download', DownloadController::class)->name('file.download');
    Route::get('/file/{fileUuid}/preview', PreviewController::class)->name('file.preview');
});

// API 라우트 (JWT 인증 필요)
Route::middleware(['api', 'jwt'])->prefix('api/chat')->name('api.chat.')->group(function () {

    // 채팅방 API
    Route::prefix('rooms')->name('rooms.')->group(function () {
        // 채팅방 목록 및 관리 (SAC)
        Route::get('/', RoomIndexController::class)->name('index');
        Route::post('/', [ChatController::class, 'storeRoom'])->name('store');
        Route::get('/{id}', ShowController::class)->name('show');
        Route::post('/{id}/join', JoinController::class)->name('join');
        Route::post('/{id}/leave', LeaveController::class)->name('leave');

        // 채팅방 메시지 목록 - SAC
        Route::get('/{id}/messages', MessageIndexController::class)->name('messages');
        Route::get('/{id}/messages/search', SearchController::class)->name('messages.search');

        // 읽음 처리 - SAC
        Route::post('/{id}/read', MarkRoomAsReadController::class)->name('read');
    });

    // 메시지 API - SAC
    Route::prefix('messages')->name('messages.')->group(function () {
        // 메시지 CRUD - SAC
        Route::post('/', MessageStoreController::class)->name('store');
        Route::get('/{id}', MessageShowController::class)->name('show');
        Route::put('/{id}', MessageUpdateController::class)->name('update');
        Route::delete('/{id}', MessageDestroyController::class)->name('delete');

        // 메시지 상호작용 - SAC
        Route::post('/{id}/read', MarkAsReadController::class)->name('read');
        Route::post('/{id}/reaction', ToggleReactionController::class)->name('reaction');
        Route::post('/{id}/pin', TogglePinController::class)->name('pin');
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
