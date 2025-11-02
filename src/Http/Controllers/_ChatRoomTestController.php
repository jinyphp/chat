<?php

namespace Jiny\Chat\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Jiny\Chat\Services\ChatRoomDatabaseService;
use Jiny\Chat\Models\ChatRoom;

/**
 * ChatRoomTestController - 독립 채팅방 데이터베이스 테스트 컨트롤러
 */
class ChatRoomTestController extends Controller
{
    protected $chatRoomService;

    public function __construct(ChatRoomDatabaseService $chatRoomService)
    {
        $this->chatRoomService = $chatRoomService;
    }

    /**
     * 테스트 채팅방 생성
     */
    public function createTestRoom(Request $request)
    {
        try {
            $roomData = [
                'title' => $request->input('title', '테스트 채팅방 ' . now()->format('Y-m-d H:i:s')),
                'description' => '독립 데이터베이스 테스트용 채팅방',
                'type' => 'public',
                'owner_uuid' => $request->input('owner_uuid', 'test-user-001'),
            ];

            $room = $this->chatRoomService->createChatRoom($roomData);

            return response()->json([
                'success' => true,
                'room' => [
                    'id' => $room->id,
                    'code' => $room->code,
                    'uuid' => $room->uuid,
                    'title' => $room->title,
                    'database_size' => $room->getDatabaseSize(),
                ],
                'message' => '독립 데이터베이스 채팅방이 성공적으로 생성되었습니다.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 테스트 메시지 전송
     */
    public function sendTestMessage(Request $request, $roomCode)
    {
        try {
            $messageData = [
                'content' => $request->input('content', '테스트 메시지 ' . now()->format('H:i:s')),
                'type' => $request->input('type', 'text'),
            ];

            $senderUuid = $request->input('sender_uuid', 'test-user-001');

            $message = $this->chatRoomService->sendMessage($roomCode, $senderUuid, $messageData);

            return response()->json([
                'success' => true,
                'message' => [
                    'id' => $message->id,
                    'content' => $message->content,
                    'type' => $message->type,
                    'sender_uuid' => $message->sender_uuid,
                    'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                ],
                'room_stats' => $this->chatRoomService->getRoomStats($roomCode, 1),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 메시지 목록 조회
     */
    public function getMessages($roomCode)
    {
        try {
            $messages = $this->chatRoomService->getMessages($roomCode, 50, 0);

            return response()->json([
                'success' => true,
                'messages' => $messages->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'content' => $message->content,
                        'type' => $message->type,
                        'sender_uuid' => $message->sender_uuid,
                        'sender_name' => $message->sender_name,
                        'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                    ];
                }),
                'total_count' => $messages->count(),
                'database_size' => $this->chatRoomService->getDatabaseSize($roomCode),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 채팅방 통계 조회
     */
    public function getRoomStats($roomCode)
    {
        try {
            $stats = $this->chatRoomService->getRoomStats($roomCode);
            $dbSize = $this->chatRoomService->getDatabaseSize($roomCode);

            return response()->json([
                'success' => true,
                'stats' => $stats,
                'database_info' => [
                    'size_bytes' => $dbSize,
                    'size_human' => $this->formatBytes($dbSize),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 데이터베이스 연결 테스트
     */
    public function testConnection($roomCode)
    {
        $result = $this->chatRoomService->testDatabaseConnection($roomCode);

        return response()->json([
            'success' => $result['success'],
            'data' => $result,
        ]);
    }

    /**
     * 모든 독립 데이터베이스 목록
     */
    public function listAllDatabases()
    {
        try {
            $databases = $this->chatRoomService->getAllDatabases();

            return response()->json([
                'success' => true,
                'databases' => array_map(function ($db) {
                    return [
                        'room_code' => $db['room_code'],
                        'file_path' => $db['file_path'],
                        'size_bytes' => $db['size'],
                        'size_human' => $this->formatBytes($db['size']),
                        'modified_at' => $db['modified_at'],
                    ];
                }, $databases),
                'total_count' => count($databases),
                'total_size' => $this->formatBytes(array_sum(array_column($databases, 'size'))),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 채팅방 데이터 마이그레이션
     */
    public function migrateRoom(Request $request, $roomId)
    {
        $result = $this->chatRoomService->migrateChatRoomData($roomId);

        return response()->json([
            'success' => $result['success'],
            'data' => $result,
        ]);
    }

    /**
     * 데이터베이스 백업
     */
    public function backupDatabase($roomCode)
    {
        try {
            $result = $this->chatRoomService->backupDatabase($roomCode);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => '데이터베이스 백업이 완료되었습니다.',
                    'backup_created' => true,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => '데이터베이스 백업에 실패했습니다.',
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 데이터베이스 최적화
     */
    public function optimizeDatabase($roomCode)
    {
        try {
            $sizeBefore = $this->chatRoomService->getDatabaseSize($roomCode);
            $result = $this->chatRoomService->optimizeDatabase($roomCode);
            $sizeAfter = $this->chatRoomService->getDatabaseSize($roomCode);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => '데이터베이스 최적화가 완료되었습니다.',
                    'size_before' => $this->formatBytes($sizeBefore),
                    'size_after' => $this->formatBytes($sizeAfter),
                    'size_reduced' => $this->formatBytes($sizeBefore - $sizeAfter),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => '데이터베이스 최적화에 실패했습니다.',
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 테스트 페이지
     */
    public function testPage()
    {
        $rooms = ChatRoom::where('code', '!=', null)->orderBy('created_at', 'desc')->limit(10)->get();

        return view('jiny-chat::test.independent-database', compact('rooms'));
    }

    /**
     * 바이트를 사람이 읽기 쉬운 형태로 변환
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}