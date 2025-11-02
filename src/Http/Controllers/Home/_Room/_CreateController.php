<?php

namespace Jiny\Chat\Http\Controllers\Home\Room;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * 채팅방 생성 폼 컨트롤러
 *
 * Single Action Controller - 채팅방 생성 페이지 표시
 */
class CreateController extends Controller
{
    /**
     * 채팅방 생성 폼 표시
     */
    public function __invoke(Request $request)
    {
        // JWT 인증 확인
        $user = \JwtAuth::user($request);
        if (!$user) {
            // 임시 테스트 사용자 생성 (개발용)
            $user = (object) [
                'uuid' => 'test-user-001',
                'name' => 'Test User',
                'email' => 'test@example.com'
            ];
        }

        // 채팅방 타입 옵션
        $roomTypes = [
            'public' => [
                'label' => '공개방',
                'description' => '누구나 참여할 수 있는 채팅방',
                'icon' => 'fas fa-globe'
            ],
            'private' => [
                'label' => '비공개방',
                'description' => '비밀번호가 필요한 채팅방',
                'icon' => 'fas fa-lock'
            ],
            'group' => [
                'label' => '그룹방',
                'description' => '초대받은 사용자만 참여 가능',
                'icon' => 'fas fa-users'
            ]
        ];

        return view('jiny-chat::home.room.create', compact('user', 'roomTypes'));
    }
}