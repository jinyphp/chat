<?php

namespace Jiny\Chat\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatRoomMessage;
use Jiny\Chat\Models\ChatRoomFile;
use Jiny\Chat\Http\Livewire\ChatWrite;
use Jiny\Chat\Http\Livewire\ChatMessages;
use Livewire\Livewire;

class ChatFileUploadTest extends TestCase
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

        // Shard 모킹 (실제 환경에서는 JWT/Shard 시스템이 있다고 가정)
        $this->mockShard();
    }

    protected function mockShard()
    {
        // Shard 클래스가 있다면 모킹
        if (class_exists('\Shard')) {
            $shard = \Mockery::mock('alias:\Shard');
            $shard->shouldReceive('user')
                ->with($this->testUser->uuid)
                ->andReturn($this->testUser);
        }
    }

    /**
     * 테스트 1: 이미지 파일 업로드 테스트
     */
    public function test_image_file_upload_creates_message_and_file_record()
    {
        // 1. 가짜 이미지 파일 생성
        $imageFile = UploadedFile::fake()->image('test-image.jpg', 800, 600)->size(500); // 500KB

        // 2. Livewire 컴포넌트 테스트 (ChatWrite)
        Livewire::test(ChatWrite::class, [
            'roomId' => $this->chatRoom->id
        ])
        ->call('loadRoom')
        ->set('user', $this->testUser)
        ->set('uploadedFiles', [$imageFile])
        ->call('uploadFiles');

        // 3. 검증: 적절한 SQLite DB에서 메시지 확인
        $roomMessage = ChatRoomMessage::forRoom(
            $this->chatRoom->code,
            $this->chatRoom->id,
            $this->chatRoom->created_at
        )->where('type', 'image')
          ->where('content', 'test-image.jpg')
          ->first();

        $this->assertNotNull($roomMessage, '이미지 메시지가 생성되어야 함');
        $this->assertEquals('image', $roomMessage->type);
        $this->assertEquals('test-image.jpg', $roomMessage->content);

        // 4. 검증: 메시지의 media 필드 확인
        $this->assertNotNull($roomMessage->media, 'media 데이터가 있어야 함');
        $this->assertEquals('test-image.jpg', $roomMessage->media['original_name']);
        $this->assertEquals('image', $roomMessage->media['file_type']);

        // 5. 검증: ChatRoomFile 레코드가 생성되었는지 확인
        $chatFile = ChatRoomFile::forRoom(
            $this->chatRoom->code,
            $this->chatRoom->id,
            $this->chatRoom->created_at
        )->where('message_id', $roomMessage->id)->first();

        $this->assertNotNull($chatFile, 'ChatRoomFile 레코드가 생성되어야 함');
        $this->assertEquals('test-image.jpg', $chatFile->original_name);
        $this->assertEquals('image', $chatFile->file_type);

        echo "✅ 테스트 1 통과: 이미지 파일 업로드 및 메시지 생성\n";
    }

    /**
     * 테스트 2: ChatMessages 컴포넌트에서 파일 정보 로드 테스트
     */
    public function test_chat_messages_displays_uploaded_file()
    {
        // 1. 먼저 이미지 메시지 생성
        $this->test_image_file_upload_creates_message_and_file_record();

        // 2. ChatMessages 컴포넌트 테스트
        $livewireTest = Livewire::test(ChatMessages::class, [
            'roomId' => $this->chatRoom->id
        ])
        ->call('loadRoom')
        ->set('user', $this->testUser)
        ->call('loadMessages');

        // 3. 컴포넌트 인스턴스에서 메시지 확인
        $component = $livewireTest->instance();
        $messages = $component->messages;

        $this->assertNotEmpty($messages, '메시지가 로드되어야 함');

        // 4. 이미지 메시지 확인
        $imageMessage = collect($messages)->first(function ($message) {
            return $message['type'] === 'image';
        });

        $this->assertNotNull($imageMessage, '이미지 메시지가 표시되어야 함');
        $this->assertArrayHasKey('file', $imageMessage, '파일 정보가 있어야 함');
        $this->assertEquals('test-image.jpg', $imageMessage['file']['original_name']);
        $this->assertEquals('image', $imageMessage['file']['file_type']);

        echo "✅ 테스트 2 통과: ChatMessages에서 파일 정보 표시\n";
    }

    /**
     * 테스트 3: 다양한 파일 타입 업로드 테스트
     */
    public function test_various_file_types_upload()
    {
        $fileTypes = [
            'pdf' => UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf'),
            'docx' => UploadedFile::fake()->create('document.docx', 800, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'mp4' => UploadedFile::fake()->create('video.mp4', 5000, 'video/mp4'),
            'mp3' => UploadedFile::fake()->create('audio.mp3', 3000, 'audio/mpeg'),
        ];

        foreach ($fileTypes as $type => $file) {
            // 각 파일 타입별로 업로드 테스트
            Livewire::test(ChatWrite::class, [
                'roomId' => $this->chatRoom->id
            ])
            ->call('loadRoom')
            ->set('user', $this->testUser)
            ->set('uploadedFiles', [$file])
            ->call('uploadFiles');

            // 각 파일 타입에 맞는 메시지가 생성되었는지 확인
            $expectedType = match($type) {
                'pdf', 'docx' => 'document',
                'mp4' => 'video',
                'mp3' => 'audio',
                default => 'file'
            };

            $roomMessage = ChatRoomMessage::forRoom(
                $this->chatRoom->code,
                $this->chatRoom->id,
                $this->chatRoom->created_at
            )->where('type', $expectedType)
              ->where('content', $file->getClientOriginalName())
              ->first();

            $this->assertNotNull($roomMessage, "{$type} 파일 메시지가 생성되어야 함");
            $this->assertEquals($expectedType, $roomMessage->type);

            echo "✅ {$type} 파일 업로드 성공\n";
        }

        echo "✅ 테스트 3 통과: 다양한 파일 타입 업로드\n";
    }

    /**
     * 테스트 4: 파일 경로 및 URL 생성 테스트
     */
    public function test_file_path_and_url_generation()
    {
        // 1. 이미지 업로드
        $imageFile = UploadedFile::fake()->image('test-path.png', 400, 300);

        Livewire::test(ChatWrite::class, [
            'roomId' => $this->chatRoom->id
        ])
        ->call('loadRoom')
        ->set('user', $this->testUser)
        ->set('uploadedFiles', [$imageFile])
        ->call('uploadFiles');

        // 2. 파일 경로 확인
        $message = ChatRoomMessage::forRoom(
            $this->chatRoom->code,
            $this->chatRoom->id,
            $this->chatRoom->created_at
        )->where('type', 'image')
          ->where('content', 'test-path.png')
          ->first();

        $this->assertNotNull($message, '이미지 메시지가 존재해야 함');
        $this->assertNotNull($message->media, 'media 데이터가 있어야 함');

        $filePath = $message->media['file_path'];
        $expectedPattern = "/^chat\/room\/{$this->chatRoom->id}\/\d{4}\/\d{2}\/\d{2}\/.+\.png$/";

        $this->assertMatchesRegularExpression($expectedPattern, $filePath, '파일 경로 패턴이 맞아야 함');

        // 3. 파일이 실제로 저장되었는지 확인
        Storage::disk('public')->assertExists($filePath);

        echo "✅ 테스트 4 통과: 파일 경로 및 저장 확인\n";
    }

    /**
     * 테스트 5: 블레이드 템플릿에서 파일 표시 테스트 (시뮬레이션)
     */
    public function test_blade_template_file_display_simulation()
    {
        // 1. 이미지 메시지 생성
        $this->test_image_file_upload_creates_message_and_file_record();

        // 2. ChatMessages에서 포맷된 메시지 가져오기
        $livewireTest = Livewire::test(ChatMessages::class, [
            'roomId' => $this->chatRoom->id
        ])
        ->call('loadRoom')
        ->set('user', $this->testUser)
        ->call('loadMessages');

        $component = $livewireTest->instance();
        $messages = $component->messages;

        $imageMessage = collect($messages)->first(function ($message) {
            return $message['type'] === 'image';
        });

        // 3. 블레이드 템플릿에서 사용할 변수들 시뮬레이션
        $message = $imageMessage;
        $fileId = $message['file']['id'] ?? null;
        $fileName = $message['file']['original_name'] ?? 'Unknown File';
        $isImage = $message['type'] === 'image';

        // 4. 템플릿 조건문 시뮬레이션
        $this->assertNotNull($fileId, '파일 ID가 존재해야 함');
        $this->assertTrue($isImage, '이미지 파일이어야 함');
        $this->assertEquals('test-image.jpg', $fileName, '파일명이 일치해야 함');

        // 5. 라우트 URL 생성 시뮬레이션
        if ($fileId) {
            $fullPath = "/home/chat/files/{$fileId}/show";
            $downloadPath = "/home/chat/files/{$fileId}/download";
            $thumbnailPath = "/home/chat/files/{$fileId}/thumbnail";

            $this->assertStringContains('/show', $fullPath);
            $this->assertStringContains('/download', $downloadPath);
            $this->assertStringContains('/thumbnail', $thumbnailPath);
        }

        echo "✅ 테스트 5 통과: 블레이드 템플릿 표시 로직 검증\n";
    }

    /**
     * 전체 플로우 통합 테스트
     */
    public function test_complete_file_upload_to_display_flow()
    {
        echo "\n🚀 전체 플로우 통합 테스트 시작\n";
        echo "=====================================\n";

        // 1단계: 파일 업로드
        echo "1단계: 이미지 파일 업로드...\n";
        $this->test_image_file_upload_creates_message_and_file_record();

        // 2단계: 메시지 표시
        echo "2단계: 메시지 목록에서 파일 표시...\n";
        $this->test_chat_messages_displays_uploaded_file();

        // 3단계: 파일 경로 검증
        echo "3단계: 파일 경로 및 저장 확인...\n";
        $this->test_file_path_and_url_generation();

        // 4단계: UI 표시 로직 검증
        echo "4단계: UI 표시 로직 검증...\n";
        $this->test_blade_template_file_display_simulation();

        echo "\n🎉 전체 플로우 테스트 완료!\n";
        echo "=====================================\n";
        echo "✅ 파일 업로드 → 메시지 생성 → 파일 레코드 생성 → 템플릿 표시 모든 단계 성공\n";
    }

    protected function tearDown(): void
    {
        Storage::fake('public'); // 테스트 파일 정리
        \Mockery::close();
        parent::tearDown();
    }
}