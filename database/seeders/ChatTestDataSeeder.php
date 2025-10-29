<?php

namespace Jiny\Chat\Database\Seeders;

use Illuminate\Database\Seeder;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Models\ChatMessage;
use Carbon\Carbon;

/**
 * 채팅 시스템 테스트 데이터 시더
 */
class ChatTestDataSeeder extends Seeder
{
    /**
     * 시더 실행
     */
    public function run()
    {
        // 테스트 사용자 UUID
        $testUserUuid = 'test-user-001';

        // 1. 테스트 채팅방 생성
        $rooms = [
            [
                'title' => '일반 채팅',
                'description' => '자유로운 대화를 나누는 공간입니다.',
                'type' => 'public',
                'is_public' => true,
                'max_participants' => 100,
            ],
            [
                'title' => '개발자 모임',
                'description' => '개발 관련 정보를 공유하는 채팅방입니다.',
                'type' => 'public',
                'is_public' => true,
                'max_participants' => 50,
            ],
            [
                'title' => '프로젝트 논의',
                'description' => '프로젝트 관련 논의를 위한 채팅방입니다.',
                'type' => 'private',
                'is_public' => false,
                'max_participants' => 20,
            ],
        ];

        $createdRooms = [];
        foreach ($rooms as $roomData) {
            $room = ChatRoom::create([
                'uuid' => \Str::uuid(),
                'title' => $roomData['title'],
                'description' => $roomData['description'],
                'type' => $roomData['type'],
                'is_public' => $roomData['is_public'],
                'max_participants' => $roomData['max_participants'],
                'owner_uuid' => $testUserUuid,
                'status' => 'active',
                'invite_code' => \Str::random(8),
                'settings' => json_encode([
                    'allow_file_upload' => true,
                    'allow_voice_message' => true,
                    'auto_delete_messages' => false,
                ]),
                'last_activity_at' => Carbon::now(),
                'created_at' => Carbon::now()->subDays(rand(1, 30)),
                'updated_at' => Carbon::now(),
            ]);

            $createdRooms[] = $room;
        }

        // 2. 테스트 사용자를 모든 채팅방에 참여시키기
        foreach ($createdRooms as $room) {
            ChatParticipant::create([
                'room_id' => $room->id,
                'user_uuid' => $testUserUuid,
                'role' => $room->owner_uuid === $testUserUuid ? 'owner' : 'member',
                'status' => 'active',
                'joined_at' => $room->created_at->addMinutes(rand(1, 60)),
                'last_read_at' => Carbon::now()->subMinutes(rand(5, 120)),
                'unread_count' => rand(0, 15),
                'nickname' => 'Test User',
                'is_muted' => false,
                'is_pinned' => rand(0, 1) === 1,
            ]);

            // 다른 참가자들도 추가
            $otherUsers = [
                ['uuid' => 'user-002', 'name' => 'Alice'],
                ['uuid' => 'user-003', 'name' => 'Bob'],
                ['uuid' => 'user-004', 'name' => 'Charlie'],
                ['uuid' => 'user-005', 'name' => 'Diana'],
            ];

            foreach ($otherUsers as $index => $user) {
                if (rand(0, 1) === 1) { // 50% 확률로 참여
                    ChatParticipant::create([
                        'room_id' => $room->id,
                        'user_uuid' => $user['uuid'],
                        'role' => 'member',
                        'status' => rand(0, 10) > 2 ? 'active' : 'inactive', // 80% 활성
                        'joined_at' => $room->created_at->addHours(rand(1, 24)),
                        'last_read_at' => Carbon::now()->subMinutes(rand(10, 300)),
                        'unread_count' => rand(0, 10),
                        'nickname' => $user['name'],
                        'is_muted' => false,
                        'is_pinned' => false,
                    ]);
                }
            }
        }

        $this->command->info('채팅 테스트 데이터가 성공적으로 생성되었습니다!');
        $this->command->info("생성된 채팅방: " . count($createdRooms));
        $this->command->info("테스트 사용자 UUID: {$testUserUuid}");
    }
}
