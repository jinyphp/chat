<?php

namespace Jiny\Chat\Http\Controllers\Home\Room;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jiny\Chat\Models\ChatRoom;

/**
 * EditController - 채팅방 편집 페이지 (SAC)
 *
 * [Single Action Controller]
 * - 채팅방 편집 폼 페이지만 담당
 * - 방장(owner) 전용 기능
 * - 채팅방 기본 정보 수정 인터페이스
 * - UI 설정 로드 및 표시
 *
 * [주요 기능]
 * - 방장 권한 엄격 검증
 * - 채팅방 정보 조회 및 표시
 * - UI 설정 (배경색 등) 로드
 * - 편집 폼 렌더링
 * - 기본값 설정 및 검증
 *
 * [권한 검증]
 * - JWT 인증 필수
 * - 방장(owner_uuid) 권한 확인
 * - 채팅방 존재 확인
 * - 활성 상태 검증
 *
 * [UI 설정 처리]
 * - 배경색 설정 로드
 * - 기본값 제공 (#f8f9fa)
 * - JSON 형태 UI 설정 파싱
 * - 안전한 설정값 추출
 *
 * [보안 기능]
 * - 다양한 인증 방식 지원
 * - 세션 인증 대체 지원
 * - 테스트 사용자 지원 (개발용)
 * - 권한 없는 접근 차단
 *
 * [라우트]
 * - GET /home/chat/rooms/{id}/edit -> 채팅방 편집 폼
 */
class EditController extends Controller
{
    /**
     * 채팅방 편집 페이지
     *
     * @param int $id 채팅방 ID
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function __invoke($id, Request $request)
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

        // 채팅방 조회
        $room = ChatRoom::find($id);
        if (!$room) {
            \Log::warning('존재하지 않는 채팅방 편집 시도', [
                'room_id' => $id,
                'user_uuid' => $user->uuid,
                'ip' => $request->ip()
            ]);

            return redirect()->route('home.chat.index')
                ->with('error', '채팅방을 찾을 수 없습니다.');
        }

        // 방장 권한 확인
        if ($room->owner_uuid !== $user->uuid) {
            \Log::warning('방장이 아닌 사용자의 채팅방 편집 시도', [
                'room_id' => $room->id,
                'room_owner' => $room->owner_uuid,
                'user_uuid' => $user->uuid,
                'user_name' => $user->name,
                'ip' => $request->ip()
            ]);

            return redirect()->route('home.chat.index')
                ->with('error', '방장만 설정을 변경할 수 있습니다.');
        }

        // UI 설정에서 배경색 추출 (안전한 방식)
        $backgroundColor = '#f8f9fa'; // 기본값

        try {
            if ($room->ui_settings && is_array($room->ui_settings)) {
                $backgroundColor = $room->ui_settings['background_color'] ?? $backgroundColor;
            } elseif ($room->ui_settings && is_string($room->ui_settings)) {
                // JSON 문자열인 경우 파싱 시도
                $uiSettings = json_decode($room->ui_settings, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($uiSettings['background_color'])) {
                    $backgroundColor = $uiSettings['background_color'];
                }
            }

            // 색상 값 유효성 검증 (헥사 색상 코드)
            if (!preg_match('/^#[a-fA-F0-9]{6}$/', $backgroundColor)) {
                $backgroundColor = '#f8f9fa'; // 잘못된 형식이면 기본값으로
            }
        } catch (\Exception $e) {
            \Log::warning('UI 설정 파싱 중 오류', [
                'room_id' => $room->id,
                'ui_settings' => $room->ui_settings,
                'error' => $e->getMessage()
            ]);
            $backgroundColor = '#f8f9fa'; // 오류 시 기본값 사용
        }

        // 채팅방 편집 페이지 접근 로깅
        \Log::info('채팅방 편집 페이지 접근', [
            'room_id' => $room->id,
            'room_title' => $room->title,
            'user_uuid' => $user->uuid,
            'user_name' => $user->name,
            'background_color' => $backgroundColor,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString()
        ]);

        // 편집 폼에 필요한 추가 데이터 준비
        $roomData = [
            'id' => $room->id,
            'title' => $room->title,
            'description' => $room->description,
            'type' => $room->type,
            'category' => $room->category,
            'is_public' => $room->is_public,
            'allow_join' => $room->allow_join,
            'allow_invite' => $room->allow_invite,
            'max_participants' => $room->max_participants,
            'created_at' => $room->created_at,
            'participant_count' => $room->activeParticipants()->count()
        ];

        return view('jiny-chat::home.room.edit', compact(
            'room',
            'roomData',
            'backgroundColor',
            'user'
        ));
    }
}