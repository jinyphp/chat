<?php

namespace Jiny\Chat\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Models\ChatInviteToken;
use Jiny\Auth\Facades\Shard;

class ChatInviteController extends Controller
{
    /**
     * 초대 링크로 채팅방 참여
     */
    public function join(Request $request, $token)
    {
        try {
            // JWT 인증 확인
            $user = \JwtAuth::user($request);
            if (!$user) {
                return redirect()->route('login')
                    ->with('error', '로그인이 필요합니다.');
            }

            // 토큰으로 채팅방 참여 시도
            $result = ChatInviteToken::joinWithToken($token, $user->uuid);

            if ($result['success']) {
                // 성공: 채팅방으로 리다이렉트
                return redirect()->route('chat.room', ['id' => $result['room_id']])
                    ->with('success', $result['message']);
            } else {
                // 실패: 에러 메시지와 함께 적절한 페이지로 리다이렉트
                $redirectRoute = 'chat.index'; // 기본 채팅 페이지

                // 이미 멤버인 경우 해당 채팅방으로 이동
                if ($result['code'] === 'ALREADY_MEMBER' && isset($result['room_id'])) {
                    $redirectRoute = 'chat.room';
                    $redirectParams = ['id' => $result['room_id']];
                }

                return redirect()->route($redirectRoute, $redirectParams ?? [])
                    ->with('warning', $result['message']);
            }

        } catch (\Exception $e) {
            \Log::error('초대 링크 처리 실패', [
                'token' => $token,
                'user_uuid' => $user->uuid ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return redirect()->route('chat.index')
                ->with('error', '초대 링크 처리 중 오류가 발생했습니다.');
        }
    }

    /**
     * 초대 링크 미리보기 (선택적)
     */
    public function preview(Request $request, $token)
    {
        try {
            $inviteToken = ChatInviteToken::where('token', $token)->first();

            if (!$inviteToken || !$inviteToken->isValid()) {
                return view('jiny-chat::invite.invalid');
            }

            $room = $inviteToken->room;
            $creator = null;

            // 생성자 정보 조회
            try {
                $creator = Shard::getUserByUuid($inviteToken->created_by_uuid);
            } catch (\Exception $e) {
                // 생성자 정보 조회 실패는 무시
            }

            return view('jiny-chat::invite.preview', [
                'room' => $room,
                'token' => $inviteToken,
                'creator' => $creator
            ]);

        } catch (\Exception $e) {
            \Log::error('초대 링크 미리보기 실패', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            return view('jiny-chat::invite.error');
        }
    }
}