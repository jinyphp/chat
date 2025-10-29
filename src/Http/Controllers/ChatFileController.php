<?php

namespace Jiny\Chat\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Jiny\Chat\Models\ChatFile;

class ChatFileController
{
    /**
     * 파일 다운로드
     */
    public function download(Request $request, string $fileUuid)
    {
        try {
            $chatFile = ChatFile::where('uuid', $fileUuid)
                ->where('is_deleted', false)
                ->first();

            if (!$chatFile) {
                abort(404, '파일을 찾을 수 없습니다.');
            }

            // 파일 존재 확인
            if (!Storage::disk('public')->exists($chatFile->file_path)) {
                abort(404, '파일이 존재하지 않습니다.');
            }

            // 파일 스트림 반환
            $filePath = Storage::disk('public')->path($chatFile->file_path);

            return response()->download(
                $filePath,
                $chatFile->original_name,
                [
                    'Content-Type' => $chatFile->mime_type,
                    'Content-Length' => $chatFile->file_size,
                ]
            );

        } catch (\Exception $e) {
            \Log::error('파일 다운로드 실패', [
                'error' => $e->getMessage(),
                'file_uuid' => $fileUuid,
                'ip' => $request->ip(),
            ]);

            abort(500, '파일 다운로드에 실패했습니다.');
        }
    }

    /**
     * 이미지 미리보기 (인라인 표시)
     */
    public function preview(Request $request, string $fileUuid)
    {
        try {
            $chatFile = ChatFile::where('uuid', $fileUuid)
                ->where('is_deleted', false)
                ->where('file_type', 'image')
                ->first();

            if (!$chatFile) {
                abort(404, '이미지를 찾을 수 없습니다.');
            }

            // 파일 존재 확인
            if (!Storage::disk('public')->exists($chatFile->file_path)) {
                abort(404, '이미지가 존재하지 않습니다.');
            }

            // 이미지 스트림 반환 (인라인)
            $filePath = Storage::disk('public')->path($chatFile->file_path);

            return response()->file(
                $filePath,
                [
                    'Content-Type' => $chatFile->mime_type,
                    'Content-Disposition' => 'inline; filename="' . $chatFile->original_name . '"',
                ]
            );

        } catch (\Exception $e) {
            \Log::error('이미지 미리보기 실패', [
                'error' => $e->getMessage(),
                'file_uuid' => $fileUuid,
                'ip' => $request->ip(),
            ]);

            abort(500, '이미지 미리보기에 실패했습니다.');
        }
    }
}