<?php

namespace Jiny\Chat\Http\Controllers\Home\Room;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Jiny\Chat\Services\ChatService;
use Jiny\Chat\Models\ChatRoom;

/**
 * 채팅방 생성 처리 컨트롤러
 *
 * Single Action Controller - 채팅방 생성 요청 처리
 */
class StoreController extends Controller
{
    protected $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * 채팅방 생성 처리
     */
    public function __invoke(Request $request)
    {
        // 요청 데이터 로깅
        \Log::info('채팅방 생성 요청', [
            'request_data' => $request->all(),
            'method' => $request->method(),
            'url' => $request->fullUrl()
        ]);

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

        try {
            // 유효성 검사
            $request->validate([
                'title' => 'required|string|max:255',
                'slug' => 'nullable|string|max:255|regex:/^[a-z0-9-]+$/|unique:chat_rooms,slug',
                'description' => 'nullable|string|max:1000',
                'type' => 'required|in:public,private,group',
                'password' => 'nullable|string|min:4',
                'max_participants' => 'nullable|integer|min:0|max:1000',
                'is_public' => 'nullable|boolean',
                'allow_join' => 'nullable|boolean',
                'allow_invite' => 'nullable|boolean',
            ], [
                'title.required' => '채팅방 제목을 입력해주세요.',
                'title.max' => '채팅방 제목은 255자를 초과할 수 없습니다.',
                'slug.regex' => '슬러그는 영문 소문자, 숫자, 하이픈(-)만 사용할 수 있습니다.',
                'slug.unique' => '이미 사용 중인 슬러그입니다.',
                'type.required' => '채팅방 타입을 선택해주세요.',
                'type.in' => '올바른 채팅방 타입을 선택해주세요.',
                'password.min' => '비밀번호는 최소 4자 이상이어야 합니다.',
                'max_participants.min' => '최대 참여자 수는 0 이상이어야 합니다 (0은 무제한).',
                'max_participants.max' => '최대 참여자 수는 1000명을 초과할 수 없습니다.',
            ]);

            \Log::info('채팅방 생성 validation 통과');

            // slug 처리
            $slug = $this->generateSlug($request->input('slug'), $request->input('title'));

            // 채팅방 데이터 준비
            $roomData = [
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'type' => $request->input('type'),
                'slug' => $slug,
                'max_participants' => $request->input('max_participants') ?: 0, // 빈 값이면 0 (무제한)
                'is_public' => $request->has('is_public') ? 1 : 0,
                'allow_join' => $request->has('allow_join') ? 1 : 0,
                'allow_invite' => $request->has('allow_invite') ? 1 : 0,
            ];

            // 채팅방 생성
            $room = $this->chatService->createRoom($user->uuid, $roomData);

            // 비밀번호 설정
            if ($request->password) {
                $room->setPassword($request->password);
            }

            \Log::info('채팅방 생성 성공', [
                'room_id' => $room->id,
                'room_title' => $room->title,
                'room_slug' => $room->slug,
                'user_uuid' => $user->uuid
            ]);

            // JSON 응답
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'room' => $room->load('activeParticipants'),
                    'redirect' => route('home.chat.rooms.show', $room->id),
                    'message' => '채팅방이 성공적으로 생성되었습니다.'
                ]);
            }

            return redirect()->route('home.chat.rooms.show', $room->id)
                ->with('success', '채팅방이 생성되었습니다. 채팅을 시작해보세요!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('채팅방 생성 validation 실패', [
                'user_uuid' => $user->uuid ?? 'unknown',
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => '입력값에 오류가 있습니다.'
                ], 422);
            }

            throw $e; // Laravel이 자동으로 처리하도록 다시 던짐

        } catch (\Exception $e) {
            // 로그 기록
            \Log::error('채팅방 생성 실패', [
                'user_uuid' => $user->uuid ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => '채팅방 생성 중 오류가 발생했습니다.',
                    'message' => $e->getMessage()
                ], 500);
            }

            return back()
                ->withInput()
                ->with('error', '채팅방 생성 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    /**
     * 슬러그 생성 및 검증
     */
    private function generateSlug(?string $inputSlug, string $title): string
    {
        // 사용자가 slug를 입력한 경우
        if (!empty($inputSlug)) {
            return Str::slug($inputSlug);
        }

        // slug가 없는 경우 제목을 기반으로 생성
        $baseSlug = Str::slug($title);

        // 빈 slug인 경우 해시 기반으로 생성
        if (empty($baseSlug)) {
            $baseSlug = 'room-' . substr(md5($title . time()), 0, 8);
        }

        // 중복 확인 및 고유 slug 생성
        $slug = $baseSlug;
        $counter = 1;

        while (ChatRoom::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}