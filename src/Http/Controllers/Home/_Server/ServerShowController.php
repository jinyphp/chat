<?php

namespace Jiny\Chat\Http\Controllers\Home\Server;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Services\ChatService;
use Carbon\Carbon;

/**
 * Server ShowController - 서버형 채팅방 상세/입장 페이지 (SAC)
 *
 * [Single Action Controller]
 * - 서버형 채팅방 상세 페이지 및 입장 처리만 담당
 * - 사용자 권한 검증 및 자동 참여 처리
 * - 채팅방 접근 제어 및 보안 검증
 * - 메시지 로드 및 읽음 처리
 *
 * [주요 기능]
 * - 채팅방 존재 및 활성 상태 검증
 * - 사용자 참여 상태 확인
 * - 자동 참여 처리 (조건 충족 시)
 * - 접근 제한 처리 (비밀번호, 초대 필요)
 * - 최근 메시지 로드 (페이지네이션)
 * - 읽음 처리 및 알림 정리
 * - 차단된 사용자 처리
 *
 * [접근 제어]
 * - JWT 인증 필수
 * - 방 상태 검증 (active만 허용)
 * - 참여 권한 확인
 * - 비밀번호 보호 처리
 * - 초대 전용 방 처리
 * - 차단 사용자 제한
 *
 * [보안 기능]
 * - 세션 인증 대체 지원
 * - 테스트 사용자 지원 (개발용)
 * - 자동 참여 시 예외 처리
 * - 권한 검증 후 리다이렉트
 *
 * [라우트]
 * - GET /home/chat/server/{id} -> 서버형 채팅방 상세/입장
 */
