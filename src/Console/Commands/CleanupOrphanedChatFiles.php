<?php

namespace Jiny\Chat\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Jiny\Chat\Models\ChatRoom;

/**
 * Orphaned SQLite 파일 정리 명령어
 */
class CleanupOrphanedChatFiles extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'chat:cleanup-orphaned-files
                            {--dry-run : 실제 삭제하지 않고 확인만 수행}
                            {--force : 확인 없이 강제 삭제}';

    /**
     * The console command description.
     */
    protected $description = 'Delete orphaned SQLite chat files that no longer have corresponding chat rooms';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $isForce = $this->option('force');

        $this->info('🧹 Orphaned Chat Files Cleanup');
        $this->line('================================');

        // 1. 현재 존재하는 채팅방 ID 목록 조회
        $existingRoomIds = ChatRoom::pluck('id')->toArray();
        $this->info("📋 Active chat rooms: " . count($existingRoomIds));
        $this->line("Room IDs: " . implode(', ', $existingRoomIds));

        // 2. SQLite 파일 스캔
        $chatBasePath = database_path('chat');

        if (!is_dir($chatBasePath)) {
            $this->error("❌ Chat database directory not found: {$chatBasePath}");
            return 1;
        }

        $this->info("🔍 Scanning directory: {$chatBasePath}");

        $orphanedFiles = $this->findOrphanedFiles($chatBasePath, $existingRoomIds);

        if (empty($orphanedFiles)) {
            $this->info("✅ No orphaned files found!");
            return 0;
        }

        $this->warn("🗑️  Found " . count($orphanedFiles) . " orphaned files:");

        foreach ($orphanedFiles as $file) {
            $this->line("  - {$file['path']} (Room ID: {$file['room_id']}, Size: {$file['size']})");
        }

        if ($isDryRun) {
            $this->info("🔍 Dry run mode - files were not deleted");
            return 0;
        }

        // 3. 삭제 확인
        if (!$isForce) {
            if (!$this->confirm('Do you want to delete these orphaned files?')) {
                $this->info("❌ Operation cancelled");
                return 0;
            }
        }

        // 4. 파일 삭제 실행
        $deletedCount = 0;
        $deletedSize = 0;

        foreach ($orphanedFiles as $file) {
            try {
                if (unlink($file['path'])) {
                    $deletedCount++;
                    $deletedSize += $file['size'];
                    $this->line("✅ Deleted: {$file['path']}");

                    // 빈 디렉토리 정리
                    $this->cleanupEmptyDirectory(dirname($file['path']));
                } else {
                    $this->error("❌ Failed to delete: {$file['path']}");
                }
            } catch (\Exception $e) {
                $this->error("❌ Error deleting {$file['path']}: " . $e->getMessage());
            }
        }

        $this->info("🎉 Cleanup completed!");
        $this->line("📊 Statistics:");
        $this->line("  - Files deleted: {$deletedCount}");
        $this->line("  - Space freed: " . $this->formatBytes($deletedSize));

        Log::info('Chat orphaned files cleanup completed', [
            'deleted_count' => $deletedCount,
            'deleted_size' => $deletedSize,
            'deleted_files' => array_column($orphanedFiles, 'path')
        ]);

        return 0;
    }

    /**
     * Orphaned SQLite 파일 찾기
     */
    private function findOrphanedFiles($directory, $existingRoomIds)
    {
        $orphanedFiles = [];

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && preg_match('/^room-(\d+)\.sqlite$/', $file->getFilename(), $matches)) {
                    $roomId = (int) $matches[1];

                    // 해당 채팅방이 더 이상 존재하지 않으면 orphaned 파일
                    if (!in_array($roomId, $existingRoomIds)) {
                        $orphanedFiles[] = [
                            'path' => $file->getPathname(),
                            'room_id' => $roomId,
                            'size' => $file->getSize(),
                            'modified' => date('Y-m-d H:i:s', $file->getMTime())
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            $this->error("Error scanning directory: " . $e->getMessage());
        }

        return $orphanedFiles;
    }

    /**
     * 빈 디렉토리 정리
     */
    private function cleanupEmptyDirectory($dirPath)
    {
        if (is_dir($dirPath) && count(scandir($dirPath)) == 2) { // . 과 .. 만 있으면 빈 디렉토리
            if (rmdir($dirPath)) {
                $this->line("🗂️  Removed empty directory: {$dirPath}");

                // 부모 디렉토리도 확인하여 정리 (재귀적으로)
                $parentDir = dirname($dirPath);
                $basePath = database_path('chat');
                if ($parentDir !== $basePath && strpos($parentDir, $basePath) === 0) {
                    $this->cleanupEmptyDirectory($parentDir);
                }
            }
        }
    }

    /**
     * 바이트를 사람이 읽을 수 있는 형태로 변환
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}