<?php

namespace Jiny\Chat\Http\Controllers\Home\Setting;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * IndexController - 사용자 채팅 설정 페이지 (SAC)
 *
 * [Single Action Controller]
 * - 사용자 채팅 설정 페이지만 담당
 * - 개인 채팅 환경 설정 관리
 * - 알림, 테마, 사운드 등 사용자별 설정
 * - 설정 폼 인터페이스 제공
 *
 * [주요 기능]
 * - 사용자별 채팅 설정 조회
 * - 알림 설정 (활성화/비활성화)
 * - 사운드 설정 (활성화/비활성화)
 * - 테마 설정 (라이트/다크)
 * - 추가 개인화 설정 옵션
 *
 * [설정 항목]
 * - notifications_enabled: 알림 활성화
 * - sound_enabled: 사운드 활성화
 * - theme: 테마 (light/dark)
 * - auto_scroll: 자동 스크롤
 * - show_timestamps: 타임스탬프 표시
 * - compact_mode: 컴팩트 모드
 *
 * [인증 지원]
 * - JWT 인증 (우선)
 * - 세션 인증 (대체)
 * - 테스트 사용자 (개발용)
 *
 * [라우트]
 * - GET /home/chat/settings -> 사용자 채팅 설정 페이지
 */
class IndexController extends Controller
{
    /**
     * 사용자 채팅 설정 페이지
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

        // 사용자의 채팅 관련 설정 조회
        // TODO: 실제 데이터베이스에서 사용자별 설정 조회 로직 구현
        $chatSettings = [
            'notifications_enabled' => true,
            'sound_enabled' => true,
            'theme' => 'light',
            'auto_scroll' => true,
            'show_timestamps' => true,
            'compact_mode' => false,
            'enter_to_send' => true,
            'show_typing_indicator' => true,
            'show_read_receipts' => true,
            'language' => 'ko',
            'timezone' => 'Asia/Seoul'
        ];

        // 설정 기본값 정의
        $defaultSettings = [
            'notifications_enabled' => true,
            'sound_enabled' => true,
            'theme' => 'light',
            'auto_scroll' => true,
            'show_timestamps' => true,
            'compact_mode' => false,
            'enter_to_send' => true,
            'show_typing_indicator' => true,
            'show_read_receipts' => true,
            'language' => 'ko',
            'timezone' => 'Asia/Seoul'
        ];

        // 설정값이 없는 경우 기본값 사용
        $chatSettings = array_merge($defaultSettings, $chatSettings);

        // API 요청인 경우 JSON 반환
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'user' => $user,
                'chatSettings' => $chatSettings,
                'availableOptions' => [
                    'themes' => ['light', 'dark'],
                    'languages' => ['ko', 'en'],
                    'timezones' => ['Asia/Seoul', 'UTC', 'America/New_York']
                ]
            ]);
        }

        return view('jiny-chat::home.settings.index', compact('user', 'chatSettings'));
    }
}