<?php
use Illuminate\Support\Facades\Route;

use Jiny\Chat\Http\Controllers\Home\Dashboard\ChatIndexController;
use Jiny\Chat\Http\Controllers\Home\Dashboard\ChatDeleteController;
use Jiny\Chat\Http\Controllers\Home\Dashboard\ChatCreateController;
use Jiny\Chat\Http\Controllers\Home\Room\ShowController;
use Jiny\Chat\Http\Controllers\Home\Room\MessageController;
use Jiny\Chat\Http\Controllers\Home\Room\SseController;
use Jiny\Chat\Http\Controllers\Home\Room\ImageGalleryController;
use Jiny\Chat\Http\Controllers\Home\Room\ImageDeleteController;
use Jiny\Chat\Http\Controllers\Home\ChatFileController;
use Jiny\Chat\Http\Controllers\Home\Invite\IndexController as InviteIndexController;
use Jiny\Chat\Http\Controllers\Home\Invite\RegenerateController as InviteRegenerateController;
use Jiny\Chat\Http\Controllers\Home\Invite\DeleteController as InviteDeleteController;

// 웹 인터페이스 라우트 (JWT 인증 필요)
Route::middleware(['web', 'jwt.auth'])->prefix('home/chat')->name('home.chat.')->group(function () {

    // 채팅 메인 대시보드 (SAC)
    Route::get('/', ChatIndexController::class)->name('index');

    // 채팅방 관리 라우트
    Route::prefix('rooms')->name('rooms.')->group(function () {
        // 채팅방 목록 (메인 대시보드와 동일)
        Route::get('/', ChatIndexController::class)->name('index');

        // 채팅방 생성 페이지
        Route::get('/create', function () {
            return view('jiny-chat::home.dashboard.create');
        })->name('create');

        // 채팅방 생성 처리 (SAC)
        Route::post('/', ChatCreateController::class)->name('store');

        // 채팅방 수정 페이지
        Route::get('/{roomId}/edit', [\Jiny\Chat\Http\Controllers\Home\Dashboard\ChatEditController::class, 'edit'])->name('edit');

        // 채팅방 수정 처리 (SAC)
        Route::put('/{roomId}', [\Jiny\Chat\Http\Controllers\Home\Dashboard\ChatEditController::class, 'update'])->name('update');


    });

    // 초대링크 관리 라우트
    Route::prefix('invite')->name('invite.')->group(function () {
        // 초대링크 관리 페이지
        Route::get('/', InviteIndexController::class)->name('index');

        // 초대링크 재발급/생성
        Route::post('/{roomId}/regenerate', InviteRegenerateController::class)->name('regenerate');

        // 초대링크 삭제
        Route::delete('/{roomId}', InviteDeleteController::class)->name('delete');
    });

    // 채팅방 입장/메시지 보기 (SAC)
    Route::get('/room/{roomId}', ShowController::class)->name('room.show');

    // 채팅방 메시지 관리
    Route::prefix('room/{roomId}/messages')->name('room.messages.')->group(function () {
        // 메시지 목록 조회 (AJAX) - 기존
        Route::get('/', [MessageController::class, 'index'])->name('index');

        // 메시지 목록 조회 (JSON API) - 새로운 분리된 컨트롤러
        Route::get('/list', [\Jiny\Chat\Http\Controllers\Home\Room\MessageListController::class, 'index'])->name('list');

        // 새 메시지 작성 (AJAX)
        Route::post('/', [MessageController::class, 'store'])->name('store');
    });

    // 폴링 시스템을 위한 메시지 API 라우트 (짧은 이름)
    Route::get('/room/{roomId}/messages', [\Jiny\Chat\Http\Controllers\Home\Room\MessageListController::class, 'index'])->name('room.messages');

    // Server-Sent Events (SSE) 실시간 채팅
    Route::prefix('room/{roomId}')->name('room.')->group(function () {
        // SSE 스트림 연결
        Route::get('/sse', [SseController::class, 'stream'])->name('sse');

        // 타이핑 상태 업데이트
        Route::post('/typing', [SseController::class, 'typing'])->name('typing');

        // 참여자 목록 조회
        Route::get('/participants', [SseController::class, 'participants'])->name('participants');


    });

    Route::prefix('room/{roomId}')->name('room.')->group(function () {

        // 이미지 갤러리
        Route::get('/images', ImageGalleryController::class)->name('images');

        // 이미지 파일 삭제 (방장 전용)
        Route::delete('/images/{fileHash}', [\Jiny\Chat\Http\Controllers\Home\Room\ImageDeleteController::class, 'destroy'])->name('images.delete');
    });

    // 채팅방 삭제 (SAC) - AJAX DELETE 요청
    Route::delete('/room/{roomId}', ChatDeleteController::class)->name('room.delete');

});

// 파일 관리 라우트 (별도 인증 처리 - 컨트롤러에서 직접 인증 확인)
Route::middleware(['web'])->prefix('home/chat/files')->name('home.chat.files.')->group(function () {
    // 파일 조회/표시 (이미지 인라인 표시)
    Route::get('/{fileId}/show', [ChatFileController::class, 'show'])->name('show');

    // 파일 다운로드
    Route::get('/{fileId}/download', [ChatFileController::class, 'download'])->name('download');

    // 이미지 썸네일 (미리보기)
    Route::get('/{fileId}/thumbnail', [ChatFileController::class, 'thumbnail'])->name('thumbnail');
});


