<?php

namespace Jiny\Chat\Http\Controllers\Home\Invite;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Models\ChatRoom;

/**
 * IndexController - 초대 링크 관리 페이지 (SAC)
 *
 * [Single Action Controller]
 * - 초대 링크 관리 페이지만 담당
 * - 사용자가 생성한 채팅방의 초대 링크 관리
 * - 최근 참여한 채팅방 목록 표시
 * - 초대 링크 발급 및 관리 인터페이스 제공
 *
 * [주요 기능]
 * - 내가 생성한 채팅방 목록 (초대 링크 발급 가능)
 * - 최근 참여한 다른 사람의 채팅방 목록
 * - 활성 참여자 정보 표시
 * - 초대 링크 관리 UI 제공
 *
 * [인증 지원]
 * - JWT 인증 (우선)
 * - 세션 인증 (대체)
 * - 테스트 사용자 (개발용)
 *
 * [라우트]
 * - GET /home/chat/invites -> 초대 링크 관리 페이지
 */
class IndexController extends Controller
{
    /**
     * 초대 링크 관리 페이지
     *
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
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
                'avatar' => $authUser->avatar ?? null,
                'shard_id' => $authUser->shard_id ?? 1
            ];
        }

        // 3. 마지막으로 테스트 사용자
        if (!$user) {
            $user = (object) [
                'uuid' => 'test-user-' . time(),
                'name' => '테스트 사용자',
                'email' => 'test@example.com',
                'avatar' => null,
                'shard_id' => 1
            ];
        }

        try {
            // 내가 생성한 채팅방 (초대 링크 발급 가능)
            $myRooms = ChatRoom::where('owner_uuid', $user->uuid)
                ->where('status', 'active')
                ->with('activeParticipants')
                ->orderBy('created_at', 'desc')
                ->get();

            // 최근 참여한 채팅방 (다른 사람 방)
            $recentJoinedRooms = ChatRoom::whereHas('activeParticipants', function ($query) use ($user) {
                    $query->where('user_uuid', $user->uuid);
                })
                ->where('owner_uuid', '!=', $user->uuid)
                ->with(['activeParticipants' => function ($query) use ($user) {
                    $query->where('user_uuid', $user->uuid);
                }])
                ->orderBy('last_activity_at', 'desc')
                ->limit(10)
                ->get();

            // API 요청인 경우 JSON 반환
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'user' => $user,
                    'myRooms' => $myRooms,
                    'recentJoinedRooms' => $recentJoinedRooms,
                    'stats' => [
                        'myRoomsCount' => $myRooms->count(),
                        'joinedRoomsCount' => $recentJoinedRooms->count(),
                        'totalParticipants' => $myRooms->sum(function ($room) {
                            return $room->activeParticipants->count();
                        })
                    ]
                ]);
            }

            return view('jiny-chat::home.invite.index', [
                'user' => $user,
                'myRooms' => $myRooms,
                'recentJoinedRooms' => $recentJoinedRooms
            ]);

        } catch (\Exception $e) {
            \Log::error('초대 링크 관리 페이지 로드 실패', [
                'error' => $e->getMessage(),
                'user_uuid' => $user->uuid ?? null,
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => '페이지를 불러오는 중 오류가 발생했습니다.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }

            return back()->with('error', '페이지를 불러오는 중 오류가 발생했습니다.');
        }
    }
}