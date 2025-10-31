<?php

namespace Jiny\Chat\Http\Controllers\Home\ChatFile;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Jiny\Chat\Models\ChatFile;

/**
 * PreviewController - 채팅 이미지 미리보기 (SAC)
 *
 * [Single Action Controller]
 * - 채팅 이미지 미리보기만 담당
 * - 이미지 파일 인라인 표시
 * - 브라우저 내 직접 렌더링 지원
 * - 이미지 전용 보안 검증
 *
 * [주요 기능]
 * - UUID 기반 이미지 조회
 * - 이미지 타입 필터링
 * - 인라인 디스플레이 헤더 설정
 * - 브라우저 캐싱 최적화
 * - 이미지 스트리밍 제공
 *
 * [보안 기능]
 * - 이미지 파일만 접근 허용
 * - UUID를 통한 직접 경로 노출 방지
 * - 삭제된 이미지 접근 차단
 * - MIME 타입 검증
 * - 이미지 형식 엄격 검증
 *
 * [성능 최적화]
 * - HTTP 캐시 헤더 설정
 * - 조건부 요청 지원
 * - 스트리밍 응답
 * - 메모리 효율적 처리
 *
 * [지원 이미지 타입]
 * - JPEG, PNG, GIF, WebP
 * - SVG (선택적 지원)
 * - 동적 MIME 타입 검증
 *
 * [브라우저 지원]
 * - 모든 모던 브라우저
 * - 인라인 표시 최적화
 * - 적절한 Content-Disposition 헤더
 *
 * [라우트]
 * - GET /chat/file/{fileUuid}/preview -> 이미지 미리보기
 */
class PreviewController extends Controller
{
    /**
     * 지원하는 이미지 MIME 타입
     */
    const SUPPORTED_IMAGE_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml'
    ];

    /**
     * 채팅 이미지 미리보기
     *
     * @param Request $request
     * @param string $fileUuid 파일 UUID
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function __invoke(Request $request, string $fileUuid)
    {
        try {
            // UUID 형식 검증
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $fileUuid)) {
                \Log::warning('잘못된 이미지 UUID 형식', [
                    'file_uuid' => $fileUuid,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
                abort(404, '올바르지 않은 이미지 식별자입니다.');
            }

            // 이미지 파일 조회 (이미지 타입만)
            $chatFile = ChatFile::where('uuid', $fileUuid)
                ->where('is_deleted', false)
                ->where('file_type', 'image')
                ->first();

            if (!$chatFile) {
                \Log::info('존재하지 않는 이미지 미리보기 시도', [
                    'file_uuid' => $fileUuid,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
                abort(404, '이미지를 찾을 수 없습니다.');
            }

            // MIME 타입 추가 검증
            if (!in_array($chatFile->mime_type, self::SUPPORTED_IMAGE_TYPES)) {
                \Log::warning('지원하지 않는 이미지 타입 접근', [
                    'file_uuid' => $fileUuid,
                    'mime_type' => $chatFile->mime_type,
                    'file_type' => $chatFile->file_type,
                    'ip' => $request->ip()
                ]);
                abort(415, '지원하지 않는 이미지 형식입니다.');
            }

            // 파일 시스템 존재 확인
            if (!Storage::disk('public')->exists($chatFile->file_path)) {
                \Log::error('데이터베이스에 있지만 파일 시스템에 없는 이미지', [
                    'file_uuid' => $fileUuid,
                    'file_path' => $chatFile->file_path,
                    'original_name' => $chatFile->original_name,
                    'ip' => $request->ip()
                ]);
                abort(404, '이미지가 존재하지 않습니다.');
            }

            // 이미지 미리보기 로깅
            \Log::info('이미지 미리보기 요청', [
                'file_uuid' => $fileUuid,
                'file_id' => $chatFile->id,
                'original_name' => $chatFile->original_name,
                'file_size' => $chatFile->file_size,
                'mime_type' => $chatFile->mime_type,
                'dimensions' => $chatFile->metadata['dimensions'] ?? null,
                'room_id' => $chatFile->room_id,
                'uploader_uuid' => $chatFile->uploader_uuid,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('Referer'),
                'timestamp' => now()->toISOString()
            ]);

            // 파일 전체 경로 획득
            $filePath = Storage::disk('public')->path($chatFile->file_path);

            // 파일 크기 재확인
            $actualFileSize = filesize($filePath);
            if ($actualFileSize !== $chatFile->file_size) {
                \Log::warning('이미지 파일 크기 불일치 감지', [
                    'file_uuid' => $fileUuid,
                    'expected_size' => $chatFile->file_size,
                    'actual_size' => $actualFileSize,
                    'file_path' => $chatFile->file_path
                ]);
            }

            // ETag 생성 (캐싱 최적화)
            $etag = md5($chatFile->uuid . $chatFile->updated_at);

            // 조건부 요청 처리
            if ($request->header('If-None-Match') === $etag) {
                return response('', 304);
            }

            // 이미지 미리보기 응답 생성
            return response()->file(
                $filePath,
                [
                    'Content-Type' => $chatFile->mime_type,
                    'Content-Disposition' => 'inline; filename="' . addslashes($chatFile->original_name) . '"',
                    'Cache-Control' => 'public, max-age=86400', // 24시간 캐시
                    'ETag' => $etag,
                    'Last-Modified' => $chatFile->updated_at->format('D, d M Y H:i:s') . ' GMT',
                    'X-Content-Type-Options' => 'nosniff',
                    'X-Frame-Options' => 'SAMEORIGIN', // 이미지 임베딩 보안
                    'Content-Length' => $actualFileSize
                ]
            );

        } catch (\Exception $e) {
            \Log::error('이미지 미리보기 실패', [
                'error' => $e->getMessage(),
                'file_uuid' => $fileUuid,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('Referer'),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                'timestamp' => now()->toISOString()
            ]);

            // 보안을 위해 구체적인 에러 정보는 로그에만 기록
            abort(500, '이미지 미리보기에 실패했습니다.');
        }
    }
}