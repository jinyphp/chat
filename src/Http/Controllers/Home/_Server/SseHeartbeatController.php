<?php

namespace Jiny\Chat\Http\Controllers\Home\Server;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * SseHeartbeatController - SSE 연결 상태 관리 컨트롤러 (SAC)
 *
 * [Single Action Controller]
 * - SSE 클라이언트의 연결 상태 추적
 * - 하트비트 신호 처리
 * - 비활성 연결 정리
 * - 참여자 온라인 상태 업데이트
 *
 * [주요 기능]
 * - 참여자 last_seen 업데이트
 * - 비활성 참여자 상태 변경
 * - 연결 통계 제공
 * - 자동 정리 기능
 *
 * [라우트]
 * - POST /home/chat/api/server/sse/{roomId}/heartbeat
 */
class SseHeartbeatController extends Controller
{
    /**
     * SSE 하트비트 처리
     *
     * @param string $roomId 채팅방 번호
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke($roomId, Request $request)
    {
        try {
            // 사용자 인증 확인
            $user = $this->getUser($request);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => '인증이 필요합니다.'
                ], 401);
            }

            // SQLite 데이터베이스 연결 설정
            $dbPath = $this->getChatDatabasePath($roomId, Carbon::now());
            $this->setupDynamicConnection($dbPath);

            // 참여자 상태 업데이트
            $updated = DB::connection('chat_sse')->table('participants')
                ->where('room_id', $roomId)
                ->where('user_uuid', $user->uuid)
                ->update([
                    'last_seen' => now()->toDateTimeString(),
                    'status' => 'active'
                ]);

            // 비활성 참여자 정리 (5분 이상 비활성)
            $inactiveThreshold = now()->subMinutes(5)->toDateTimeString();
            $inactiveUpdated = DB::connection('chat_sse')->table('participants')
                ->where('room_id', $roomId)
                ->where('last_seen', '<', $inactiveThreshold)
                ->where('status', 'active')
                ->update(['status' => 'away']);

            // 현재 활성 참여자 수 조회
            $activeCount = DB::connection('chat_sse')->table('participants')
                ->where('room_id', $roomId)
                ->where('status', 'active')
                ->count();

            return response()->json([
                'success' => true,
                'message' => '하트비트 처리 완료',
                'data' => [
                    'user_updated' => $updated > 0,
                    'inactive_cleaned' => $inactiveUpdated,
                    'active_participants' => $activeCount,
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('SSE 하트비트 처리 오류', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '하트비트 처리 중 오류가 발생했습니다.'
            ], 500);
        }
    }

    /**
     * 사용자 인증 정보 조회
     */
    private function getUser(Request $request)
    {
        // 1. 세션 인증 시도
        if (auth()->check()) {
            $authUser = auth()->user();
            return (object) [
                'uuid' => $authUser->uuid ?? 'user-' . $authUser->id,
                'name' => $authUser->name,
                'email' => $authUser->email,
                'avatar' => $authUser->avatar ?? null
            ];
        }

        // 2. JWT 인증 시도
        try {
            return \JwtAuth::user($request);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 채팅 데이터베이스 경로 생성
     */
    private function getChatDatabasePath($roomId, Carbon $date)
    {
        $basePath = database_path('chat');
        $year = $date->format('Y');
        $month = $date->format('m');
        $day = $date->format('d');

        $dirPath = "{$basePath}/{$year}/{$month}/{$day}";

        if (!file_exists($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        return "{$dirPath}/room-{$roomId}.sqlite";
    }

    /**
     * 동적 DB 연결 설정
     */
    private function setupDynamicConnection($dbPath)
    {
        config([
            'database.connections.chat_sse' => [
                'driver' => 'sqlite',
                'database' => $dbPath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]
        ]);

        DB::purge('chat_sse');
    }
}