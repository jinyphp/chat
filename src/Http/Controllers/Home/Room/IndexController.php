<?php

namespace Jiny\Chat\Http\Controllers\Home\Room;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Models\ChatRoom;

/**
 * IndexController - 채팅방 목록 페이지 (SAC)
 *
 * [Single Action Controller]
 * - 채팅방 목록 페이지만 담당
 * - 다양한 필터링 옵션 제공 (공개방, 참여방, 소유방)
 * - 검색 기능 제공
 * - API 및 웹 요청 모두 지원
 *
 * [주요 기능]
 * - 채팅방 목록 표시 (페이지네이션)
 * - 타입별 필터링 (public, joined, owned)
 * - 제목/설명 기반 검색
 * - count_only 파라미터 지원 (API용)
 * - JSON/HTML 응답 지원
 *
 * [지원하는 파라미터]
 * - type: all(기본), public, joined/participant, owned/owner
 * - search: 제목/설명 검색어
 * - count_only: true/false (개수만 반환)
 *
 * [라우트]
 * - GET /home/chat/rooms -> 채팅방 목록
 */
class IndexController extends Controller
{
    /**
     * 채팅방 목록 페이지
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
                'email' => $authUser->email
            ];
        }

        // 3. 마지막으로 테스트 사용자
        if (!$user) {
            $user = (object) [
                'uuid' => 'test-user-001',
                'name' => 'Test User',
                'email' => 'test@example.com'
            ];
        }

        $query = ChatRoom::query()
            ->where('status', 'active');

        // 필터링
        $type = $request->get('type', 'all');
        switch ($type) {
            case 'public':
                $query->where('is_public', true);
                break;
            case 'joined':
            case 'participant':
                $query->whereHas('participants', function ($q) use ($user) {
                    $q->where('user_uuid', $user->uuid)
                      ->where('status', 'active');
                });
                break;
            case 'owned':
            case 'owner':
                $query->where('owner_uuid', $user->uuid);
                break;
        }

        // 검색
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // count_only 파라미터 확인
        $countOnly = $request->boolean('count_only');

        if ($countOnly) {
            $count = $query->count();

            // API 요청인 경우 JSON 반환 (count_only)
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'count' => $count,
                    'type' => $type
                ]);
            }

            return response()->json([
                'success' => true,
                'count' => $count,
                'type' => $type
            ]);
        }

        $rooms = $query->with(['activeParticipants'])
            ->orderBy('last_activity_at', 'desc')
            ->paginate(20);

        // API 요청인 경우 JSON 반환
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'rooms' => $rooms->items(),
                'pagination' => [
                    'current_page' => $rooms->currentPage(),
                    'last_page' => $rooms->lastPage(),
                    'total' => $rooms->total(),
                ]
            ]);
        }

        return view('jiny-chat::home.room.index', compact('rooms', 'type', 'user'));
    }
}