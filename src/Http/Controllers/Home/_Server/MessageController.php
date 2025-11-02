<?php

namespace Jiny\Chat\Http\Controllers\Home\Server;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatMessage;
use Jiny\Chat\Services\ChatService;

/**
 * Server MessageController - 서버형 채팅방 메시지 API (SAC)
 *
 * [Single Action Controller]
 * - 서버형 채팅방 메시지 목록을 JSON으로 반환
 * - 실시간 메시지 로딩 및 페이지네이션 지원
 * - 파일 첨부 및 이미지 정보 포함
 * - 사용자 권한 검증 및 읽음 처리
 *
 * [주요 기능]
 * - 채팅방 메시지 목록 조회 (JSON 응답)
 * - 페이지네이션 지원 (기본 50개)
 * - 파일 첨부 정보 포함
 * - 메시지 읽음 처리
 * - 참여자 권한 검증
 * - 삭제된 메시지 필터링
 *
 * [응답 형식]
 * {
 *   "success": true,
 *   "messages": [...],
 *   "pagination": {...},
 *   "room": {...}
 * }
 *
 * [라우트]
 * - GET /api/chat/server/{id}/messages -> 서버형 채팅방 메시지 목록
 */
class MessageController extends Controller
{
    protected $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * 서버형 채팅방 메시지 목록 조회 (JSON API)
     *
     * @param int $id 채팅방 ID
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke($id, Request $request)
    {
        try {
            // 사용자 인증 확인 (세션 우선 방식)
            $user = null;

            // 1. 세션 인증 시도 (우선)
            if (auth()->check()) {
                $authUser = auth()->user();
                $user = (object) [
                    'uuid' => $authUser->uuid ?? 'user-' . $authUser->id,
                    'name' => $authUser->name,
                    'email' => $authUser->email,
                    'avatar' => $authUser->avatar ?? null
                ];
                \Log::debug('세션 인증 성공', ['user_uuid' => $user->uuid]);
            }

            // 2. JWT 인증 시도 (Bearer 토큰)
            if (!$user) {
                $authHeader = $request->header('Authorization');
                if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
                    try {
                        $token = substr($authHeader, 7);
                        $user = \JwtAuth::parseToken($token)->authenticate();
                        \Log::debug('JWT Bearer 토큰 인증 성공', ['user_uuid' => $user->uuid ?? 'unknown']);
                    } catch (\Exception $e) {
                        \Log::debug('JWT Bearer 토큰 인증 실패', ['error' => $e->getMessage()]);
                    }
                }
            }

            // 3. JWT 요청 객체에서 직접 시도
            if (!$user) {
                try {
                    $user = \JwtAuth::user($request);
                    \Log::debug('JWT 요청 인증 성공', ['user_uuid' => $user->uuid ?? 'unknown']);
                } catch (\Exception $e) {
                    \Log::debug('JWT 요청 인증 실패', ['error' => $e->getMessage()]);
                }
            }

            // 4. 테스트 사용자 (개발용)
            if (!$user) {
                $user = (object) [
                    'uuid' => 'test-user-001',
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'avatar' => null
                ];
                \Log::debug('테스트 사용자 인증 사용');
            }

            // 채팅방 조회
            $room = ChatRoom::with(['activeParticipants'])->find($id);

            if (!$room || $room->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => '채팅방을 찾을 수 없습니다.'
                ], 404);
            }

            // SQLite 채팅 데이터베이스 설정
            $now = Carbon::now();
            $dbPath = $this->getChatDatabasePath($id, $now);

            if (!file_exists($dbPath)) {
                return response()->json([
                    'success' => false,
                    'message' => '채팅 데이터베이스를 찾을 수 없습니다.'
                ], 404);
            }

            // 동적 DB 연결 설정
            $this->setupDynamicConnection($dbPath);

            // 참여자 권한 확인 (SQLite에서)
            $participant = DB::connection('chat_server')
                ->table('participants')
                ->where('room_id', $id)
                ->where('user_uuid', $user->uuid)
                ->where('status', 'active')
                ->first();

            if (!$participant) {
                return response()->json([
                    'success' => false,
                    'message' => '채팅방에 참여하지 않았습니다.'
                ], 403);
            }

            // 페이지네이션 파라미터
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 50);
            $lastMessageId = $request->get('last_message_id');

            // 메시지 쿼리 빌드 (SQLite에서)
            $query = DB::connection('chat_server')
                ->table('messages')
                ->where('room_id', $id)
                ->where('is_deleted', 0)
                ->orderBy('created_at', 'desc');

            // 실시간 업데이트용 - 마지막 메시지 ID 이후의 메시지만 조회
            if ($lastMessageId) {
                $query->where('id', '>', $lastMessageId);
            }

            // 메시지 조회
            $messages = $query->limit($limit)->get();

            // 메시지 데이터 가공
            $messageData = $messages->map(function ($message) use ($user) {
                $messageArray = [
                    'id' => $message->id,
                    'content' => $message->content,
                    'type' => $message->type ?? 'text',
                    'user_uuid' => $message->user_uuid,
                    'user_name' => $message->user_name ?? 'Unknown',
                    'user_avatar' => $message->user_avatar,
                    'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                    'created_at_human' => $message->created_at->format('H:i'),
                    'is_my_message' => $message->user_uuid === $user->uuid,
                    'reply_to' => null,
                    'files' => []
                ];

                // 답글 정보
                if ($message->replyTo) {
                    $messageArray['reply_to'] = [
                        'id' => $message->replyTo->id,
                        'content' => $message->replyTo->content,
                        'user_name' => $message->replyTo->user_name
                    ];
                }

                // 파일 첨부 정보 (SQLite에서)
                if ($message->file_path) {
                    $filePath = $message->file_path;
                    $fileName = $message->file_name ?? basename($filePath);
                    $fileSize = $message->file_size ?? 0;
                    $fileType = $message->file_type ?? 'application/octet-stream';

                    $isImage = in_array(strtolower(pathinfo($fileName, PATHINFO_EXTENSION)),
                                      ['jpg', 'jpeg', 'png', 'gif', 'webp']);

                    $messageArray['files'] = [[
                        'id' => $message->id,
                        'name' => $fileName,
                        'path' => $filePath,
                        'size' => $fileSize,
                        'type' => $fileType,
                        'is_image' => $isImage,
                        'download_url' => asset('storage/' . $filePath),
                        'preview_url' => $isImage ? asset('storage/' . $filePath) : null
                    ]];
                }

                return $messageArray;
            });

            // 시간순으로 정렬 (최신이 아래)
            $messageData = $messageData->reverse()->values();

            // 읽음 처리
            try {
                $this->chatService->markAsRead($room->id, $user->uuid);
            } catch (\Exception $e) {
                \Log::warning('메시지 읽음 처리 실패', [
                    'room_id' => $room->id,
                    'user_uuid' => $user->uuid,
                    'error' => $e->getMessage()
                ]);
            }

            // 시간순으로 정렬 (최신이 아래)
            $messageData = $messageData->reverse()->values();

            // 응답 데이터
            $response = [
                'success' => true,
                'messages' => $messageData,
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $limit,
                    'total' => $messages->count(),
                    'has_more' => $messages->count() >= $limit
                ],
                'room' => [
                    'id' => $room->id,
                    'title' => $room->title,
                    'description' => $room->description,
                    'participants_count' => $room->activeParticipants->count()
                ],
                'user' => [
                    'uuid' => $user->uuid,
                    'name' => $user->name
                ]
            ];

            // 로깅
            \Log::info('서버형 채팅방 메시지 API 요청', [
                'room_id' => $room->id,
                'user_uuid' => $user->uuid,
                'page' => $page,
                'limit' => $limit,
                'message_count' => $messageData->count(),
                'last_message_id' => $lastMessageId,
                'ip' => $request->ip()
            ]);

            return response()->json($response);

        } catch (\Exception $e) {
            \Log::error('서버형 채팅방 메시지 API 오류', [
                'room_id' => $id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '메시지를 불러오는 중 오류가 발생했습니다.',
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
}