<?php

use Illuminate\Support\Facades\Route;

/**
 * Jiny Chat 관리자 라우트
 *
 * [라우트 구조]
 * - 관리자 전용 채팅 관리 기능
 * - 채팅방, 메시지, 사용자 관리
 * - 통계 및 모니터링 기능
 *
 * [접근 권한]
 * - admin 미들웨어 필요
 * - 관리자 권한 확인
 */

// 관리자 라우트 (관리자 권한 필요)
Route::middleware(['web', 'admin'])->prefix('admin/chat')->name('admin.chat.')->group(function () {

    // 채팅방 관리
    Route::prefix('rooms')->name('rooms.')->group(function () {
        Route::get('/', function () {
            return view('jiny-chat::admin.rooms.index');
        })->name('index');

        Route::get('/{id}', function ($id) {
            return view('jiny-chat::admin.rooms.show', compact('id'));
        })->name('show');

        Route::post('/{id}/close', function ($id) {
            // 채팅방 강제 종료
            $room = \Jiny\Chat\Models\ChatRoom::find($id);
            if ($room) {
                $room->update(['status' => 'archived']);
            }
            return back()->with('success', '채팅방이 종료되었습니다.');
        })->name('close');
    });

    // 메시지 관리
    Route::prefix('messages')->name('messages.')->group(function () {
        Route::get('/', function () {
            return view('jiny-chat::admin.messages.index');
        })->name('index');

        Route::delete('/{id}', function ($id) {
            // 메시지 강제 삭제
            $message = \Jiny\Chat\Models\ChatMessage::find($id);
            if ($message) {
                $message->deleteMessage(auth()->id(), '관리자 삭제');
            }
            return response()->json(['success' => true]);
        })->name('delete');
    });

    // 사용자 관리
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', function () {
            return view('jiny-chat::admin.users.index');
        })->name('index');

        Route::post('/{userUuid}/ban', function ($userUuid) {
            // 사용자 전체 채팅 차단
            // TODO: 구현
            return back()->with('success', '사용자가 차단되었습니다.');
        })->name('ban');

        Route::post('/{userUuid}/unban', function ($userUuid) {
            // 사용자 차단 해제
            // TODO: 구현
            return back()->with('success', '사용자 차단이 해제되었습니다.');
        })->name('unban');
    });

    // 통계
    Route::get('/stats', function () {
        $stats = [
            'total_rooms' => \Jiny\Chat\Models\ChatRoom::count(),
            'active_rooms' => \Jiny\Chat\Models\ChatRoom::where('status', 'active')->count(),
            'total_messages' => \Jiny\Chat\Models\ChatMessage::count(),
            'today_messages' => \Jiny\Chat\Models\ChatMessage::whereDate('created_at', today())->count(),
            'total_participants' => \Jiny\Chat\Models\ChatParticipant::where('status', 'active')->count(),
        ];

        return view('jiny-chat::admin.stats', compact('stats'));
    })->name('stats');
});
