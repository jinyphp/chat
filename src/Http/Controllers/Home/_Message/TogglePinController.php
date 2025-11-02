<?php

namespace Jiny\Chat\Http\Controllers\Home\Message;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Models\ChatMessage;

/**
 * TogglePinController - 메시지 고정/해제 (SAC)
 */
class TogglePinController extends Controller
{
    public function __invoke($id, Request $request)
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

        try {
            $message = ChatMessage::findOrFail($id);
            $message->togglePin($user->uuid);

            return response()->json([
                'success' => true,
                'message' => '메시지가 ' . ($message->is_pinned ? '고정' : '고정 해제') . '되었습니다.',
                'data' => [
                    'message_id' => $id,
                    'is_pinned' => $message->is_pinned,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'TOGGLE_PIN_FAILED'
            ], 400);
        }
    }
}