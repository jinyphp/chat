<?php

use Illuminate\Support\Facades\Route;
use Jiny\Chat\Http\Controllers\Home\Server\MessageSendController;
use Jiny\Chat\Http\Controllers\Home\Server\SseMessageSendController;

// /**
//  * Jiny Chat API 라우트
//  *
//  * [라우트 구조]
//  * - RESTful API 엔드포인트
//  * - JSON 요청/응답
//  * - 세션 및 JWT 인증 지원
//  * - 서버형 채팅방 전용 API
//  *
//  * [접근 권한]
//  * - 세션 기반 인증 (우선)
//  * - JWT 토큰 인증 (보조)
//  * - CSRF 토큰 검증
//  */

// // API 라우트 (세션 기반 인증, CSRF 보호 포함)
// Route::middleware(['web'])->prefix('api/chat')->name('api.chat.')->group(function () {

//     // 서버형 채팅방 API
//     Route::prefix('server')->name('server.')->group(function () {

//         // 서버 채팅방 메시지 전송 - SAC
//         Route::post('/{id}/message', MessageSendController::class)->name('message.send');

//         // SSE 방식 메시지 전송 - SAC (실시간 채팅용)
//         Route::post('/sse/{roomId}/message', SseMessageSendController::class)->name('sse.message.send');
//     });
// });

// // 순수 JWT API 라우트 (CSRF 없음, JWT 인증만)
// Route::middleware(['api'])->prefix('api/v1/chat')->name('api.v1.chat.')->group(function () {

//     // JWT 전용 서버형 채팅방 API
//     Route::prefix('server')->name('server.')->group(function () {

//         // JWT 메시지 전송 - SAC
//         Route::post('/{id}/message', MessageSendController::class)->name('message.send');
//     });
// });
