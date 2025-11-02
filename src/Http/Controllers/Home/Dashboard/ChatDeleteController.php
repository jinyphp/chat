<?php

namespace Jiny\Chat\Http\Controllers\Home\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;

/**
 * ChatDeleteController - 채팅방 삭제 처리
 *
 * 복잡한 채팅방 삭제 로직:
 * 1. 채팅방 테이블에서 삭제
 * 2. 참여자 목록 삭제
 * 3. SQLite 데이터베이스 파일 삭제
 * 4. 관련 첨부파일 삭제
 */
class ChatDeleteController extends Controller
{
    public function __invoke(Request $request, $roomId)
    {
        // JWT 인증된 사용자 정보 가져오기
        $authUser = auth()->user();
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => '로그인이 필요합니다.'
            ], 401);
        }

        $user = (object) [
            'uuid' => $authUser->uuid ?? 'user-' . $authUser->id,
            'name' => $authUser->name ?? 'Unknown User',
            'email' => $authUser->email ?? 'unknown@example.com'
        ];

        try {
            DB::beginTransaction();

            // 1. 채팅방 조회 및 권한 확인
            $room = ChatRoom::findOrFail($roomId);

            // 방장 권한 확인
            if ($room->owner_uuid !== $user->uuid) {
                return response()->json([
                    'success' => false,
                    'message' => '채팅방을 삭제할 권한이 없습니다. 방장만 삭제할 수 있습니다.'
                ], 403);
            }

            Log::info('채팅방 삭제 시작', [
                'room_id' => $roomId,
                'room_title' => $room->title,
                'user_uuid' => $user->uuid
            ]);

            // 2. 채팅방 참여자 목록 삭제
            $this->deleteParticipants($roomId);

            // 3. SQLite 데이터베이스 파일 삭제
            $this->deleteSqliteDatabase($room);

            // 4. 관련 첨부파일 삭제
            $this->deleteAttachments($roomId);

            // 5. 채팅방 테이블에서 삭제
            $room->delete();

            DB::commit();

            Log::info('채팅방 삭제 완료', [
                'room_id' => $roomId,
                'room_title' => $room->title
            ]);

            return response()->json([
                'success' => true,
                'message' => '채팅방이 성공적으로 삭제되었습니다.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('채팅방 삭제 실패', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '채팅방 삭제 중 오류가 발생했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 채팅방 참여자 목록 삭제
     */
    private function deleteParticipants($roomId)
    {
        $deletedCount = ChatParticipant::where('room_id', $roomId)->delete();

        Log::info('참여자 삭제 완료', [
            'room_id' => $roomId,
            'deleted_participants' => $deletedCount
        ]);
    }

    /**
     * SQLite 데이터베이스 파일 삭제
     */
    private function deleteSqliteDatabase($room)
    {
        // 채팅방 ID 기반으로 SQLite 파일 찾아서 삭제
        $chatBasePath = database_path('chat');
        $this->findAndDeleteSqliteFiles($chatBasePath, $room->id);

        Log::info('SQLite 파일 삭제 시도', [
            'room_id' => $room->id,
            'room_code' => $room->code,
            'search_path' => $chatBasePath
        ]);
    }

    /**
     * 채팅방 관련 첨부파일 삭제
     */
    private function deleteAttachments($roomId)
    {
        try {
            // chat_files 테이블이 있다면 해당 파일들 조회 및 삭제
            $files = DB::table('chat_files')->where('room_id', $roomId)->get();

            foreach ($files as $file) {
                // 실제 파일 삭제
                if ($file->file_path && Storage::exists($file->file_path)) {
                    Storage::delete($file->file_path);
                }
            }

            // 파일 레코드 삭제
            $deletedFiles = DB::table('chat_files')->where('room_id', $roomId)->delete();

            Log::info('첨부파일 삭제 완료', [
                'room_id' => $roomId,
                'deleted_files' => $deletedFiles
            ]);

        } catch (\Exception $e) {
            Log::warning('첨부파일 삭제 중 오류 (계속 진행)', [
                'room_id' => $roomId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * SQLite 파일 재귀적으로 찾아서 삭제
     */
    private function findAndDeleteSqliteFiles($directory, $roomId)
    {
        if (!is_dir($directory)) {
            Log::warning('SQLite 검색 디렉토리가 존재하지 않음', [
                'directory' => $directory
            ]);
            return;
        }

        $targetFilename = "room-{$roomId}.sqlite";
        $deletedFiles = [];

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getFilename() === $targetFilename) {
                    $filePath = $file->getPathname();

                    if (unlink($filePath)) {
                        $deletedFiles[] = $filePath;

                        Log::info('SQLite 데이터베이스 파일 삭제 완료', [
                            'room_id' => $roomId,
                            'filename' => $targetFilename,
                            'file_path' => $filePath
                        ]);

                        // 빈 디렉토리 정리
                        $this->cleanupEmptyDirectory(dirname($filePath));
                    } else {
                        Log::error('SQLite 파일 삭제 실패', [
                            'room_id' => $roomId,
                            'file_path' => $filePath
                        ]);
                    }
                }
            }

            if (empty($deletedFiles)) {
                Log::warning('삭제할 SQLite 파일을 찾을 수 없음', [
                    'room_id' => $roomId,
                    'target_filename' => $targetFilename,
                    'search_directory' => $directory
                ]);
            } else {
                Log::info('SQLite 파일 삭제 요약', [
                    'room_id' => $roomId,
                    'deleted_count' => count($deletedFiles),
                    'deleted_files' => $deletedFiles
                ]);
            }

        } catch (\Exception $e) {
            Log::error('SQLite 파일 삭제 중 예외 발생', [
                'room_id' => $roomId,
                'directory' => $directory,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 채팅방 코드를 기반으로 SQLite 파일 경로 생성 (레거시)
     */
    private function getChatDatabasePath($code)
    {
        $path = database_path('chat');
        $path .= DIRECTORY_SEPARATOR . substr($code, 0, 2);
        $path .= DIRECTORY_SEPARATOR . substr($code, 2, 2);
        $path .= DIRECTORY_SEPARATOR . substr($code, 4, 2);
        $path .= DIRECTORY_SEPARATOR . $code . '.sqlite';

        return $path;
    }

    /**
     * 빈 디렉토리 정리 (단일 디렉토리)
     */
    private function cleanupEmptyDirectory($dirPath)
    {
        if (is_dir($dirPath) && count(scandir($dirPath)) == 2) { // . 과 .. 만 있으면 빈 디렉토리
            rmdir($dirPath);
            Log::info('빈 디렉토리 삭제', ['path' => $dirPath]);

            // 부모 디렉토리도 확인하여 정리 (재귀적으로)
            $parentDir = dirname($dirPath);
            $basePath = database_path('chat');
            if ($parentDir !== $basePath && strpos($parentDir, $basePath) === 0) {
                $this->cleanupEmptyDirectory($parentDir);
            }
        }
    }
}