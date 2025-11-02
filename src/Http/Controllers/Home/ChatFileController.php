<?php

namespace Jiny\Chat\Http\Controllers\Home;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Jiny\Chat\Models\ChatFile;
use Jiny\Chat\Models\ChatRoomFile;
use Jiny\Chat\Models\ChatRoom;

class ChatFileController extends Controller
{
    /**
     * 파일 조회/표시
     */
    public function show(Request $request, $fileId)
    {
        try {
            // JWT 또는 세션 인증 확인
            $user = null;
            $authMethod = 'none';

            // 1. JWT 인증 시도
            if (class_exists('\JwtAuth')) {
                try {
                    $jwtUser = \JwtAuth::user(request());
                    if ($jwtUser && isset($jwtUser->uuid)) {
                        // 샤딩된 테이블에서 실제 사용자 조회
                        $user = \Shard::getUserByUuid($jwtUser->uuid);
                        if ($user) {
                            $authMethod = 'jwt';
                        }
                    }
                } catch (\Exception $e) {
                    \Log::debug('JWT 인증 실패', ['error' => $e->getMessage()]);
                }
            }

            // 2. 세션 인증 시도 (JWT가 없는 경우)
            if (!$user && auth()->check()) {
                $user = auth()->user();
                $authMethod = 'session';
            }

            // 3. 개발 환경에서는 임시로 더 유연한 인증 허용
            if (!$user && app()->environment('local')) {
                // 개발 환경에서는 첫 번째 사용자로 임시 인증
                $user = \App\Models\User::first();
                $authMethod = 'development';
                \Log::info('개발 환경 - 임시 사용자 인증', ['user_id' => $user?->id]);
            }

            if (!$user) {
                \Log::warning('파일 접근 - 인증 실패', [
                    'file_id' => $fileId,
                    'auth_method_tried' => $authMethod,
                    'has_session' => auth()->check(),
                    'user_agent' => request()->userAgent()
                ]);
                return response()->json(['error' => '인증이 필요합니다.'], 401);
            }

            \Log::info('파일 접근 - 인증 성공', [
                'file_id' => $fileId,
                'auth_method' => $authMethod,
                'user_uuid' => $user->uuid ?? 'unknown'
            ]);

            // 파일 정보 조회
            $file = $this->getFileInfo($fileId);
            if (!$file) {
                \Log::warning('파일 조회 실패', [
                    'file_id' => $fileId,
                    'user_uuid' => $user->uuid ?? 'unknown'
                ]);

                // 개발 환경에서는 더 상세한 오류 정보 제공
                if (app()->environment('local')) {
                    return response()->json([
                        'error' => '파일을 찾을 수 없습니다.',
                        'debug' => [
                            'file_id' => $fileId,
                            'searched_in' => 'ChatRoomFile tables'
                        ]
                    ], 404);
                }

                return response()->json(['error' => '파일을 찾을 수 없습니다.'], 404);
            }

            // 사용자 권한 확인 (채팅방 참여자인지 확인)
            if (!$this->hasAccessToFile($user, $file)) {
                return response()->json(['error' => '파일에 접근할 권한이 없습니다.'], 403);
            }

            // 파일 경로 확인
            if (!Storage::disk('public')->exists($file->file_path)) {
                \Log::warning('파일 물리적 부재', [
                    'file_id' => $fileId,
                    'file_path' => $file->file_path,
                    'full_path' => storage_path('app/public/' . $file->file_path),
                    'file_exists' => file_exists(storage_path('app/public/' . $file->file_path))
                ]);
                return response()->json(['error' => '파일이 존재하지 않습니다.'], 404);
            }

            // 파일 내용 반환
            $mimeType = $file->mime_type ?: 'application/octet-stream';

            // 실제 파일에서 MIME 타입 확인 (fallback)
            if (Storage::disk('public')->exists($file->file_path)) {
                $content = Storage::disk('public')->get($file->file_path);

                // 이미지 파일인 경우 실제 MIME 타입 감지
                if (in_array($file->file_type, ['image'])) {
                    $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
                    $detectedMimeType = $fileInfo->buffer($content);
                    if ($detectedMimeType && str_starts_with($detectedMimeType, 'image/')) {
                        $mimeType = $detectedMimeType;
                    }
                }
            } else {
                return response()->json(['error' => '파일이 존재하지 않습니다.'], 404);
            }

            return Response::make($content, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . $file->original_name . '"',
                'Cache-Control' => 'private, max-age=3600',
                'Content-Length' => strlen($content),
            ]);

        } catch (\Exception $e) {
            \Log::error('파일 조회 실패', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
                'user_uuid' => $user->uuid ?? 'unknown'
            ]);

