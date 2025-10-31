<?php

namespace Jiny\Chat\Http\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Models\ChatMessage;
use Jiny\Chat\Models\ChatFile;
use Jiny\Chat\Models\ChatRoomMessage;
use Jiny\Chat\Models\ChatRoomFile;
use Illuminate\Support\Facades\Storage;

class ChatWrite extends Component
{
    use WithFileUploads;

    public $roomId;
    public $room;
    public $user;
    public $newMessage = '';
    public $isTyping = false;

    // 파일 업로드 관련
    public $uploadedFiles = [];
    public $showFileUpload = false;

    // 답장 관련
    public $replyingTo = null;
    public $replyMessage = null;

    public function mount($roomId)
    {
        $this->roomId = $roomId;
        $this->loadRoom();
        $this->loadUser();
    }

    public function loadRoom()
    {
        $this->room = ChatRoom::find($this->roomId);
    }

    public function loadUser()
    {
        // 사용자 조회 (JWT + Shard 파사드 기반)
        $this->user = null;

        // 1. JWT 인증으로 사용자 정보 확인
        if (class_exists('\JwtAuth') && method_exists('\JwtAuth', 'user')) {
            try {
                $jwtUser = \JwtAuth::user(request());
                if ($jwtUser && isset($jwtUser->uuid)) {
                    // JWT 사용자 정보로 샤딩된 테이블에서 실제 사용자 조회
                    $this->user = \Jiny\Auth\Facades\Shard::getUserByUuid($jwtUser->uuid);

                    if ($this->user) {
                        \Log::info('ChatWrite - JWT + Shard 사용자 로드 성공', [
                            'uuid' => $this->user->uuid,
                            'name' => $this->user->name,
                            'email' => $this->user->email,
                            'shard_table' => $this->user->getTable() ?? 'unknown'
                        ]);
                        return;
                    } else {
                        \Log::warning('ChatWrite - JWT 사용자를 샤딩 테이블에서 찾을 수 없음', [
                            'jwt_uuid' => $jwtUser->uuid,
                            'jwt_name' => $jwtUser->name ?? 'unknown',
                            'jwt_email' => $jwtUser->email ?? 'unknown'
                        ]);
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('ChatWrite - JWT 인증 실패', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // 2. 세션 인증 사용자 확인 (일반 users 테이블)
        if (!$this->user) {
            $sessionUser = auth()->user();
            if ($sessionUser && isset($sessionUser->uuid)) {
                // 세션 사용자도 샤딩된 테이블에서 조회 시도
                $this->user = \Jiny\Auth\Facades\Shard::getUserByUuid($sessionUser->uuid);

                if (!$this->user) {
                    // 샤딩된 테이블에 없으면 세션 사용자 그대로 사용
                    $this->user = $sessionUser;
                }

                \Log::info('ChatWrite - 세션 사용자 로드', [
                    'uuid' => $this->user->uuid,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'from_shard' => !($this->user === $sessionUser)
                ]);
            }
        }

        // 3. 채팅방 참여자 정보에서 사용자 조회
        if (!$this->user && $this->roomId) {
            $participant = \Jiny\Chat\Models\ChatParticipant::where('room_id', $this->roomId)
                ->where('status', 'active')
                ->first();

            if ($participant) {
                // 참여자의 UUID로 샤딩된 테이블에서 실제 사용자 조회
                $this->user = \Jiny\Auth\Facades\Shard::getUserByUuid($participant->user_uuid);

                if (!$this->user) {
                    // 샤딩된 테이블에 없으면 참여자 정보로 임시 객체 생성
                    $this->user = (object) [
                        'uuid' => $participant->user_uuid,
                        'name' => $participant->name,
                        'email' => $participant->email ?? 'unknown@example.com'
                    ];
                }

                \Log::info('ChatWrite - 참여자 정보로 사용자 로드', [
                    'uuid' => $this->user->uuid,
                    'name' => $this->user->name,
                    'participant_id' => $participant->id,
                    'from_shard' => is_object($this->user) && method_exists($this->user, 'getTable')
                ]);
            }
        }

        // 4. 개발/테스트 환경 - 샤딩된 테이블에서 테스트 사용자 조회
        if (!$this->user && app()->environment(['local', 'development'])) {
            $testUuid = 'e04c8388-e7ed-438c-ba03-15fffd7a5312';
            $this->user = \Jiny\Auth\Facades\Shard::getUserByUuid($testUuid);

            if (!$this->user) {
                // 샤딩된 테이블에도 없으면 일반 users 테이블에서 조회
                $this->user = \App\Models\User::where('uuid', $testUuid)->first();
            }

            if ($this->user) {
                \Log::info('ChatWrite - 개발 환경 테스트 사용자 사용', [
                    'uuid' => $this->user->uuid,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'table' => method_exists($this->user, 'getTable') ? $this->user->getTable() : 'users'
                ]);
            }
        }

        if (!$this->user) {
            \Log::error('ChatWrite - 사용자를 찾을 수 없음', [
                'room_id' => $this->roomId,
                'jwt_available' => class_exists('\JwtAuth'),
                'session_user' => auth()->check(),
                'participant_count' => \Jiny\Chat\Models\ChatParticipant::where('room_id', $this->roomId)->count()
            ]);
            throw new \Exception('인증된 사용자를 찾을 수 없습니다.');
        }
    }

    public function sendMessage()
    {
        if (empty(trim($this->newMessage))) {
            return;
        }

        try {
            // Room UUID 가져오기
            $room = ChatRoom::find($this->roomId);
            if (!$room) {
                throw new \Exception('채팅방을 찾을 수 없습니다.');
            }

            $messageData = [
                'content' => trim($this->newMessage),
                'type' => 'text',
            ];

            // 답장 메시지인 경우 답장 정보 추가
            if ($this->replyingTo) {
                $messageData['reply_to_message_id'] = $this->replyingTo;
            }

            // 독립 데이터베이스 사용
            if ($room->code) {
                $message = $room->sendMessage($this->user->uuid, $messageData);
            } else {
                // 기존 방식 (하위 호환성)
                $messageData['room_id'] = $this->roomId;
                $messageData['room_uuid'] = $room->uuid;
                $messageData['sender_uuid'] = $this->user->uuid;
                $messageData['created_at'] = now();
                $messageData['updated_at'] = now();

                $message = ChatMessage::create($messageData);
            }

            // 형제 컴포넌트에 메시지 전송 이벤트 디스패치
            $this->dispatch('messageSent', [
                'message_id' => $message->id,
                'room_id' => $this->roomId,
                'sender_uuid' => $this->user->uuid,
                'content' => $message->content,
                'type' => $message->type,
                'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                'reply_to_message_id' => $this->replyingTo
            ]);

            $this->newMessage = '';

            // 답장 상태 초기화
            $this->replyingTo = null;
            $this->replyMessage = null;

            \Log::info('메시지 전송 성공', [
                'message_id' => $message->id,
                'user_uuid' => $this->user->uuid,
                'room_id' => $this->roomId,
                'room_code' => $room->code,
                'content' => $message->content,
                'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                'independent_db' => !empty($room->code)
            ]);

        } catch (\Exception $e) {
            \Log::error('메시지 전송 실패', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_uuid' => $this->user->uuid,
                'room_id' => $this->roomId,
                'message_content' => $this->newMessage
            ]);

            // 사용자에게 에러 알림
            session()->flash('error', '메시지 전송에 실패했습니다: ' . $e->getMessage());
        }
    }

    public function uploadFiles()
    {
        $this->validate([
            'uploadedFiles.*' => 'required|file|max:10240', // 10MB 제한
        ]);

        foreach ($this->uploadedFiles as $file) {
            $this->processFileUpload($file);
        }

        $this->uploadedFiles = [];
        $this->showFileUpload = false;
    }

    public function processFileUpload($file)
    {
        try {
            // Room UUID 가져오기
            $room = ChatRoom::find($this->roomId);
            if (!$room) {
                throw new \Exception('채팅방을 찾을 수 없습니다.');
            }

            // 독립 데이터베이스 사용
            if ($room->code) {
                $chatFile = $room->uploadFile($this->user->uuid, $file);
                $message = $chatFile->message;
            } else {
                // 기존 방식 (하위 호환성)
                $originalName = $file->getClientOriginalName();
                $mimeType = $file->getMimeType();
                $fileSize = $file->getSize();
                $fileType = ChatFile::determineFileType($mimeType);

                // 계층화된 저장 경로 생성
                $storagePath = ChatFile::generateStoragePath($room->uuid);
                $fileName = time() . '_' . \Str::random(10) . '.' . $file->getClientOriginalExtension();
                $fullPath = "chat_files/{$storagePath}/{$fileName}";

                // 파일 저장
                $file->storeAs(dirname($fullPath), basename($fullPath), 'public');

                // 메시지 생성 (파일 타입)
                $message = ChatMessage::create([
                    'room_id' => $this->roomId,
                    'room_uuid' => $room->uuid,
                    'sender_uuid' => $this->user->uuid,
                    'content' => $originalName, // 파일명을 content로
                    'type' => $fileType,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 파일 정보 저장
                $chatFile = ChatFile::create([
                    'message_id' => $message->id,
                    'room_uuid' => $room->uuid,
                    'uploader_uuid' => $this->user->uuid,
                    'original_name' => $originalName,
                    'file_name' => $fileName,
                    'file_path' => $fullPath,
                    'file_type' => $fileType,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'storage_path' => $storagePath,
                    'metadata' => [
                        'upload_ip' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ],
                ]);
            }

            // 형제 컴포넌트에 파일 업로드 완료 이벤트 디스패치
            $this->dispatch('fileUploaded', [
                'message_id' => $message->id,
                'file_id' => $chatFile->id,
                'room_id' => $this->roomId,
                'sender_uuid' => $this->user->uuid,
                'file_name' => $chatFile->original_name,
                'file_type' => $chatFile->file_type,
                'created_at' => $message->created_at->format('Y-m-d H:i:s')
            ]);

            \Log::info('파일 업로드 성공', [
                'file_id' => $chatFile->id,
                'message_id' => $message->id,
                'original_name' => $chatFile->original_name,
                'file_size' => $chatFile->file_size,
                'file_type' => $chatFile->file_type,
                'room_code' => $room->code,
                'independent_db' => !empty($room->code)
            ]);

        } catch (\Exception $e) {
            \Log::error('파일 업로드 실패', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file_name' => $file->getClientOriginalName() ?? 'unknown',
                'room_id' => $this->roomId
            ]);

            session()->flash('error', '파일 업로드에 실패했습니다: ' . $e->getMessage());
        }
    }

    public function toggleFileUpload()
    {
        $this->showFileUpload = !$this->showFileUpload;
    }

    public function startTyping()
    {
        $this->isTyping = true;
        // 형제 컴포넌트에 타이핑 상태 전송
        $this->dispatch('userTyping', [
            'user_uuid' => $this->user->uuid,
            'user_name' => $this->user->name,
            'room_id' => $this->roomId
        ]);
    }

    public function stopTyping()
    {
        $this->isTyping = false;
        // 형제 컴포넌트에 타이핑 상태 전송
        $this->dispatch('userStoppedTyping', [
            'user_uuid' => $this->user->uuid,
            'user_name' => $this->user->name,
            'room_id' => $this->roomId
        ]);
    }

    #[On('replyToMessage')]
    public function replyToMessage($messageId, $content, $senderName, $timestamp)
    {
        $this->replyingTo = $messageId;
        $this->replyMessage = [
            'id' => $messageId,
            'content' => $content,
            'sender_name' => $senderName,
            'created_at' => $timestamp
        ];
    }

    public function cancelReply()
    {
        $this->replyingTo = null;
        $this->replyMessage = null;
    }

    public function render()
    {
        return view('jiny-chat::livewire.chat-write');
    }
}