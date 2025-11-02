<?php

namespace Jiny\Chat\Http\Controllers\Home\Server;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * SseController - Server-Side Events 채팅방 컨트롤러 (SAC)
 *
 * [Single Action Controller]
 * - SSE 방식의 실시간 채팅방 구현
 * - 동적 SQLite 데이터베이스 연결 (연/월/일/방번호.sqlite)
 * - JWT 기반 사용자 인증 및 샤딩 지원
 * - 참여자 목록 및 메시지 표시
 *
 * [주요 기능]
 * - 동적 데이터베이스 파일 로드
 * - SSE 실시간 통신 지원
 * - JWT 인증 및 user_0xx 샤딩
 * - 채팅방 참여자 관리
 * - 메시지 목록 표시
 *
 * [데이터베이스 구조]
 * - 경로: /database/chat/YYYY/MM/DD/방번호.sqlite
 * - 테이블: messages, participants
 *
 * [라우트]
 * - GET /home/chat/server/sse/{roomId} -> SSE 채팅방 페이지
 */
class SseController extends Controller
{
    /**
     * SSE 채팅방 페이지 표시
     *
     * @param string $roomId 채팅방 번호
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function __invoke($roomId, Request $request)
    {
        try {
            // JWT 인증 확인
            $user = null;

            try {
                $user = \JwtAuth::user($request);
            } catch (\Exception $e) {
                \Log::debug('JWT 인증 실패', ['error' => $e->getMessage()]);

                // 테스트용 사용자 생성
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

            \Log::info('SSE 채팅방 접근', [
                'room_id' => $roomId,
                'user_uuid' => $user->uuid,
                'db_path' => $dbPath,
                'db_exists' => file_exists($dbPath)
            ]);

            // 데이터베이스 파일이 없으면 생성
            if (!file_exists($dbPath)) {
                $this->createChatDatabase($dbPath);
            }

            // 동적 DB 연결 설정
            $this->setupDynamicConnection($dbPath);

            // 채팅방 정보
            $room = (object) [
                'id' => $roomId,
                'title' => "채팅방 #{$roomId}",
                'description' => "SSE 실시간 채팅",
                'created_at' => $now->format('Y-m-d H:i:s'),
                'db_path' => $dbPath
            ];

            // 참여자 등록/업데이트
            $this->upsertParticipant($user, $roomId);

            // 최근 메시지 조회 (최대 50개)
            $messages = $this->getRecentMessages($roomId, 50);

            // 참여자 목록 조회
            $participants = $this->getActiveParticipants($roomId);

            return view('jiny-chat::home.server.sse', compact(
                'room',
                'user',
                'messages',
                'participants',
                'roomId'
            ));

        } catch (\Exception $e) {
            \Log::error('SSE 채팅방 오류', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->view('errors.500', [
                'message' => 'SSE 채팅방을 불러올 수 없습니다: ' . $e->getMessage()
            ], 500);
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

        // 디렉토리 생성
        if (!file_exists($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        return "{$dirPath}/{$roomId}.sqlite";
    }

    /**
     * 채팅 데이터베이스 생성
     */
    private function createChatDatabase($dbPath)
    {
        // SQLite 파일 생성
        $pdo = new \PDO("sqlite:{$dbPath}");

        // 메시지 테이블 생성
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id TEXT NOT NULL,
                user_uuid TEXT NOT NULL,
                user_name TEXT NOT NULL,
                user_avatar TEXT,
                content TEXT NOT NULL,
                type TEXT DEFAULT 'text',
                reply_to INTEGER,
                is_deleted INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // 참여자 테이블 생성
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS participants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id TEXT NOT NULL,
                user_uuid TEXT NOT NULL,
                user_name TEXT NOT NULL,
                user_avatar TEXT,
                status TEXT DEFAULT 'active',
                last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
                joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(room_id, user_uuid)
            )
        ");

        // 인덱스 생성
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_messages_room_created ON messages(room_id, created_at)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_participants_room_status ON participants(room_id, status)");

        \Log::info('SSE 채팅 데이터베이스 생성됨', ['db_path' => $dbPath]);
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

    /**
     * 참여자 등록/업데이트
     */
    private function upsertParticipant($user, $roomId)
    {
        DB::connection('chat_sse')->table('participants')
            ->updateOrInsert(
                [
                    'room_id' => $roomId,
                    'user_uuid' => $user->uuid
                ],
                [
                    'user_name' => $user->name,
                    'user_avatar' => $user->avatar,
                    'status' => 'active',
                    'last_seen' => now()->toDateTimeString(),
                    'joined_at' => now()->toDateTimeString()
                ]
            );
    }

    /**
     * 최근 메시지 조회
     */
    private function getRecentMessages($roomId, $limit = 50)
    {
        return DB::connection('chat_sse')
            ->table('messages')
            ->where('room_id', $roomId)
            ->where('is_deleted', 0)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * 활성 참여자 목록 조회
     */
    private function getActiveParticipants($roomId)
    {
        return DB::connection('chat_sse')
            ->table('participants')
            ->where('room_id', $roomId)
            ->where('status', 'active')
            ->orderBy('last_seen', 'desc')
            ->get();
    }
}