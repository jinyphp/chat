<?php

namespace Jiny\Chat\Http\Controllers\Home\Room;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Events\MessageSent;

/**
 * MessageController - 채팅 메시지 작성 및 관리
 */
class MessageController extends Controller
{
    /**
     * 새 메시지 작성
     */
    public function store(Request $request, $roomId)
    {
        // JWT 인증된 사용자 정보 가져오기
        $authUser = auth()->user();
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => '로그인이 필요합니다.'
            ], 401);
        }

        $user = (object) [
            'uuid' => $authUser->uuid ?? 'user-' . $authUser->id,
            'name' => $authUser->name ?? 'Unknown User',
            'email' => $authUser->email ?? 'unknown@example.com'
        ];

        // 입력 데이터 검증
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'message_type' => 'sometimes|string|in:text,image,file,system',
            'reply_to_id' => 'sometimes|integer|nullable'
        ]);

        try {
            // 1. 채팅방 조회
            $room = ChatRoom::findOrFail($roomId);

            // 2. 사용자 참여 여부 확인
            $participant = ChatParticipant::where('room_id', $room->id)
                ->where('user_uuid', $user->uuid)
                ->where('status', 'active')
                ->first();

            if (!$participant) {
                return response()->json([
                    'success' => false,
                    'message' => '이 채팅방에 참여하지 않았습니다.'
                ], 403);
            }

            // 3. 메시지 전송 권한 확인
            if (!$participant->can_send_message) {
                return response()->json([
                    'success' => false,
                    'message' => '메시지 전송 권한이 없습니다.'
                ], 403);
            }

            // 4. SQLite에 메시지 저장
            $messageId = $this->saveMessageToSqlite($room, $user, $validated);

            if (!$messageId) {
                return response()->json([
                    'success' => false,
                    'message' => '메시지 저장에 실패했습니다.'
                ], 500);
            }

            // 5. 채팅방 최종 활동 시간 업데이트
            $room->update([
                'last_activity_at' => now(),
                'last_message_at' => now(),
                'message_count' => DB::raw('message_count + 1')
            ]);

            // 6. 참여자 읽음 상태 업데이트
            $participant->update([
                'last_read_at' => now(),
                'last_read_message_id' => $messageId
            ]);

            // 7. 참여자 목록 조회 (SSE 이벤트용)
            $participants = ChatParticipant::where('room_id', $room->id)
                ->where('status', 'active')
                ->get()
                ->toArray();

            // 8. SSE 이벤트 브로드캐스트
            $messageData = [
                'id' => $messageId,
                'room_id' => $room->id,
                'user_uuid' => $user->uuid,
                'message' => $validated['message'],
                'message_type' => $validated['message_type'] ?? 'text',
                'reply_to_id' => $validated['reply_to_id'] ?? null,
                'is_system' => false,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString()
            ];

            event(new MessageSent($room, $messageData, $participants));

            Log::info('새 메시지 작성 완료', [
                'room_id' => $roomId,
                'message_id' => $messageId,
                'user_uuid' => $user->uuid,
                'message_length' => strlen($validated['message'])
            ]);

            return response()->json([
                'success' => true,
                'message' => '메시지가 전송되었습니다.',
                'data' => [
                    'message_id' => $messageId,
                    'message' => $validated['message'],
                    'message_type' => $validated['message_type'] ?? 'text',
                    'created_at' => now()->format('H:i'),
                    'user_name' => $user->name,
                    'user_uuid' => $user->uuid
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('메시지 작성 실패', [
                'room_id' => $roomId,
                'user_uuid' => $user->uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '메시지 전송 중 오류가 발생했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * SQLite 데이터베이스에 메시지 저장
     */
    private function saveMessageToSqlite($room, $user, $validated)
    {
        try {
            // SQLite 파일 경로 찾기
            $sqlitePath = $this->findSqlitePath($room);

            if (!$sqlitePath || !file_exists($sqlitePath)) {
                Log::error('SQLite 파일을 찾을 수 없음', [
                    'room_id' => $room->id,
                    'expected_path' => $sqlitePath
                ]);
                return false;
            }

            // SQLite 연결
            $pdo = new \PDO("sqlite:" . $sqlitePath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // UTF-8 인코딩 설정
            $pdo->exec("PRAGMA encoding = 'UTF-8'");
            $pdo->exec("PRAGMA journal_mode = WAL");

            // 메시지 삽입
            $stmt = $pdo->prepare("
                INSERT INTO chat_messages (
                    room_id, user_uuid, message, message_type,
                    reply_to_id, is_system, is_deleted,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, 0, 0, datetime('now'), datetime('now'))
            ");

            $stmt->execute([
                $room->id,
                $user->uuid,
                $validated['message'],
                $validated['message_type'] ?? 'text',
                $validated['reply_to_id'] ?? null
            ]);

            $messageId = $pdo->lastInsertId();

            Log::info('SQLite 메시지 저장 완료', [
                'room_id' => $room->id,
                'message_id' => $messageId,
                'sqlite_path' => $sqlitePath
            ]);

            return $messageId;

        } catch (\Exception $e) {
            Log::error('SQLite 메시지 저장 실패', [
                'room_id' => $room->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * 채팅방의 SQLite 파일 경로 찾기
     */
    private function findSqlitePath($room)
    {
        // 새로운 형식: database/chat/년/월/일/room-{id}.sqlite
        $chatBasePath = database_path('chat');

        // 재귀적으로 파일 검색
        if (is_dir($chatBasePath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($chatBasePath, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getFilename() === "room-{$room->id}.sqlite") {
                    return $file->getPathname();
                }
            }
        }

        // 파일을 찾지 못한 경우 현재 날짜 기준으로 경로 생성
        $year = date('Y');
        $month = date('m');
        $day = date('d');

        return database_path("chat/{$year}/{$month}/{$day}/room-{$room->id}.sqlite");
    }

    /**
     * 메시지 목록 조회 (AJAX 리로드용)
     */
    public function index(Request $request, $roomId)
    {
        // JWT 인증된 사용자 정보 가져오기
        $authUser = auth()->user();
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => '로그인이 필요합니다.'
            ], 401);
        }

        $user = (object) [
            'uuid' => $authUser->uuid ?? 'user-' . $authUser->id,
            'name' => $authUser->name ?? 'Unknown User',
            'email' => $authUser->email ?? 'unknown@example.com'
        ];

        try {
            // 1. 채팅방 조회
            $room = ChatRoom::findOrFail($roomId);

            // 2. 사용자 참여 여부 확인
            $participant = ChatParticipant::where('room_id', $room->id)
                ->where('user_uuid', $user->uuid)
                ->where('status', 'active')
                ->first();

            if (!$participant) {
                return response()->json([
                    'success' => false,
                    'message' => '이 채팅방에 참여하지 않았습니다.'
                ], 403);
            }

            // 3. SQLite에서 최근 메시지 조회
            $messages = $this->getRecentMessagesFromSqlite($room, $request->get('limit', 50));

            // 4. 참여자 목록 조회
            $participants = ChatParticipant::where('room_id', $room->id)
                ->where('status', 'active')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'messages' => $messages,
                    'participants' => $participants,
                    'current_user' => $user
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('메시지 목록 조회 실패', [
                'room_id' => $roomId,
                'user_uuid' => $user->uuid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '메시지를 불러올 수 없습니다.'
            ], 500);
        }
    }

    /**
     * SQLite에서 최근 메시지 조회
     */
    private function getRecentMessagesFromSqlite($room, $limit = 50)
    {
        try {
            $sqlitePath = $this->findSqlitePath($room);

            if (!$sqlitePath || !file_exists($sqlitePath)) {
                return collect([]);
            }

            $pdo = new \PDO("sqlite:" . $sqlitePath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // UTF-8 인코딩 설정
            $pdo->exec("PRAGMA encoding = 'UTF-8'");
            $pdo->exec("PRAGMA journal_mode = WAL");

            $stmt = $pdo->prepare("
                SELECT
                    id, room_id, user_uuid, message, message_type,
                    reply_to_id, is_system, is_deleted,
                    created_at, updated_at
                FROM chat_messages
                WHERE room_id = ? AND is_deleted = 0
                ORDER BY created_at DESC
                LIMIT ?
            ");

            $stmt->execute([$room->id, (int) $limit]);
            $rawMessages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 사용자 메시지가 있는지 확인 (시스템 메시지 제외)
            $hasUserMessages = false;
            foreach ($rawMessages as $msg) {
                if (!$msg['is_system']) {
                    $hasUserMessages = true;
                    break;
                }
            }

            // 사용자 메시지가 있으면 시스템 안내 메시지 필터링
            if ($hasUserMessages) {
                $rawMessages = array_filter($rawMessages, function ($msg) {
                    // 생성 안내 시스템 메시지만 제거 (다른 시스템 메시지는 유지)
                    if ($msg['is_system'] && strpos($msg['message'], '이 생성되었습니다. 대화를 시작해보세요!') !== false) {
                        return false;
                    }
                    return true;
                });
            }

            return collect($rawMessages)->reverse()->map(function ($message) {
                return (object) $message;
            });

        } catch (\Exception $e) {
            Log::error('SQLite 메시지 조회 실패', [
                'room_id' => $room->id,
                'error' => $e->getMessage()
            ]);

            return collect([]);
        }
    }
}