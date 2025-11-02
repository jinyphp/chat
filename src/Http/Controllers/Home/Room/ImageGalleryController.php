<?php

namespace Jiny\Chat\Http\Controllers\Home\Room;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Models\ChatMessage;
use Jiny\Chat\Models\ChatFile;

/**
 * ImageGalleryController - 채팅방 이미지 갤러리
 *
 * 채팅방에 업로드된 이미지 파일들을 그리드 형태로 표시합니다.
 */
class ImageGalleryController extends Controller
{
    public function __invoke(Request $request, $roomId)
    {
        \Log::info('ImageGalleryController started', ['roomId' => $roomId]);

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

        // 3. 마지막으로 테스트 사용자 (실제 참여자 UUID 사용)
        if (!$user) {
            // 해당 채팅방의 첫 번째 활성 참여자 사용
            $firstParticipant = ChatParticipant::where('room_id', $roomId)
                ->where('status', 'active')
                ->first();

            if ($firstParticipant) {
                $user = (object) [
                    'uuid' => $firstParticipant->user_uuid,
                    'name' => $firstParticipant->name ?: '테스트 사용자',
                    'email' => $firstParticipant->email ?: 'test@example.com',
                    'avatar' => $firstParticipant->avatar,
                    'shard_id' => $firstParticipant->shard_id ?: 1
                ];
            } else {
                $user = (object) [
                    'uuid' => 'test-user-' . time(),
                    'name' => '테스트 사용자',
                    'email' => 'test@example.com',
                    'avatar' => null,
                    'shard_id' => 1
                ];
            }
        }

        try {
            // 1. 채팅방 조회
            $room = ChatRoom::findOrFail($roomId);

            // 2. 사용자 참여 여부 확인
            $participant = ChatParticipant::where('room_id', $room->id)
                ->where('user_uuid', $user->uuid)
                ->where('status', 'active')
                ->first();

            if (!$participant) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => '이 채팅방에 참여하지 않았습니다.'
                    ], 403);
                }

                return redirect()->route('home.chat.index')
                    ->withErrors(['error' => '이 채팅방에 참여하지 않았습니다.']);
            }

            // 3. 실제 storage 디렉토리에서 이미지 파일들 조회
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
            $roomStoragePath = storage_path("app/public/chat/room/{$roomId}");

            $imageFiles = collect();

            if (is_dir($roomStoragePath)) {
                // 재귀적으로 모든 이미지 파일 찾기 (thumbnails 제외)
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($roomStoragePath)
                );

                foreach ($files as $file) {
                    if ($file->isFile()) {
                        $extension = strtolower($file->getExtension());
                        $path = $file->getPathname();

                        // 이미지 확장자이고 썸네일 디렉토리가 아닌 경우
                        if (in_array($extension, $imageExtensions) &&
                            !str_contains($path, '/thumbnails/')) {

                            $relativePath = str_replace(storage_path('app/public/'), '', $path);

                            $imageFiles->push((object) [
                                'id' => md5($path), // 파일 경로의 해시를 ID로 사용
                                'original_name' => $file->getFilename(),
                                'file_size' => $file->getSize(),
                                'created_at' => \Carbon\Carbon::createFromTimestamp($file->getMTime()),
                                'storage_path' => $relativePath,
                                'full_path' => $path,
                                'extension' => $extension
                            ]);
                        }
                    }
                }

                // 생성일 기준 내림차순 정렬
                $imageFiles = $imageFiles->sortByDesc('created_at');
            }

            // 수동 페이지네이션
            $perPage = 20;
            $currentPage = request()->get('page', 1);
            $totalItems = $imageFiles->count();
            $offset = ($currentPage - 1) * $perPage;

            $paginatedFiles = $imageFiles->slice($offset, $perPage)->values();

            // Laravel Paginator 형태로 변환
            $imageFiles = new \Illuminate\Pagination\LengthAwarePaginator(
                $paginatedFiles,
                $totalItems,
                $perPage,
                $currentPage,
                [
                    'path' => request()->url(),
                    'pageName' => 'page',
                ]
            );

            // 4. 갤러리 통계
            $stats = [
                'total_images' => $imageFiles->total(),
                'current_page' => $imageFiles->currentPage(),
                'total_pages' => $imageFiles->lastPage(),
            ];

            // 5. 현재 사용자가 방장인지 확인
            $isRoomOwner = ($room->owner_uuid === $user->uuid);

            Log::info('이미지 갤러리 접근', [
                'room_id' => $roomId,
                'room_title' => $room->title,
                'user_uuid' => $user->uuid,
                'user_name' => $user->name,
                'total_images' => $stats['total_images']
            ]);

            // API 요청인 경우 JSON 반환
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'room' => $room,
                    'images' => $imageFiles,
                    'stats' => $stats,
                    'user' => $user,
                    'isRoomOwner' => $isRoomOwner
                ]);
            }

            return view('jiny-chat::home.room.images', [
                'room' => $room,
                'imageFiles' => $imageFiles,
                'stats' => $stats,
                'user' => $user,
                'isRoomOwner' => $isRoomOwner
            ]);

        } catch (\Exception $e) {
            Log::error('이미지 갤러리 로드 실패', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
                'user_uuid' => $user->uuid ?? null,
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => '이미지 갤러리를 불러오는 중 오류가 발생했습니다.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }

            return back()->with('error', '이미지 갤러리를 불러오는 중 오류가 발생했습니다.');
        }
    }
}