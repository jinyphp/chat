<?php

namespace Jiny\Chat\Http\Controllers\Home\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Jiny\Chat\Models\ChatRoom;

/**
 * ChatEditController - 채팅방 수정 처리
 *
 * 채팅방 수정 기능:
 * 1. 수정 페이지 표시 (edit 메서드)
 * 2. 채팅방 정보 업데이트 (update 메서드)
 * 3. 이미지 업로드 처리
 * 4. 비밀번호 변경 처리
 */
class ChatEditController extends Controller
{
    /**
     * 채팅방 수정 페이지 표시
     */
    public function edit(Request $request, $roomId)
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

        try {
            // 1. 채팅방 조회 및 권한 확인
            $room = ChatRoom::findOrFail($roomId);

            // 방장 권한 확인
            if ($room->owner_uuid !== $user->uuid) {
                return redirect()->route('home.chat.index')
                    ->withErrors(['error' => '채팅방을 수정할 권한이 없습니다. 방장만 수정할 수 있습니다.']);
            }

            Log::info('채팅방 수정 페이지 접근', [
                'room_id' => $roomId,
                'room_title' => $room->title,
                'user_uuid' => $user->uuid
            ]);

            return view('jiny-chat::home.dashboard.edit', compact('room'));

        } catch (\Exception $e) {
            Log::error('채팅방 수정 페이지 로드 실패', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
                'user_uuid' => $user->uuid
            ]);

            return redirect()->route('home.chat.index')
                ->withErrors(['error' => '채팅방을 찾을 수 없습니다.']);
        }
    }

    /**
     * 채팅방 수정 처리
     */
    public function update(Request $request, $roomId)
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

            // 1. 채팅방 조회 및 권한 확인
            $room = ChatRoom::findOrFail($roomId);

            // 방장 권한 확인
            if ($room->owner_uuid !== $user->uuid) {
                return redirect()->route('home.chat.index')
                    ->withErrors(['error' => '채팅방을 수정할 권한이 없습니다. 방장만 수정할 수 있습니다.']);
            }

            Log::info('채팅방 수정 시작', [
                'room_id' => $roomId,
                'room_title' => $room->title,
                'user_uuid' => $user->uuid
            ]);

            // 2. 슬러그 처리 (빈 값이면 제목에서 자동 생성)
            if (empty($validated['slug'])) {
                $validated['slug'] = $this->generateSlug($validated['title'], $room->id);
            } else {
                // 중복 슬러그 체크 (자기 자신 제외)
                $duplicateSlug = ChatRoom::where('slug', $validated['slug'])
                    ->where('id', '!=', $room->id)
                    ->exists();

                if ($duplicateSlug) {
                    $validated['slug'] = $this->generateSlug($validated['slug'], $room->id);
                }
            }

            // 3. 이미지 업로드 처리
            $imagePath = $room->image; // 기존 이미지 유지
            if ($request->hasFile('room_image')) {
                // 기존 이미지 삭제
                if ($room->image && Storage::exists('public/' . $room->image)) {
                    Storage::delete('public/' . $room->image);
                }

                $imagePath = $this->uploadRoomImage($request->file('room_image'));
            }

            // 4. 업데이트할 데이터 준비
            $updateData = [
                'title' => $validated['title'],
                'slug' => $validated['slug'],
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
                'is_public' => $validated['is_public'] ?? false,
                'allow_join' => $validated['allow_join'] ?? false,
                'allow_invite' => $validated['allow_invite'] ?? false,
                'max_participants' => $validated['max_participants'] ?? 0,
                'image' => $imagePath,
                'updated_at' => now()
            ];

            // 5. 비밀번호 처리 (입력된 경우에만 변경)
            if (!empty($validated['password'])) {
                $updateData['password'] = bcrypt($validated['password']);
                Log::info('채팅방 비밀번호 변경', [
                    'room_id' => $roomId,
                    'user_uuid' => $user->uuid
                ]);
            }

            // 6. 채팅방 업데이트
            $room->update($updateData);

            DB::commit();

            Log::info('채팅방 수정 완료', [
                'room_id' => $roomId,
                'room_title' => $room->title,
                'changes' => array_keys($updateData)
            ]);

            return redirect()->route('home.chat.index')
                ->with('success', '채팅방 "' . $room->title . '"이 성공적으로 수정되었습니다.');

        } catch (\Exception $e) {
            DB::rollBack();

            // 업로드된 이미지 파일 정리 (새로 업로드된 경우만)
            if (isset($imagePath) && $imagePath !== $room->image && Storage::exists('public/' . $imagePath)) {
                Storage::delete('public/' . $imagePath);
            }

            Log::error('채팅방 수정 실패', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_uuid' => $user->uuid
            ]);

            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => '채팅방 수정 중 오류가 발생했습니다: ' . $e->getMessage()]);
        }
    }

    /**
     * 제목을 기반으로 슬러그 생성 (기존 ID 제외)
     */
    private function generateSlug($title, $excludeId = null)
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        // 중복 슬러그 체크 및 번호 추가 (자기 자신 제외)
        while (true) {
            $query = ChatRoom::where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            if (!$query->exists()) {
                break;
            }

            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
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
}