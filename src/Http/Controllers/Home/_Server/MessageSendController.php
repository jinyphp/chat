<?php

namespace Jiny\Chat\Http\Controllers\Home\Server;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Services\ChatService;

/**
 * Server MessageSendController - 서버형 채팅방 메시지 전송 API (SAC)
 *
 * [Single Action Controller]
 * - 서버형 채팅방 메시지 전송 처리
 * - JSON 요청/응답 방식
 * - 파일 첨부 지원
 * - 실시간 브로드캐스팅
 *
 * [주요 기능]
 * - 텍스트 메시지 전송
 * - 파일 첨부 메시지 전송
 * - 답글(Reply) 기능 지원
 * - 사용자 권한 검증
 * - 실시간 알림 처리
 *
 * [요청 형식]
 * {
 *   "content": "메시지 내용",
 *   "type": "text|file",
 *   "reply_to": 메시지ID (선택)
 * }
 *
 * [응답 형식]
 * {
 *   "success": true,
 *   "message": {...},
 *   "room_id": 1
 * }
 *
 * [라우트]
 * - POST /api/chat/server/{id}/message -> 서버형 채팅방 메시지 전송
 */
class MessageSendController extends Controller
{
    protected $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * 서버형 채팅방 메시지 전송 (JSON API)
     *
     * @param int $id 채팅방 ID
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke($id, Request $request)
    {
        try {
            // 입력 검증
            $request->validate([
                'content' => 'required|string|max:4000',
                'type' => 'sometimes|in:text,file',
                'reply_to' => 'sometimes|integer',
                'file' => 'sometimes|file|max:51200' // 50MB 제한
            ]);

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

            // 파일 업로드 처리
            $filePath = null;
            $fileName = null;
            $fileSize = null;
            $fileType = null;

            if ($request->hasFile('file')) {
                $uploadedFile = $request->file('file');

                // 파일 저장
                $filePath = $uploadedFile->store('chat/files/' . date('Y/m/d'), 'public');
                $fileName = $uploadedFile->getClientOriginalName();
                $fileSize = $uploadedFile->getSize();
                $fileType = $uploadedFile->getMimeType();

                \Log::info('파일 업로드 성공', [
                    'room_id' => $id,
                    'user_uuid' => $user->uuid,
                    'file_name' => $fileName,
                    'file_path' => $filePath,
                    'file_size' => $fileSize
                ]);
            }

            // 답글 대상 메시지 확인 (선택사항)
            $replyTo = $request->input('reply_to');
            if ($replyTo) {
                $replyMessage = DB::connection('chat_server')
                    ->table('messages')
                    ->where('id', $replyTo)
                    ->where('room_id', $id)
                    ->where('is_deleted', 0)
                    ->first();

                if (!$replyMessage) {
                    return response()->json([
                        'success' => false,
                        'message' => '답글 대상 메시지를 찾을 수 없습니다.'
                    ], 404);
                }
            }

            // 메시지 저장 (SQLite에)
            $messageId = DB::connection('chat_server')
                ->table('messages')
                ->insertGetId([
                    'room_id' => $id,
                    'user_uuid' => $user->uuid,
                    'user_name' => $user->name,
                    'user_avatar' => $user->avatar,
                    'content' => $request->input('content'),
                    'type' => $request->input('type', 'text'),
                    'reply_to' => $replyTo,
                    'file_path' => $filePath,
                    'file_name' => $fileName,
                    'file_size' => $fileSize,
                    'file_type' => $fileType,
                    'is_deleted' => 0,
                    'created_at' => $now->toDateTimeString(),
                    'updated_at' => $now->toDateTimeString()
                ]);

            // 저장된 메시지 조회
            $savedMessage = DB::connection('chat_server')
                ->table('messages')
                ->where('id', $messageId)
                ->first();

            // 메시지 응답 데이터 구성
            $messageResponse = [
                'id' => $savedMessage->id,
                'content' => $savedMessage->content,
                'type' => $savedMessage->type ?? 'text',
                'user_uuid' => $savedMessage->user_uuid,
                'user_name' => $savedMessage->user_name,
                'user_avatar' => $savedMessage->user_avatar,
                'created_at' => $savedMessage->created_at,
                'created_at_human' => Carbon::parse($savedMessage->created_at)->format('H:i'),
                'is_my_message' => true,
                'reply_to' => null,
                'files' => []
            ];

            // 파일 정보 추가
            if ($savedMessage->file_path) {
                $isImage = in_array(strtolower(pathinfo($savedMessage->file_name, PATHINFO_EXTENSION)),
                                  ['jpg', 'jpeg', 'png', 'gif', 'webp']);

                $messageResponse['files'] = [[
                    'id' => $savedMessage->id,
                    'name' => $savedMessage->file_name,
                    'path' => $savedMessage->file_path,
                    'size' => $savedMessage->file_size,
                    'type' => $savedMessage->file_type,
                    'is_image' => $isImage,
                    'download_url' => asset('storage/' . $savedMessage->file_path),
                    'preview_url' => $isImage ? asset('storage/' . $savedMessage->file_path) : null
                ]];
            }

            // 답글 정보 추가
            if ($replyTo && isset($replyMessage)) {
                $messageResponse['reply_to'] = [
                    'id' => $replyMessage->id,
                    'content' => $replyMessage->content,
                    'user_name' => $replyMessage->user_name
                ];
            }

            // 참여자의 마지막 접속 시간 업데이트
            DB::connection('chat_server')
                ->table('participants')
                ->where('room_id', $id)
                ->where('user_uuid', $user->uuid)
                ->update([
                    'last_seen' => $now->toDateTimeString()
                ]);

            // 성공 응답
            $response = [
                'success' => true,
                'message' => $messageResponse,
                'room_id' => $id
            ];

            // 로깅
            \Log::info('서버형 채팅방 메시지 전송 성공', [
                'room_id' => $id,
                'message_id' => $savedMessage->id,
                'user_uuid' => $user->uuid,
                'content_length' => strlen($savedMessage->content),
                'type' => $savedMessage->type,
                'has_file' => $savedMessage->file_path ? true : false,
                'ip' => $request->ip()
            ]);

            return response()->json($response);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '입력 데이터가 올바르지 않습니다.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            \Log::error('서버형 채팅방 메시지 전송 오류', [
                'room_id' => $id ?? null,
                'user_uuid' => $user->uuid ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '메시지 전송 중 오류가 발생했습니다.',
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