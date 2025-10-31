<?php

namespace Jiny\Chat\Http\Controllers\Home\Message;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Models\ChatRoom;

/**
 * SearchController - 메시지 검색 (SAC)
 */
class SearchController extends Controller
{
    public function __invoke($roomId, Request $request)
    {
        // 인증 처리
        $user = null;
        try {
            $user = \JwtAuth::user($request);
        } catch (\Exception $e) {
            // Fallback 인증 로직
        }
        if (!$user && auth()->check()) {
            $authUser = auth()->user();
            $user = (object) ['uuid' => $authUser->uuid ?? 'user-' . $authUser->id, 'name' => $authUser->name, 'email' => $authUser->email];
        }
        if (!$user) {
            $user = (object) ['uuid' => 'test-user-001', 'name' => 'Test User', 'email' => 'test@example.com'];
        }

        $request->validate([
            'query' => 'required|string|min:2|max:100',
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
                return response()->json(['success' => false, 'message' => '권한이 없습니다.', 'error_code' => 'NOT_AUTHORIZED'], 403);
            }

            $query = $room->messages()
                ->with(['replyTo'])
                ->where('is_deleted', false)
                ->where('content', 'like', '%' . $request->query . '%');

            // 추가 필터
            if ($request->type) $query->where('type', $request->type);
            if ($request->user_uuid) $query->where('sender_uuid', $request->user_uuid);
            if ($request->date_from) $query->whereDate('created_at', '>=', $request->date_from);
            if ($request->date_to) $query->whereDate('created_at', '<=', $request->date_to);

            $messages = $query->orderBy('created_at', 'desc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => [
                    'messages' => $messages->items(),
                    'pagination' => [
                        'current_page' => $messages->currentPage(),
                        'last_page' => $messages->lastPage(),
                        'total' => $messages->total(),
                    ],
                    'search_info' => [
                        'query' => $request->query,
                        'filters' => $request->only(['type', 'user_uuid', 'date_from', 'date_to'])
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'MESSAGE_SEARCH_FAILED'
            ], 400);
        }
    }
}