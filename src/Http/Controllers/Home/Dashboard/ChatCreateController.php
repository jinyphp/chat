<?php

namespace Jiny\Chat\Http\Controllers\Home\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;

/**
 * ChatCreateController - 새로운 채팅방 생성 처리 (SAC)
 *
 * 복잡한 채팅방 생성 로직:
 * 1. 채팅방 정보 생성 및 저장
 * 2. 생성자를 방장으로 참여자 테이블에 추가
 * 3. SQLite 데이터베이스 파일 생성
 * 4. 채팅방용 테이블들 자동 생성
 */
class ChatCreateController extends Controller
{
    public function __invoke(Request $request)
    {
        // JWT 인증된 사용자 정보 가져오기
        $authUser = auth()->user();
        if (!$authUser) {
            return redirect()->route('auth.login')
                ->withErrors(['error' => '로그인이 필요합니다.']);
        }

        $user = (object) [
            'uuid' => $authUser->uuid ?? 'user-' . $authUser->id,
            'name' => $authUser->name ?? 'Unknown User',
            'email' => $authUser->email ?? 'unknown@example.com'
        ];

        // 입력 데이터 검증
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|regex:/^[a-z0-9-]+$/',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:public,private,group',
            'is_public' => 'nullable|boolean',
            'allow_join' => 'nullable|boolean',
            'allow_invite' => 'nullable|boolean',
            'password' => 'nullable|string|min:4',
            'max_participants' => 'nullable|integer|min:0|max:1000',
            'room_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        try {
            DB::beginTransaction();

            // 1. 슬러그 자동 생성 (없는 경우)
            if (empty($validated['slug'])) {
                $validated['slug'] = $this->generateSlug($validated['title']);
            }

            // 2. 채팅방 코드 생성 (SQLite 파일명으로 사용)
            $roomCode = $this->generateRoomCode();

            // 3. 이미지 업로드 처리
            $imagePath = null;
            if ($request->hasFile('room_image')) {
                $imagePath = $this->uploadRoomImage($request->file('room_image'));
            }

            // 4. 채팅방 데이터 생성
            $roomData = [
                'title' => $validated['title'],
                'slug' => $validated['slug'],
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
                'code' => $roomCode,
                'is_public' => $validated['is_public'] ?? true,
                'allow_join' => $validated['allow_join'] ?? true,
                'allow_invite' => $validated['allow_invite'] ?? true,
                'password' => isset($validated['password']) ? bcrypt($validated['password']) : null,
                'max_participants' => $validated['max_participants'] ?? 0,
                'image' => $imagePath,
                'owner_uuid' => $user->uuid,
                'created_at' => now(),
                'updated_at' => now()
            ];

            // 5. 채팅방 생성
            $room = ChatRoom::create($roomData);

            Log::info('새 채팅방 생성', [
                'room_id' => $room->id,
                'room_title' => $room->title,
                'room_code' => $room->code,
                'owner_uuid' => $user->uuid
            ]);

            // 6. 방장을 참여자로 추가
            $this->addOwnerAsParticipant($room, $user);

            // 7. SQLite 데이터베이스 파일 생성
            $this->createSqliteDatabase($room);

            DB::commit();

            Log::info('채팅방 생성 완료', [
                'room_id' => $room->id,
                'room_title' => $room->title,
                'room_code' => $room->code
            ]);

            return redirect()->route('home.chat.index')
                ->with('success', '채팅방이 성공적으로 생성되었습니다: ' . $room->title);

        } catch (\Exception $e) {
            DB::rollBack();

            // 업로드된 이미지 파일 정리
            if (isset($imagePath) && $imagePath && Storage::exists($imagePath)) {
                Storage::delete($imagePath);
            }

            Log::error('채팅방 생성 실패', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_uuid' => $user->uuid,
                'room_data' => $validated ?? []
            ]);

            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => '채팅방 생성 중 오류가 발생했습니다: ' . $e->getMessage()]);
        }
    }

    /**
     * 제목을 기반으로 슬러그 생성
     */
    private function generateSlug($title)
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        // 중복 슬러그 체크 및 번호 추가
        while (ChatRoom::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * 고유한 채팅방 코드 생성
     */
    private function generateRoomCode()
    {
        do {
            $code = 'room_' . Str::random(8);
        } while (ChatRoom::where('code', $code)->exists());

        return $code;
    }

    /**
     * 채팅방 이미지 업로드
     */
    private function uploadRoomImage($file)
    {
        $fileName = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
        $path = 'chat/rooms/' . date('Y/m/d');

        return $file->storeAs($path, $fileName, 'public');
    }

    /**
     * 방장을 참여자로 추가
     */
    private function addOwnerAsParticipant($room, $user)
    {
        ChatParticipant::create([
            'room_id' => $room->id,
            'room_uuid' => $room->code,
            'user_uuid' => $user->uuid,
            'shard_id' => 1,
            'email' => $user->email,
            'name' => $user->name,
            'avatar' => null,
            'role' => 'owner',
            'status' => 'active',
            'permissions' => json_encode(['all']),
            'can_send_message' => 1,
            'can_invite' => 1,
            'can_moderate' => 1,
            'notifications_enabled' => 1,
            'notification_settings' => json_encode(['mentions' => true, 'all_messages' => true]),
            'last_read_at' => now(),
            'last_read_message_id' => 0,
            'unread_count' => 0,
            'joined_at' => now(),
            'last_seen_at' => now(),
            'invited_by_uuid' => null,
            'join_reason' => 'Room owner',
            'banned_at' => null,
            'banned_by_uuid' => null,
            'ban_reason' => null,
            'ban_expires_at' => null,
            'language' => 'ko'
        ]);

        Log::info('방장 참여자 추가 완료', [
            'room_id' => $room->id,
            'user_uuid' => $user->uuid,
            'role' => 'owner'
        ]);
    }

    /**
     * SQLite 데이터베이스 파일 생성
     */
    private function createSqliteDatabase($room)
    {
        // SQLite 파일 경로 생성: /database/chat/년도/월/일/room-{id}.sqlite
        $year = date('Y');
        $month = date('m');
        $day = date('d');

        $chatDbDir = database_path("chat/{$year}/{$month}/{$day}");
        $chatDbPath = $chatDbDir . "/room-{$room->id}.sqlite";

        // 디렉토리 생성
        if (!is_dir($chatDbDir)) {
            mkdir($chatDbDir, 0755, true);
        }

        // SQLite 데이터베이스 생성 및 테이블 구성
        $this->createSqliteTables($chatDbPath, $room);

        Log::info('SQLite 데이터베이스 생성 완료', [
            'room_id' => $room->id,
            'room_code' => $room->code,
            'sqlite_path' => $chatDbPath
        ]);
    }

    /**
     * SQLite 데이터베이스에 필요한 테이블들 생성
     */
    private function createSqliteTables($dbPath, $room)
    {
        $pdo = new \PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // UTF-8 인코딩 설정
        $pdo->exec("PRAGMA encoding = 'UTF-8'");
        $pdo->exec("PRAGMA journal_mode = WAL");

        // 채팅 메시지 테이블
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS chat_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id INTEGER NOT NULL,
                user_uuid VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                message_type VARCHAR(50) DEFAULT 'text',
                reply_to_id INTEGER NULL,
                is_system BOOLEAN DEFAULT FALSE,
                is_deleted BOOLEAN DEFAULT FALSE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (reply_to_id) REFERENCES chat_messages(id)
            )
        ");

        // 메시지 읽음 상태 테이블
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS chat_message_read (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                message_id INTEGER NOT NULL,
                user_uuid VARCHAR(255) NOT NULL,
                read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(message_id, user_uuid),
                FOREIGN KEY (message_id) REFERENCES chat_messages(id)
            )
        ");

        // 파일 첨부 테이블
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS chat_files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                message_id INTEGER NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_size INTEGER NOT NULL,
                file_type VARCHAR(100) NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (message_id) REFERENCES chat_messages(id)
            )
        ");

        // 메시지 즐겨찾기 테이블
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS chat_message_favourites (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                message_id INTEGER NOT NULL,
                user_uuid VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(message_id, user_uuid),
                FOREIGN KEY (message_id) REFERENCES chat_messages(id)
            )
        ");

        // 메시지 번역 테이블
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS chat_message_translations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                message_id INTEGER NOT NULL,
                user_uuid VARCHAR(255) NOT NULL,
                original_language VARCHAR(10) NOT NULL,
                target_language VARCHAR(10) NOT NULL,
                translated_text TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(message_id, user_uuid, target_language),
                FOREIGN KEY (message_id) REFERENCES chat_messages(id)
            )
        ");

        // 인덱스 생성
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_messages_room_id ON chat_messages(room_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_messages_user_uuid ON chat_messages(user_uuid)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_messages_created_at ON chat_messages(created_at)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_message_read_message_id ON chat_message_read(message_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_message_read_user_uuid ON chat_message_read(user_uuid)");

        // 채팅방 생성 시스템 메시지 추가 (초기에만)
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (room_id, user_uuid, message, message_type, is_system, created_at, updated_at)
            VALUES (?, 'system', ?, 'system', 1, datetime('now'), datetime('now'))
        ");
        $stmt->execute([
            $room->id,
            "채팅방 '{$room->title}'이 생성되었습니다. 대화를 시작해보세요!"
        ]);

        Log::info('채팅방 생성 시스템 메시지 추가', [
            'room_id' => $room->id,
            'room_title' => $room->title
        ]);

        $pdo = null; // 연결 해제
    }
}