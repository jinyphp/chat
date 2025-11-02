<?php

namespace Jiny\Chat\Http\Controllers\Home\Room;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;

/**
 * MessageListController - 메시지 목록 JSON API
 */
class MessageListController extends Controller
{
    /**
     * 메시지 목록 조회 (JSON)
     */
    public function index(Request $request, $roomId)
    {
        try {
            Log::info('MessageListController::index 시작', [
                'room_id' => $roomId,
                'request_url' => $request->url(),
                'headers' => $request->headers->all()
            ]);

            // JWT 인증된 사용자 정보 가져오기
            $authUser = auth()->user();
            Log::info('인증 사용자 확인', ['auth_user' => $authUser ? $authUser->toArray() : null]);

            if (!$authUser) {
                Log::warning('인증되지 않은 사용자');
                return response()->json([
                    'success' => false,
                    'message' => '로그인이 필요합니다.'
                ], 401);
            }
        } catch (\Exception $e) {
            Log::error('MessageListController 초기 오류', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => '초기화 오류: ' . $e->getMessage()
            ], 500);
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

            // 3. SQLite에서 메시지 조회
            $since = $request->get('since');
            $messages = $this->getMessagesFromSqlite($room, $request->get('limit', 50), $since);

            // 4. 참여자 목록 조회
            $participants = ChatParticipant::where('room_id', $room->id)
                ->where('status', 'active')
                ->get()
                ->toArray();

            $messageCount = is_array($messages) ? count($messages) : 0;
            Log::info('메시지 목록 API 호출', [
                'room_id' => $roomId,
                'user_uuid' => $user->uuid,
                'message_count' => $messageCount,
                'messages_is_array' => is_array($messages),
                'messages_type' => gettype($messages)
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'messages' => $messages,
                    'participants' => $participants,
                    'room' => [
                        'id' => $room->id,
                        'title' => $room->title,
                        'code' => $room->code
                    ],
                    'current_user' => $user
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('메시지 목록 API 실패', [
                'room_id' => $roomId,
                'user_uuid' => $user->uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '메시지를 불러올 수 없습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * SQLite에서 메시지 조회
     */
    private function getMessagesFromSqlite($room, $limit = 50, $since = null)
    {
        try {
            $sqlitePath = $this->findSqlitePath($room);

            if (!$sqlitePath || !file_exists($sqlitePath)) {
                Log::warning('SQLite 파일을 찾을 수 없음', [
                    'room_id' => $room->id,
                    'expected_path' => $sqlitePath
                ]);
                return [];
            }

            $pdo = new \PDO("sqlite:" . $sqlitePath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // UTF-8 인코딩 설정
            $pdo->exec("PRAGMA encoding = 'UTF-8'");
            $pdo->exec("PRAGMA journal_mode = WAL");

            // SQL 쿼리 동적 생성 (since 파라미터 처리)
            // 독립 SQLite 데이터베이스에는 room_id 컬럼이 없으므로 제거
            $whereClause = "WHERE is_deleted = 0";
            $params = [];

            if ($since) {
                $whereClause .= " AND created_at > ?";
                $params[] = $since;
            }

            $stmt = $pdo->prepare("
                SELECT
                    id, sender_uuid, sender_name, content, type,
                    reply_to_message_id, is_system, is_deleted,
                    created_at, updated_at
                FROM chat_messages
                {$whereClause}
                ORDER BY created_at DESC
                LIMIT ?
            ");

            $params[] = (int) $limit;
            $stmt->execute($params);
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
                    if ($msg['is_system'] && strpos($msg['content'], '이 생성되었습니다. 대화를 시작해보세요!') !== false) {
                        return false;
                    }
                    return true;
                });
            }

            // UTF-8 인코딩 확인 및 처리된 메시지 배열
            $processedMessages = [];
            foreach (array_reverse($rawMessages) as $message) {
                // UTF-8 인코딩 확인 및 변환
                if (isset($message['content'])) {
                    if (!mb_check_encoding($message['content'], 'UTF-8')) {
                        $message['content'] = mb_convert_encoding($message['content'], 'UTF-8', 'auto');
                    }
                }
                // 프론트엔드 호환성을 위해 기존 컬럼명도 추가
                $message['message'] = $message['content'];
                $message['user_uuid'] = $message['sender_uuid'];
                $message['message_type'] = $message['type'];
                $message['reply_to_id'] = $message['reply_to_message_id'];

                $processedMessages[] = $message;
            }

            $messages = $processedMessages; // 순수 배열

            Log::info('SQLite 메시지 조회 완료 (API)', [
                'room_id' => $room->id,
                'message_count' => count($messages),
                'sqlite_path' => $sqlitePath,
                'since_filter' => $since,
                'is_polling_request' => !empty($since)
            ]);

            return $messages;

        } catch (\Exception $e) {
            Log::error('SQLite 메시지 조회 실패 (API)', [
                'room_id' => $room->id,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * 채팅방의 SQLite 파일 경로 찾기
     */
    private function findSqlitePath($room)
    {
        // 현재 날짜 기준으로 직접 경로 생성 (재귀 검색 제거)
        $year = date('Y');
        $month = date('m');
        $day = date('d');

        $sqlitePath = database_path("chat/{$year}/{$month}/{$day}/room-{$room->id}.sqlite");

        // 파일이 존재하면 반환
        if (file_exists($sqlitePath)) {
            return $sqlitePath;
        }

        // 파일이 없으면 최근 3일 내에서 검색 (제한된 검색)
        for ($i = 0; $i < 3; $i++) {
            $date = date('Y/m/d', strtotime("-{$i} days"));
            $testPath = database_path("chat/{$date}/room-{$room->id}.sqlite");
            if (file_exists($testPath)) {
                return $testPath;
            }
        }

        // 파일을 찾지 못한 경우 현재 날짜 기준으로 경로 반환
        return $sqlitePath;
    }
}