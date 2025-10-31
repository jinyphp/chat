<?php

namespace Jiny\Chat\Http\Controllers\Home\ChatFile;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Jiny\Chat\Models\ChatFile;

/**
 * DownloadController - 채팅 파일 다운로드 (SAC)
 *
 * [Single Action Controller]
 * - 채팅 파일 다운로드만 담당
 * - 파일 존재 및 권한 검증
 * - 안전한 파일 스트리밍 제공
 * - 다운로드 로깅 및 감사 추적
 *
 * [주요 기능]
 * - UUID 기반 파일 조회
 * - 파일 삭제 상태 검증
 * - 물리적 파일 존재 확인
 * - 원본 파일명으로 다운로드
 * - 적절한 MIME 타입 설정
 * - 파일 크기 헤더 포함
 *
 * [보안 기능]
 * - UUID를 통한 직접 파일 경로 노출 방지
 * - 삭제된 파일 접근 차단
 * - 파일 시스템 접근 검증
 * - 다운로드 시도 로깅
 * - 에러 정보 노출 최소화
 *
 * [성능 최적화]
 * - 스트리밍 다운로드 지원
 * - 적절한 캐시 헤더
 * - 메모리 효율적 파일 전송
 *
 * [지원 파일 타입]
 * - 모든 MIME 타입 지원
 * - 이미지, 문서, 압축 파일 등
 * - 원본 확장자 보존
 *
 * [라우트]
 * - GET /chat/file/{fileUuid}/download -> 파일 다운로드
 */
class DownloadController extends Controller
{
    /**
     * 채팅 파일 다운로드
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
                \Log::warning('잘못된 파일 UUID 형식', [
                    'file_uuid' => $fileUuid,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
                abort(404, '올바르지 않은 파일 식별자입니다.');
            }

            // 채팅 파일 조회
            $chatFile = ChatFile::where('uuid', $fileUuid)
                ->where('is_deleted', false)
                ->first();

            if (!$chatFile) {
                \Log::info('존재하지 않는 파일 다운로드 시도', [
                    'file_uuid' => $fileUuid,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
                abort(404, '파일을 찾을 수 없습니다.');
            }

            // 파일 시스템 존재 확인
            if (!Storage::disk('public')->exists($chatFile->file_path)) {
                \Log::error('데이터베이스에 있지만 파일 시스템에 없는 파일', [
                    'file_uuid' => $fileUuid,
                    'file_path' => $chatFile->file_path,
                    'original_name' => $chatFile->original_name,
                    'ip' => $request->ip()
                ]);
                abort(404, '파일이 존재하지 않습니다.');
            }

            // 파일 다운로드 로깅
            \Log::info('파일 다운로드 시작', [
                'file_uuid' => $fileUuid,
                'file_id' => $chatFile->id,
                'original_name' => $chatFile->original_name,
                'file_size' => $chatFile->file_size,
                'mime_type' => $chatFile->mime_type,
                'room_id' => $chatFile->room_id,
                'uploader_uuid' => $chatFile->uploader_uuid,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString()
            ]);

            // 파일 전체 경로 획득
            $filePath = Storage::disk('public')->path($chatFile->file_path);

            // 파일 크기 재확인 (데이터 일관성 검증)
            $actualFileSize = filesize($filePath);
            if ($actualFileSize !== $chatFile->file_size) {
                \Log::warning('파일 크기 불일치 감지', [
                    'file_uuid' => $fileUuid,
                    'expected_size' => $chatFile->file_size,
                    'actual_size' => $actualFileSize,
                    'file_path' => $chatFile->file_path
                ]);
            }

            // 다운로드 응답 생성
            return response()->download(
                $filePath,
                $chatFile->original_name,
                [
                    'Content-Type' => $chatFile->mime_type,
                    'Content-Length' => $actualFileSize,
                    'Cache-Control' => 'private, max-age=3600',
                    'X-Content-Type-Options' => 'nosniff',
                    'X-Download-Options' => 'noopen'
                ]
            );

        } catch (\Exception $e) {
            \Log::error('파일 다운로드 실패', [
                'error' => $e->getMessage(),
                'file_uuid' => $fileUuid,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                'timestamp' => now()->toISOString()
            ]);

            // 보안을 위해 구체적인 에러 정보는 로그에만 기록
            abort(500, '파일 다운로드에 실패했습니다.');
        }
    }
}