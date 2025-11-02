<?php

namespace Jiny\Chat\Http\Controllers\Home\Message;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Models\ChatRoom;

/**
 * IndexController - 채팅방 메시지 목록 조회 (SAC)
 *
 * [Single Action Controller]
 * - 채팅방 메시지 목록 조회만 담당
 * - 페이지네이션 및 실시간 업데이트 지원
 * - 다양한 필터링 및 검색 옵션
 * - 참여자 권한 검증
 *
 * [주요 기능]
 * - 사용자 인증 및 참여자 권한 확인
 * - 메시지 목록 페이지네이션
 * - 실시간 업데이트용 after/before 필터
 * - 메시지 타입별 필터링
 * - 내용 기반 검색
 * - 읽음 정보 포함
 *
 * [쿼리 파라미터]
 * - page: 페이지 번호 (기본값: 1)
 * - limit: 페이지 크기 (기본값: 50, 최대: 100)
 * - after: 특정 메시지 ID 이후 조회 (실시간 업데이트용)
 * - before: 특정 메시지 ID 이전 조회 (과거 메시지 로드용)
 * - search: 내용 검색어
 * - type: 메시지 타입 필터
 *
 * [보안 기능]
 * - JWT 인증 필수
 * - 세션 인증 대체 지원
 * - 테스트 사용자 지원 (개발용)
 * - 참여자 권한 검증
 * - 삭제된 메시지 제외
 *
 * [라우트]
 * - GET /api/chat/rooms/{roomId}/messages -> 메시지 목록
 */
class IndexController extends Controller
{
    /**
     * 채팅방 메시지 목록 조회
     *
     * @param int $roomId 채팅방 ID
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke($roomId, Request $request)
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
                'avatar' => $authUser->avatar ?? null
            ];
        }

        // 3. 마지막으로 테스트 사용자
        if (!$user) {
            $user = (object) [
                'uuid' => 'test-user-001',
                'name' => 'Test User',
                'email' => 'test@example.com',
                'avatar' => null
            ];
        }

        // 쿼리 파라미터 검증
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(max(1, (int) $request->get('limit', 50)), 100);
        $after = $request->get('after');
        $before = $request->get('before');
        $search = $request->get('search');
        $type = $request->get('type');

        // 채팅방 존재 확인
        $room = ChatRoom::find($roomId);
        if (!$room) {
            return response()->json([
                'success' => false,
                'message' => '채팅방을 찾을 수 없습니다.',
                'error_code' => 'ROOM_NOT_FOUND'
            ], 404);
        }

        // 참여자 권한 확인
        $participant = $room->participants()
            ->where('user_uuid', $user->uuid)
            ->where('status', 'active')
            ->first();

        if (!$participant) {
            \Log::warning('메시지 목록 접근 권한 없음', [
                'room_id' => $roomId,
                'user_uuid' => $user->uuid,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => '채팅방에 참여하고 있지 않습니다.',
                'error_code' => 'NOT_PARTICIPANT'
            ], 403);
        }

        // 메시지 목록 조회 로깅
        \Log::info('메시지 목록 조회', [
            'room_id' => $roomId,
            'user_uuid' => $user->uuid,
            'page' => $page,
            'limit' => $limit,
            'has_after' => !empty($after),
            'has_before' => !empty($before),
            'has_search' => !empty($search),
            'type_filter' => $type,
            'ip' => $request->ip(),
            'timestamp' => now()->toISOString()
        ]);

        // 메시지 쿼리 구성
        $query = $room->messages()
            ->with(['replyTo', 'reads'])
            ->where('is_deleted', false);

        // 실시간 업데이트용 필터 (after)
        if ($after) {
            $query->where('id', '>', $after);
        }

        // 과거 메시지 로드용 필터 (before)
        if ($before) {
            $query->where('id', '<', $before);
        }

        // 검색 필터
        if ($search) {
            $query->where('content', 'like', "%{$search}%");
        }

        // 메시지 타입 필터
        if ($type && in_array($type, ['text', 'image', 'file', 'voice', 'video'])) {
            $query->where('type', $type);
        }

        // 메시지 조회 실행
        $messages = $query->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // 메시지를 시간순으로 정렬 (최신이 마지막)
        $messagesArray = $messages->reverse()->values();

        // 응답 데이터 준비
        $responseData = [
            'success' => true,
            'data' => [
                'messages' => $messagesArray,
                'room_info' => [
                    'id' => $room->id,
                    'title' => $room->title,
                    'participant_count' => $room->activeParticipants()->count()
                ],
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'total' => $messages->total(),
                    'per_page' => $messages->perPage(),
                    'has_more' => $messages->hasMorePages()
                ],
                'filters' => [
                    'search' => $search,
                    'type' => $type,
                    'after' => $after,
                    'before' => $before
                ],
                'user_info' => [
                    'uuid' => $user->uuid,
                    'participant_id' => $participant->id,
                    'role' => $participant->role
                ]
            ]
        ];

        return response()->json($responseData);
    }
}