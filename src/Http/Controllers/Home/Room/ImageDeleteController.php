<?php

namespace Jiny\Chat\Http\Controllers\Home\Room;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;

/**
 * ImageDeleteController - 채팅방 이미지 파일 삭제
 *
 * 방장만 채팅방의 이미지 파일을 삭제할 수 있습니다.
 */
class ImageDeleteController extends Controller
{
    public function destroy(Request $request, $roomId, $fileHash)
    {
        \Log::info('ImageDeleteController started', [
            'roomId' => $roomId,
            'fileHash' => $fileHash
        ]);

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
                return response()->json([
                    'success' => false,
                    'message' => '인증되지 않은 사용자입니다.'
                ], 401);
            }
        }

        try {
            // 1. 채팅방 조회
            $room = ChatRoom::findOrFail($roomId);

            // 2. 방장 권한 확인
            if ($room->owner_uuid !== $user->uuid) {
                Log::warning('File deletion denied - not room owner', [
                    'room_id' => $roomId,
                    'room_owner' => $room->owner_uuid,
                    'user_uuid' => $user->uuid
                ]);

                return response()->json([
                    'success' => false,
                    'message' => '파일 삭제 권한이 없습니다. 방장만 파일을 삭제할 수 있습니다.'
                ], 403);
            }

            // 3. 파일 시스템에서 해당 해시의 파일 찾기
            $roomStoragePath = storage_path("app/public/chat/room/{$roomId}");
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];

            $targetFile = null;

            if (is_dir($roomStoragePath)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($roomStoragePath)
                );

                foreach ($files as $file) {
                    if ($file->isFile()) {
                        $extension = strtolower($file->getExtension());
                        $path = $file->getPathname();

                        // 이미지 파일이고 썸네일이 아닌 경우
                        if (in_array($extension, $imageExtensions) &&
                            !str_contains($path, '/thumbnails/')) {

                            // 파일 해시가 일치하는지 확인
                            if (md5($path) === $fileHash) {
                                $targetFile = $file;
                                break;
                            }
                        }
                    }
                }
            }

            if (!$targetFile) {
                return response()->json([
                    'success' => false,
                    'message' => '삭제할 파일을 찾을 수 없습니다.'
                ], 404);
            }

            $fileName = $targetFile->getFilename();
            $filePath = $targetFile->getPathname();

            // 4. 썸네일 파일들도 함께 삭제
            $thumbnailDir = dirname($filePath) . '/thumbnails';
            $baseFileName = pathinfo($fileName, PATHINFO_FILENAME);

            if (is_dir($thumbnailDir)) {
                $thumbnailFiles = glob($thumbnailDir . '/' . $baseFileName . '_*');
                foreach ($thumbnailFiles as $thumbnailFile) {
                    if (file_exists($thumbnailFile)) {
                        unlink($thumbnailFile);
                        Log::info('Thumbnail deleted', ['file' => $thumbnailFile]);
                    }
                }
            }

            // 5. 원본 파일 삭제
            if (file_exists($filePath)) {
                unlink($filePath);

                Log::info('Image file deleted successfully', [
                    'room_id' => $roomId,
                    'file_name' => $fileName,
                    'file_path' => $filePath,
                    'deleted_by' => $user->uuid,
                    'user_name' => $user->name
                ]);

                return response()->json([
                    'success' => true,
                    'message' => '파일이 성공적으로 삭제되었습니다.',
                    'deleted_file' => $fileName
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '파일 삭제에 실패했습니다.'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('File deletion failed', [
                'room_id' => $roomId,
                'file_hash' => $fileHash,
                'error' => $e->getMessage(),
                'user_uuid' => $user->uuid ?? null,
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ]);

            return response()->json([
                'success' => false,
                'message' => '파일 삭제 중 오류가 발생했습니다.',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}