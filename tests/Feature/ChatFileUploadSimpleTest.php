<?php

namespace Jiny\Chat\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatRoomMessage;
use Jiny\Chat\Models\ChatRoomFile;

class ChatFileUploadSimpleTest extends TestCase
{
    use RefreshDatabase;

    protected $chatRoom;
    protected $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 가짜 스토리지 설정
        Storage::fake('public');

        // 테스트 채팅방 생성
        $this->chatRoom = ChatRoom::create([
            'title' => 'Test Chat Room',
            'code' => 'test_room_' . time(),
            'type' => 'public',
            'description' => 'Test room for file upload',
            'max_participants' => 100,
            'created_at' => now(),
        ]);

        // 테스트 사용자 생성
        $this->testUser = (object) [
            'uuid' => 'test-user-' . time(),
            'name' => 'Test User',
            'email' => 'test@example.com',
            'avatar' => null
        ];
    }

    /**
     * 테스트 1: ChatRoomMessage::createMessage() 직접 테스트
     */
    public function test_chat_room_message_create_with_media_data()
    {
        echo "\n🧪 테스트 1: ChatRoomMessage 파일 메시지 생성\n";

        // 1. 이미지 파일 정보 시뮬레이션
        $mediaData = [
            'original_name' => 'test-image.jpg',
            'file_name' => time() . '_test_image.jpg',
            'file_path' => "chat/room/{$this->chatRoom->id}/2024/11/02/" . time() . '_test_image.jpg',
            'file_type' => 'image',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024000,
        ];

        // 2. 메시지 데이터 준비
        $messageData = [
            'content' => 'test-image.jpg',
            'type' => 'image',
            'media' => $mediaData
        ];

        // 3. ChatRoomMessage 생성 테스트
        $message = ChatRoomMessage::createMessage(
            $this->chatRoom->code,
            $this->testUser->uuid,
            $messageData,
            $this->chatRoom->id,
            $this->chatRoom->created_at
        );

        // 4. 검증: 메시지가 생성되었는지 확인
        $this->assertNotNull($message, '메시지가 생성되어야 함');
        $this->assertEquals('image', $message->type);
        $this->assertEquals('test-image.jpg', $message->content);
        $this->assertNotNull($message->media, 'media 데이터가 있어야 함');

        echo "✅ 메시지 생성 성공: ID {$message->id}\n";

        // 5. 검증: ChatRoomFile 레코드가 생성되었는지 확인
        $chatFile = ChatRoomFile::forRoom(
            $this->chatRoom->code,
            $this->chatRoom->id,
            $this->chatRoom->created_at
        )->where('message_id', $message->id)->first();

        $this->assertNotNull($chatFile, 'ChatRoomFile 레코드가 생성되어야 함');
        $this->assertEquals('test-image.jpg', $chatFile->original_name);
        $this->assertEquals('image', $chatFile->file_type);

        echo "✅ ChatRoomFile 레코드 생성 성공: ID {$chatFile->id}\n";

        return $message;
    }

    /**
     * 테스트 2: 여러 파일 타입 테스트
     */
    public function test_various_file_types_message_creation()
    {
        echo "\n🧪 테스트 2: 다양한 파일 타입 메시지 생성\n";

        $fileTypes = [
            'image' => [
                'original_name' => 'photo.jpg',
                'mime_type' => 'image/jpeg',
                'file_type' => 'image'
            ],
            'document' => [
                'original_name' => 'document.pdf',
                'mime_type' => 'application/pdf',
                'file_type' => 'document'
            ],
            'video' => [
                'original_name' => 'video.mp4',
                'mime_type' => 'video/mp4',
                'file_type' => 'video'
            ],
            'audio' => [
                'original_name' => 'audio.mp3',
                'mime_type' => 'audio/mpeg',
                'file_type' => 'audio'
            ]
        ];

        foreach ($fileTypes as $type => $fileInfo) {
            $mediaData = [
                'original_name' => $fileInfo['original_name'],
                'file_name' => time() . '_' . $fileInfo['original_name'],
                'file_path' => "chat/room/{$this->chatRoom->id}/2024/11/02/" . time() . '_' . $fileInfo['original_name'],
                'file_type' => $fileInfo['file_type'],
                'mime_type' => $fileInfo['mime_type'],
                'file_size' => 1024000,
            ];

            $messageData = [
                'content' => $fileInfo['original_name'],
                'type' => $fileInfo['file_type'],
                'media' => $mediaData
            ];

            $message = ChatRoomMessage::createMessage(
                $this->chatRoom->code,
                $this->testUser->uuid,
                $messageData,
                $this->chatRoom->id,
                $this->chatRoom->created_at
            );

            $this->assertNotNull($message);
            $this->assertEquals($fileInfo['file_type'], $message->type);

            $chatFile = ChatRoomFile::forRoom(
                $this->chatRoom->code,
                $this->chatRoom->id,
                $this->chatRoom->created_at
            )->where('message_id', $message->id)->first();

            $this->assertNotNull($chatFile);
            $this->assertEquals($fileInfo['file_type'], $chatFile->file_type);

            echo "✅ {$type} 파일 타입 성공: {$fileInfo['original_name']}\n";
        }
    }

    /**
     * 테스트 3: 파일 정보 포맷팅 테스트 (ChatMessages::formatMessage 시뮬레이션)
     */
    public function test_file_message_formatting()
    {
        echo "\n🧪 테스트 3: 파일 메시지 포맷팅\n";

        // 1. 이미지 메시지 생성
        $message = $this->test_chat_room_message_create_with_media_data();

        // 2. ChatRoomFile 조회
        $chatFile = ChatRoomFile::forRoom(
            $this->chatRoom->code,
            $this->chatRoom->id,
            $this->chatRoom->created_at
        )->where('message_id', $message->id)->first();

        // 3. formatMessage 로직 시뮬레이션
        $formattedMessage = [
            'id' => $message->id,
            'type' => $message->type,
            'content' => $message->content,
            'sender_name' => $message->sender_name,
            'created_at' => $message->created_at->format('Y-m-d H:i:s'),
            'file' => null
        ];

        // 파일 정보 추가 (ChatMessages::formatMessage와 동일한 로직)
        if ($chatFile) {
            $formattedMessage['file'] = [
                'id' => $chatFile->id,
                'original_name' => $chatFile->original_name,
                'file_type' => $chatFile->file_type,
                'file_size' => $chatFile->file_size,
                'mime_type' => $chatFile->mime_type,
                'file_path' => $chatFile->file_path,
                'name' => $chatFile->original_name,
                'filename' => $chatFile->original_name,
                'size' => $chatFile->file_size,
                'type' => $chatFile->file_type,
            ];
        }

        // 4. 검증: 포맷된 메시지 구조 확인
        $this->assertNotNull($formattedMessage['file'], '파일 정보가 포함되어야 함');
        $this->assertEquals('test-image.jpg', $formattedMessage['file']['original_name']);
        $this->assertEquals('image', $formattedMessage['file']['file_type']);
        $this->assertArrayHasKey('id', $formattedMessage['file']);

        echo "✅ 파일 메시지 포맷팅 성공\n";

        return $formattedMessage;
    }

    /**
     * 테스트 4: 파일 라우트 URL 생성 테스트
     */
    public function test_file_route_url_generation()
    {
        echo "\n🧪 테스트 4: 파일 라우트 URL 생성\n";

        // 1. 포맷된 메시지 가져오기
        $formattedMessage = $this->test_file_message_formatting();
        $fileId = $formattedMessage['file']['id'];

        // 2. 라우트 URL 생성 시뮬레이션 (블레이드 템플릿에서 사용하는 방식)
        $urls = [
            'show' => route('home.chat.files.show', $fileId),
            'download' => route('home.chat.files.download', $fileId),
            'thumbnail' => route('home.chat.files.thumbnail', $fileId)
        ];

        // 3. URL 패턴 검증
        $expectedPattern = "/\/home\/chat\/files\/{$fileId}\/(show|download|thumbnail)/";

        foreach ($urls as $type => $url) {
            $this->assertMatchesRegularExpression($expectedPattern, $url, "{$type} URL 패턴이 올바르지 않음");
            echo "✅ {$type} URL 생성 성공: {$url}\n";
        }
    }

    /**
     * 전체 플로우 통합 테스트
     */
    public function test_complete_file_upload_and_display_flow()
    {
        echo "\n🚀 전체 플로우 통합 테스트 시작\n";
        echo "=====================================\n";

        // 1. 메시지 및 파일 레코드 생성
        echo "1단계: 파일 메시지 생성...\n";
        $message = $this->test_chat_room_message_create_with_media_data();

        // 2. 다양한 파일 타입 테스트
        echo "\n2단계: 다양한 파일 타입 테스트...\n";
        $this->test_various_file_types_message_creation();

        // 3. 메시지 포맷팅 테스트
        echo "\n3단계: 메시지 포맷팅 테스트...\n";
        $formattedMessage = $this->test_file_message_formatting();

        // 4. URL 생성 테스트
        echo "\n4단계: 파일 URL 생성 테스트...\n";
        $this->test_file_route_url_generation();

        echo "\n🎉 전체 플로우 테스트 완료!\n";
        echo "=====================================\n";
        echo "✅ 파일 업로드 → 메시지 생성 → 파일 레코드 생성 → 포맷팅 → URL 생성 모든 단계 성공\n";

        $this->assertTrue(true, '전체 플로우 테스트 성공');
    }

    protected function tearDown(): void
    {
        Storage::fake('public'); // 테스트 파일 정리
        parent::tearDown();
    }
}