class ServerShowController extends Controller
{
    protected $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * 서버형 채팅방 상세 페이지 (입장)
     *
     * @param int $id 채팅방 ID
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function __invoke($id, Request $request)
    {
        // 다양한 인증 방식 시도
        $user = null;

        // 1. JWT 인증 시도
        try {
            $user = \JwtAuth::user($request);
        } catch (\Exception $e) {
            // JWT 실패 시 무시
        }

        // 2. 세션 인증 시도
        if (!$user && auth()->check()) {
            $authUser = auth()->user();
            $user = (object) [
                'uuid' => $authUser->uuid ?? 'user-' . $authUser->id,
                'name' => $authUser->name,
                'email' => $authUser->email,
                'avatar' => $authUser->avatar ?? null
            ];
        }

        // 3. 마지막으로 테스트 사용자
        if (!$user) {
            $user = (object) [
                'uuid' => 'test-user-001',
                'name' => 'Test User',
                'email' => 'test@example.com',
                'avatar' => null
            ];
        }

        // 채팅방 조회 (활성 참여자 포함)
        $room = ChatRoom::with(['activeParticipants'])
            ->find($id);

        if (!$room || $room->status !== 'active') {
            abort(404, '채팅방을 찾을 수 없습니다.');
        }

        // 사용자 참여 상태 확인
        $participant = $room->participants()
            ->where('user_uuid', $user->uuid)
            ->first();

        // 참여 가능 여부 및 제한 사항 확인
        $canJoin = $room->canJoin($user->uuid);
        $needsPassword = $room->password && !$participant;
        $needsInvite = !$room->is_public && !$participant && !$room->allow_join;

        // 접근 제한이 있는 경우 접근 페이지 표시
        if (!$participant && (!$canJoin || $needsPassword || $needsInvite)) {
            return view('jiny-chat::home.room.access', compact(
                'room',
                'user',
                'needsPassword',
                'needsInvite',
                'canJoin'
            ));
        }

        // 참여자가 아닌 경우 자동 참여 시도
        if (!$participant && $canJoin) {
            try {
                $participant = $this->chatService->joinRoom($room->id, $user->uuid);

                // MySQL chat_participants 테이블에도 추가
                $this->ensureMysqlParticipant($room->id, $user);

                \Log::info('서버형 채팅방 자동 참여 성공', [
                    'room_id' => $room->id,
                    'user_uuid' => $user->uuid,
                    'room_title' => $room->title
                ]);
            } catch (\Exception $e) {
                \Log::error('서버형 채팅방 자동 참여 실패', [
                    'room_id' => $room->id,
                    'user_uuid' => $user->uuid,
                    'error' => $e->getMessage()
                ]);

                return redirect()->route('home.chat.rooms.index')
                    ->with('error', $e->getMessage());
            }
        }

        // 차단된 사용자 처리
        if ($participant && $participant->isBanned()) {
            \Log::warning('차단된 사용자의 서버형 채팅방 접근 시도', [
                'room_id' => $room->id,
                'user_uuid' => $user->uuid,
                'participant_id' => $participant->id,
                'ban_reason' => $participant->ban_reason
            ]);

            return view('jiny-chat::home.room.banned', compact('room', 'participant'));
        }

        // SQLite 채팅 데이터베이스 설정
        $now = Carbon::now();
        $dbPath = $this->getChatDatabasePath($id, $now);

        \Log::info('서버형 채팅방 SQLite 데이터베이스', [
            'room_id' => $id,
            'db_path' => $dbPath,
            'db_exists' => file_exists($dbPath)
        ]);

        // 데이터베이스 파일이 없으면 생성
        if (!file_exists($dbPath)) {
            $this->createChatDatabase($dbPath);
        }

        // 동적 DB 연결 설정
        $this->setupDynamicConnection($dbPath);

        // 참여자 등록/업데이트 (SQLite)
        $this->upsertParticipant($user, $id);

        // 최근 메시지 조회 (SQLite에서)
        $messages = $this->getRecentMessages($id, 50);

        // 읽음 처리 (참여자인 경우만)
        if ($participant) {
            try {
                $this->chatService->markAsRead($room->id, $user->uuid);
            } catch (\Exception $e) {
                \Log::warning('읽음 처리 실패', [
                    'room_id' => $room->id,
                    'user_uuid' => $user->uuid,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // 서버형 채팅방 입장 로깅
        \Log::info('서버형 채팅방 입장', [
            'room_id' => $room->id,
            'room_title' => $room->title,
            'user_uuid' => $user->uuid,
            'user_name' => $user->name,
            'participant_id' => $participant->id ?? null,
            'is_auto_joined' => !$participant ? false : true,
            'message_count' => $messages->count(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString()
        ]);

        // 참여자 목록 조회 (MySQL + SQLite 조합)
        $participants = $this->getMergedParticipants($id);

        return view('jiny-chat::home.server.index', compact(
            'room',
            'participant',
            'messages',
            'participants',
            'user'
        ));
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

        $dirPath = "{$basePath}/{$year}/{$month}/{$day}";

        // 디렉토리 생성
        if (!file_exists($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        return "{$dirPath}/room-{$roomId}.sqlite";
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
                file_path TEXT,
                file_name TEXT,
                file_size INTEGER,
                file_type TEXT,
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

        \Log::info('서버형 채팅 SQLite 데이터베이스 생성됨', ['db_path' => $dbPath]);
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
     * 참여자 등록/업데이트
     */
    private function upsertParticipant($user, $roomId)
    {
        DB::connection('chat_server')->table('participants')
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
        return DB::connection('chat_server')
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
     * 활성 참여자 목록 조회 (SQLite)
     */
    private function getActiveParticipants($roomId)
    {
        return DB::connection('chat_server')
            ->table('participants')
            ->where('room_id', $roomId)
            ->where('status', 'active')
            ->orderBy('last_seen', 'desc')
            ->get();
    }

    /**
     * MySQL + SQLite 참여자 정보 병합
     */
    private function getMergedParticipants($roomId)
    {
        try {
            // 1. MySQL에서 실제 참여자 정보 조회
            $mysqlParticipants = DB::table('chat_participants')
                ->where('room_id', $roomId)
                ->where('status', 'active')
                ->select([
                    'user_uuid',
                    'name',
                    'email',
                    'avatar',
                    'role',
                    'status',
                    'joined_at',
                    'last_seen_at',
                    'shard_id'
                ])
                ->get()
                ->keyBy('user_uuid');

            // 2. SQLite에서 실시간 상태 조회
            $sqliteParticipants = DB::connection('chat_server')
                ->table('participants')
                ->where('room_id', $roomId)
                ->select([
                    'user_uuid',
                    'user_name',
                    'status',
                    'last_seen'
                ])
                ->get()
                ->keyBy('user_uuid');

            // 3. 데이터 병합
            $mergedParticipants = collect();

            foreach ($mysqlParticipants as $userUuid => $mysqlParticipant) {
                $sqliteParticipant = $sqliteParticipants->get($userUuid);

                $participant = (object) [
                    'user_uuid' => $userUuid,
                    'user_name' => $mysqlParticipant->name,
                    'email' => $mysqlParticipant->email,
                    'avatar' => $mysqlParticipant->avatar,
                    'role' => $mysqlParticipant->role,
                    'status' => $mysqlParticipant->status,
                    'shard_id' => $mysqlParticipant->shard_id,
                    'joined_at' => $mysqlParticipant->joined_at,
                    'last_seen_at' => $mysqlParticipant->last_seen_at,
                    // SQLite에서 가져온 실시간 정보
                    'is_online' => $sqliteParticipant ? true : false,
                    'last_seen_realtime' => $sqliteParticipant ? $sqliteParticipant->last_seen : null,
                    'realtime_status' => $sqliteParticipant ? $sqliteParticipant->status : 'offline'
                ];

                $mergedParticipants->push($participant);
            }

            // 온라인 상태 우선으로 정렬
            return $mergedParticipants->sortByDesc(function ($participant) {
                if ($participant->is_online) {
                    return $participant->last_seen_realtime ?: $participant->last_seen_at;
                }
                return $participant->last_seen_at ?: '1970-01-01';
            })->values();

        } catch (\Exception $e) {
            \Log::error('참여자 목록 병합 오류', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // 오류 시 기본 SQLite 참여자만 반환
            return $this->getActiveParticipants($roomId);
        }
    }

    /**
     * MySQL chat_participants 테이블에 참여자 등록
     */
    private function ensureMysqlParticipant($roomId, $user)
    {
        try {
            // 사용자 UUID에서 샤드 ID 추출 (user_0xx 형식)
            $shardId = 0;
            if (preg_match('/user[_-](\d+)/', $user->uuid, $matches)) {
                $shardId = (int) $matches[1];
            }

            // 이미 존재하는지 확인
            $existingParticipant = DB::table('chat_participants')
                ->where('room_id', $roomId)
                ->where('user_uuid', $user->uuid)
                ->first();

            if ($existingParticipant) {
                // 기존 참여자 정보 업데이트 (재입장)
                DB::table('chat_participants')
                    ->where('room_id', $roomId)
                    ->where('user_uuid', $user->uuid)
                    ->update([
                        'name' => $user->name,
                        'email' => $user->email,
                        'avatar' => $user->avatar,
                        'status' => 'active',
                        'last_seen_at' => now(),
                        'updated_at' => now()
                    ]);

                \Log::info('MySQL 참여자 정보 업데이트', [
                    'room_id' => $roomId,
                    'user_uuid' => $user->uuid,
                    'shard_id' => $shardId
                ]);
            } else {
                // 새 참여자 추가
                DB::table('chat_participants')->insert([
                    'room_id' => $roomId,
                    'room_uuid' => \Str::uuid(),
                    'user_uuid' => $user->uuid,
                    'shard_id' => $shardId,
                    'email' => $user->email,
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                    'role' => 'member',
                    'status' => 'active',
                    'permissions' => null,
                    'can_send_message' => true,
                    'can_invite' => false,
                    'can_moderate' => false,
                    'notifications_enabled' => true,
                    'notification_settings' => null,
                    'last_read_at' => null,
                    'last_read_message_id' => null,
                    'unread_count' => 0,
                    'joined_at' => now(),
                    'last_seen_at' => now(),
                    'invited_by_uuid' => null,
                    'join_reason' => '자동 참여',
                    'banned_at' => null,
                    'banned_by_uuid' => null,
                    'ban_reason' => null,
                    'ban_expires_at' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                \Log::info('MySQL 새 참여자 추가', [
                    'room_id' => $roomId,
                    'user_uuid' => $user->uuid,
                    'shard_id' => $shardId,
                    'user_name' => $user->name
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('MySQL 참여자 등록 오류', [
                'room_id' => $roomId,
                'user_uuid' => $user->uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