            return response()->json(['error' => '파일 조회 중 오류가 발생했습니다.'], 500);
        }
    }

    /**
     * 파일 다운로드
     */
    public function download(Request $request, $fileId)
    {
        try {
            // JWT 또는 세션 인증 확인
            $user = null;

            // 1. JWT 인증 시도
            if (class_exists('\JwtAuth')) {
                try {
                    $user = \JwtAuth::user(request());
                } catch (\Exception $e) {
                    // JWT 인증 실패 시 계속 진행
                }
            }

            // 2. 세션 인증 시도 (JWT가 없는 경우)
            if (!$user && auth()->check()) {
                $user = auth()->user();
            }

            if (!$user) {
                return response()->json(['error' => '인증이 필요합니다.'], 401);
            }

            // 파일 정보 조회
            $file = $this->getFileInfo($fileId);
            if (!$file) {
                return response()->json(['error' => '파일을 찾을 수 없습니다.'], 404);
            }

            // 사용자 권한 확인
            if (!$this->hasAccessToFile($user, $file)) {
                return response()->json(['error' => '파일에 접근할 권한이 없습니다.'], 403);
            }

            // 파일 경로 확인
            if (!Storage::disk('public')->exists($file->file_path)) {
                return response()->json(['error' => '파일이 존재하지 않습니다.'], 404);
            }

            // 다운로드 이벤트 로깅
            \Log::info('파일 다운로드', [
                'file_id' => $fileId,
                'file_name' => $file->original_name,
                'user_uuid' => $user->uuid,
                'file_size' => $file->file_size
            ]);

            // 파일 다운로드
            return Storage::disk('public')->download($file->file_path, $file->original_name);

        } catch (\Exception $e) {
            \Log::error('파일 다운로드 실패', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
                'user_uuid' => $user->uuid ?? 'unknown'
            ]);

            return response()->json(['error' => '파일 다운로드 중 오류가 발생했습니다.'], 500);
        }
    }

    /**
     * 이미지 미리보기 (썸네일)
     */
    public function thumbnail(Request $request, $fileId)
    {
        try {
            // JWT 또는 세션 인증 확인
            $user = null;

            // 1. JWT 인증 시도
            if (class_exists('\JwtAuth')) {
                try {
                    $user = \JwtAuth::user(request());
                } catch (\Exception $e) {
                    // JWT 인증 실패 시 계속 진행
                }
            }

            // 2. 세션 인증 시도 (JWT가 없는 경우)
            if (!$user && auth()->check()) {
                $user = auth()->user();
            }

            if (!$user) {
                return response()->json(['error' => '인증이 필요합니다.'], 401);
            }

            // 파일 정보 조회
            $file = $this->getFileInfo($fileId);
            if (!$file) {
                return response()->json(['error' => '파일을 찾을 수 없습니다.'], 404);
            }

            // 이미지 파일인지 확인
            if (!$this->isImageFile($file)) {
                return response()->json(['error' => '이미지 파일이 아닙니다.'], 400);
            }

            // 사용자 권한 확인
            if (!$this->hasAccessToFile($user, $file)) {
                return response()->json(['error' => '파일에 접근할 권한이 없습니다.'], 403);
            }

            // 파일 경로 확인
            if (!Storage::disk('public')->exists($file->file_path)) {
                return response()->json(['error' => '파일이 존재하지 않습니다.'], 404);
            }

            $width = $request->input('w', 300);
            $height = $request->input('h', 300);

            // 썸네일 경로 생성
            $thumbnailPath = $this->generateThumbnailPath($file->file_path, $width, $height);

            // 썸네일이 이미 존재하는지 확인
            if (!Storage::disk('public')->exists($thumbnailPath)) {
                // 썸네일 생성
                $this->createThumbnail($file->file_path, $thumbnailPath, $width, $height);
            }

            // 썸네일 반환
            $content = Storage::disk('public')->get($thumbnailPath);
            $mimeType = $file->mime_type ?: 'image/jpeg';

            return Response::make($content, 200, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'public, max-age=86400', // 24시간 캐시
                'Content-Length' => strlen($content),
            ]);

        } catch (\Exception $e) {
            \Log::error('썸네일 생성 실패', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
                'user_uuid' => $user->uuid ?? 'unknown'
            ]);

            // 원본 이미지 반환
            return $this->show($request, $fileId);
        }
    }

    /**
     * 파일 정보 조회
     */
    private function getFileInfo($fileId)
    {
        // ChatRoomFile에서 모든 SQLite 데이터베이스 검색
        $roomFile = ChatRoomFile::findById($fileId);
        if ($roomFile) {
            return $roomFile;
        }

        // 기존 ChatFile에서 조회 (하위 호환성)
        if (class_exists('\\Jiny\\Chat\\Models\\ChatFile')) {
            return \Jiny\Chat\Models\ChatFile::find($fileId);
        }

        return null;
    }

    /**
     * 사용자가 파일에 접근할 권한이 있는지 확인
     */
    private function hasAccessToFile($user, $file)
    {
        try {
            // 일단 모든 인증된 사용자에게 접근 허용 (개발 중)
            // 추후 실제 권한 로직 구현 예정
            if ($user && isset($user->uuid)) {
                return true;
            }

            return false;

        } catch (\Exception $e) {
            \Log::error('파일 접근 권한 확인 실패', [
                'file_id' => $file->id,
                'user_uuid' => $user->uuid ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * 이미지 파일인지 확인
     */
    private function isImageFile($file)
    {
        // 1. file_type이 'image'인 경우 (우리 시스템)
        if ($file->file_type === 'image') {
            return true;
        }

        // 2. MIME 타입 확인
        $imageMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        $mimeType = $file->mime_type ?: '';

        if (in_array(strtolower($mimeType), $imageMimeTypes)) {
            return true;
        }

        // 3. 파일 확장자로 확인 (fallback)
        if ($file->original_name) {
            $extension = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

            if (in_array($extension, $imageExtensions)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 썸네일 경로 생성
     */
    private function generateThumbnailPath($originalPath, $width, $height)
    {
        $pathInfo = pathinfo($originalPath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'];

        return "{$directory}/thumbnails/{$filename}_{$width}x{$height}.{$extension}";
    }

    /**
     * 썸네일 생성
     */
    private function createThumbnail($originalPath, $thumbnailPath, $width, $height)
    {
        try {
            // GD 라이브러리 사용 가능 여부 확인
            if (!extension_loaded('gd')) {
                throw new \Exception('GD 라이브러리가 설치되지 않았습니다.');
            }

            $originalContent = Storage::disk('public')->get($originalPath);
            $originalImage = imagecreatefromstring($originalContent);

            if (!$originalImage) {
                throw new \Exception('이미지를 생성할 수 없습니다.');
            }

            $originalWidth = imagesx($originalImage);
            $originalHeight = imagesy($originalImage);

            // 비율 유지하면서 썸네일 크기 계산
            $ratio = min($width / $originalWidth, $height / $originalHeight);
            $newWidth = intval($originalWidth * $ratio);
            $newHeight = intval($originalHeight * $ratio);

            // 썸네일 이미지 생성
            $thumbnail = imagecreatetruecolor($newWidth, $newHeight);

            // 투명도 유지 (PNG용)
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);

            imagecopyresampled(
                $thumbnail, $originalImage,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $originalWidth, $originalHeight
            );

            // 썸네일 디렉토리 생성
            $thumbnailDir = dirname($thumbnailPath);
            if (!Storage::disk('public')->exists($thumbnailDir)) {
                Storage::disk('public')->makeDirectory($thumbnailDir);
            }

            // 썸네일 저장
            ob_start();

            $pathInfo = pathinfo($thumbnailPath);
            $extension = strtolower($pathInfo['extension']);

            switch ($extension) {
                case 'png':
                    imagepng($thumbnail);
                    break;
                case 'gif':
                    imagegif($thumbnail);
                    break;
                default:
                    imagejpeg($thumbnail, null, 90);
                    break;
            }

            $thumbnailContent = ob_get_contents();
            ob_end_clean();

            Storage::disk('public')->put($thumbnailPath, $thumbnailContent);

            // 메모리 해제
            imagedestroy($originalImage);
            imagedestroy($thumbnail);

        } catch (\Exception $e) {
            \Log::error('썸네일 생성 실패', [
                'original_path' => $originalPath,
                'thumbnail_path' => $thumbnailPath,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}