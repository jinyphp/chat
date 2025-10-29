<?php

namespace Jiny\Chat\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatMessage;
use Jiny\Chat\Services\ChatService;

/**
 * MessageController - 채팅 메시지 API 컨트롤러
 *
 * [컨트롤러 역할]
 * - 메시지 전송, 편집, 삭제 API
 * - 메시지 읽음 처리 API
 * - 메시지 목록 및 검색 API
 * - 실시간 메시지 처리
 *
 * [주요 API]
 * - POST /api/chat/messages - 메시지 전송
 * - PUT /api/chat/messages/{id} - 메시지 편집
 * - DELETE /api/chat/messages/{id} - 메시지 삭제
 * - POST /api/chat/messages/{id}/read - 읽음 처리
 * - GET /api/chat/rooms/{id}/messages - 메시지 목록
 *
 * [응답 형식]
 * - 성공: {"success": true, "data": {...}}
 * - 실패: {"success": false, "error": "message"}
 */
class MessageController extends Controller
{
    protected $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * 메시지 전송
     */
    public function store(Request $request)
    {
        // JWT 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'room_id' => 'required|exists:chat_rooms,id',
            'content' => 'required_without:media|string|max:1000',
            'type' => 'in:text,image,file,voice,video',
            'reply_to_message_id' => 'nullable|exists:chat_messages,id',
            'media' => 'array',
            'media.url' => 'string',
            'media.name' => 'string',
            'media.size' => 'integer',
            'media.type' => 'string',
        ]);

        try {
            $messageData = [
                'content' => $request->content,
                'type' => $request->type ?? 'text',
                'reply_to_message_id' => $request->reply_to_message_id,
                'media' => $request->media,
            ];

            $message = $this->chatService->sendMessage(
                $request->room_id,
                $user->uuid,
                $messageData
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => $message->load(['sender', 'replyTo']),
                    'room_id' => $request->room_id,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 메시지 편집
     */
    public function update($id, Request $request)
    {
        // JWT 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        try {
            $message = $this->chatService->editMessage(
                $id,
                $user->uuid,
                $request->content
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => $message->load(['sender', 'replyTo']),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 메시지 삭제
     */
    public function destroy($id, Request $request)
    {
        // JWT 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        try {
            $this->chatService->deleteMessage(
                $id,
                $user->uuid,
                $request->reason
            );

            return response()->json([
                'success' => true,
                'data' => ['message_id' => $id]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 메시지 읽음 처리
     */
    public function markAsRead($id, Request $request)
    {
        // JWT 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $message = ChatMessage::findOrFail($id);

            $this->chatService->markAsRead(
                $message->room_id,
                $user->uuid,
                $id
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'message_id' => $id,
                    'room_id' => $message->room_id,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 채팅방 모든 메시지 읽음 처리
     */
    public function markRoomAsRead($roomId, Request $request)
    {
        // JWT 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $this->chatService->markAsRead($roomId, $user->uuid);

            return response()->json([
                'success' => true,
                'data' => ['room_id' => $roomId]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 채팅방 메시지 목록
     */
    public function index($roomId, Request $request)
    {
        // JWT 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // 채팅방 존재 및 권한 확인
        $room = ChatRoom::find($roomId);
        if (!$room) {
            return response()->json(['error' => 'Room not found'], 404);
        }

        $participant = $room->participants()
            ->where('user_uuid', $user->uuid)
            ->where('status', 'active')
            ->first();

        if (!$participant) {
            return response()->json(['error' => 'Not a participant'], 403);
        }

        // 메시지 목록 조회
        $query = $room->messages()
            ->with(['replyTo', 'reads'])
            ->where('is_deleted', false);

        // 페이지네이션 설정
        $page = $request->get('page', 1);
        $limit = min($request->get('limit', 50), 100); // 최대 100개

        // 특정 메시지 이후 조회 (실시간 업데이트용)
        if ($after = $request->get('after')) {
            $query->where('id', '>', $after);
        }

        // 특정 메시지 이전 조회 (과거 메시지 로드용)
        if ($before = $request->get('before')) {
            $query->where('id', '<', $before);
        }

        // 검색
        if ($search = $request->get('search')) {
            $query->where('content', 'like', "%{$search}%");
        }

        // 메시지 타입 필터
        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        $messages = $query->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // 메시지를 시간순으로 정렬 (최신이 마지막)
        $messagesArray = $messages->reverse()->values();

        return response()->json([
            'success' => true,
            'data' => [
                'messages' => $messagesArray,
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'total' => $messages->total(),
                    'per_page' => $messages->perPage(),
                ]
            ]
        ]);
    }

    /**
     * 메시지 상세 정보
     */
    public function show($id, Request $request)
    {
        // JWT 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $message = ChatMessage::with([
                'sender',
                'replyTo',
                'replies',
                'reads.user',
                'room'
            ])->findOrFail($id);

            // 권한 확인
            $participant = $message->room->participants()
                ->where('user_uuid', $user->uuid)
                ->where('status', 'active')
                ->first();

            if (!$participant) {
                return response()->json(['error' => 'Not authorized'], 403);
            }

            return response()->json([
                'success' => true,
                'data' => ['message' => $message]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Message not found'
            ], 404);
        }
    }

    /**
     * 메시지 반응 추가/제거
     */
    public function toggleReaction($id, Request $request)
    {
        // JWT 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'emoji' => 'required|string|max:10',
        ]);

        try {
            $message = ChatMessage::findOrFail($id);

            // 권한 확인
            $participant = $message->room->participants()
                ->where('user_uuid', $user->uuid)
                ->where('status', 'active')
                ->first();

            if (!$participant) {
                return response()->json(['error' => 'Not authorized'], 403);
            }

            $reactions = $message->reactions ?? [];
            $emoji = $request->emoji;
            $userReacted = isset($reactions[$emoji]) && in_array($user->uuid, $reactions[$emoji]);

            if ($userReacted) {
                $message->removeReaction($user->uuid, $emoji);
                $action = 'removed';
            } else {
                $message->addReaction($user->uuid, $emoji);
                $action = 'added';
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'message_id' => $id,
                    'emoji' => $emoji,
                    'action' => $action,
                    'reactions' => $message->fresh()->reactions,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 메시지 고정/고정해제
     */
    public function togglePin($id, Request $request)
    {
        // JWT 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $message = ChatMessage::findOrFail($id);
            $message->togglePin($user->uuid);

            return response()->json([
                'success' => true,
                'data' => [
                    'message_id' => $id,
                    'is_pinned' => $message->is_pinned,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 메시지 검색
     */
    public function search($roomId, Request $request)
    {
        // JWT 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'query' => 'required|string|min:2',
            'type' => 'nullable|in:text,image,file,voice,video',
            'user_uuid' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        try {
            $room = ChatRoom::findOrFail($roomId);

            // 권한 확인
            $participant = $room->participants()
                ->where('user_uuid', $user->uuid)
                ->where('status', 'active')
                ->first();

            if (!$participant) {
                return response()->json(['error' => 'Not authorized'], 403);
            }

            $query = $room->messages()
                ->with(['replyTo'])
                ->where('is_deleted', false)
                ->where('content', 'like', '%' . $request->query . '%');

            // 추가 필터
            if ($request->type) {
                $query->where('type', $request->type);
            }

            if ($request->user_uuid) {
                $query->where('sender_uuid', $request->user_uuid);
            }

            if ($request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $messages = $query->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => [
                    'messages' => $messages->items(),
                    'pagination' => [
                        'current_page' => $messages->currentPage(),
                        'last_page' => $messages->lastPage(),
                        'total' => $messages->total(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}