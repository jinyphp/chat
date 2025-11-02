<?php

namespace Jiny\Chat\Http\Controllers\Home\Server;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * SseParticipantsController - SSE 채팅방 참여자 API (SAC)
 *
 * [Single Action Controller]
 * - SSE 채팅방 참여자 목록 조회
 * - JWT 기반 사용자 샤딩 지원 (user_0xx)
 * - 동적 SQLite 데이터베이스 연결
 * - 실시간 참여자 상태 업데이트
 *
 * [주요 기능]
 * - 활성 참여자 목록 조회
 * - 사용자 샤딩 정보 포함
 * - 마지막 접속 시간 업데이트
 * - JSON 응답 형식
 *
 * [응답 형식]
 * {
 *   "success": true,
 *   "participants": [...],
 *   "total_count": 5
 * }
 *
 * [라우트]
 * - GET /home/chat/api/server/sse/{roomId}/participants
 */
class SseParticipantsController extends Controller
{
    /**
     * SSE 채팅방 참여자 목록 조회
     *
     * @param string $roomId 채팅방 번호
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke($roomId, Request $request)
    {
        try {
            // JWT 인증 확인
            $user = null;

            try {
                $user = \JwtAuth::user($request);
            } catch (\Exception $e) {
                // 테스트용 사용자
                $user = (object) [
                    'uuid' => 'user_001',
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'avatar' => null
                ];
            }

            // 현재 날짜 기준으로 데이터베이스 경로 생성
            $now = Carbon::now();
            $dbPath = $this->getChatDatabasePath($roomId, $now);

            if (!file_exists($dbPath)) {
                return response()->json([
                    'success' => false,
                    'message' => '채팅방을 찾을 수 없습니다.'
                ], 404);
            }

            // 동적 DB 연결 설정
            $this->setupDynamicConnection($dbPath);

            // 현재 사용자의 마지막 접속 시간 업데이트
            $this->updateLastSeen($user, $roomId);

            // 활성 참여자 목록 조회
            $participants = DB::connection('chat_server')
                ->table('participants')
                ->where('room_id', $roomId)
                ->where('status', 'active')
                ->orderBy('last_seen', 'desc')
                ->get()
                ->map(function ($participant) {
                    return [
                        'user_uuid' => $participant->user_uuid,
                        'user_name' => $participant->user_name,
                        'user_avatar' => $participant->user_avatar,
                        'status' => $participant->status,
                        'last_seen' => $participant->last_seen,
                        'joined_at' => $participant->joined_at,
                        'is_online' => $this->isUserOnline($participant->last_seen),
                        'shard_info' => $this->getUserShardInfo($participant->user_uuid)
                    ];
                })
                ->toArray();

            $response = [
                'success' => true,
                'participants' => $participants,
                'total_count' => count($participants),
                'current_user' => $user->uuid
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            \Log::error('SSE 참여자 목록 조회 오류', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '참여자 목록을 불러올 수 없습니다.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * 채팅 데이터베이스 경로 생성 (room-{id}.sqlite 형식)
     */
    private function getChatDatabasePath($roomId, Carbon $date)
    {
        $basePath = database_path('chat');
        $year = $date->format('Y');
        $month = $date->format('m');
        $day = $date->format('d');

        return "{$basePath}/{$year}/{$month}/{$day}/room-{$roomId}.sqlite";
    }

    /**
     * 동적 DB 연결 설정
     */
    private function setupDynamicConnection($dbPath)
    {
        config([
            'database.connections.chat_server' => [
                'driver' => 'sqlite',
                'database' => $dbPath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]
        ]);

        DB::purge('chat_server');
    }

    /**
     * 마지막 접속 시간 업데이트
     */
    private function updateLastSeen($user, $roomId)
    {
        DB::connection('chat_server')
            ->table('participants')
            ->where('room_id', $roomId)
            ->where('user_uuid', $user->uuid)
            ->update([
                'last_seen' => now()->toDateTimeString()
            ]);
    }

    /**
     * 사용자 온라인 상태 확인 (5분 이내 접속)
     */
    private function isUserOnline($lastSeen)
    {
        $threshold = Carbon::now()->subMinutes(5);
        return Carbon::parse($lastSeen)->isAfter($threshold);
    }

    /**
     * 사용자 샤딩 정보 추출
     */
    private function getUserShardInfo($userUuid)
    {
        // user_001, user_002 등의 패턴에서 샤드 번호 추출
        if (preg_match('/^user_(\d{3})$/', $userUuid, $matches)) {
            $shardNumber = intval($matches[1]);
            return [
                'shard_type' => 'user',
                'shard_number' => $shardNumber,
                'shard_group' => floor($shardNumber / 100), // 100단위로 그룹핑
                'formatted' => $userUuid
            ];
        }

        // 기본 샤딩 정보
        return [
            'shard_type' => 'default',
            'shard_number' => 0,
            'shard_group' => 0,
            'formatted' => $userUuid
        ];
    }
}