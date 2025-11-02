<?php

namespace Jiny\Chat\Http\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Models\ChatRoomMessage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

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
        Log::info('ChatWrite - mount 시작', [
            'received_room_id' => $roomId,
            'room_id_type' => gettype($roomId)
        ]);

        $this->roomId = $roomId;

        Log::info('ChatWrite - roomId 할당 완료', [
            'this_room_id' => $this->roomId,
            'this_room_id_type' => gettype($this->roomId)
        ]);

        $this->loadRoom();
        $this->loadUser();
    }

    /**
     * 채팅방 정보 로드
     */
    public function loadRoom()
    {
        Log::info('ChatWrite - 방 정보 로딩 시작', [
            'requested_room_id' => $this->roomId,
            'room_id_type' => gettype($this->roomId)
        ]);

        $this->room = ChatRoom::find($this->roomId);

        if (!$this->room) {
            Log::error('ChatWrite - 채팅방을 찾을 수 없음', [
                'requested_room_id' => $this->roomId
            ]);
            throw new \Exception("채팅방을 찾을 수 없습니다. ID: {$this->roomId}");
        }

        // 방 정보 상세 검증
        if (!$this->room->id) {
            Log::error('ChatWrite - 방 ID가 없음', [
                'room_object' => $this->room->toArray()
            ]);
            throw new \Exception('로드된 채팅방에 ID가 없습니다.');
        }

        if (!$this->room->code) {
            Log::warning('ChatWrite - 방 코드가 없음', [
                'room_id' => $this->room->id
            ]);
            // code가 없으면 자동 생성
            $this->room->code = 'room_' . \Str::random(8);
            $this->room->save();
        }

        Log::info('ChatWrite - 방 정보 로드 완료', [
            'room_id' => $this->room->id,
            'room_code' => $this->room->code,
            'room_created_at' => $this->room->created_at,
            'room_title' => $this->room->title
        ]);
    }

    /**
     * JWT 인증된 샤딩 사용자 정보 로드
     */
    public function loadUser()
    {
        $this->user = null;

        try {
            // JWT 인증으로만 사용자 정보 확인
            if (!class_exists('\JwtAuth')) {
                throw new \Exception('JWT 인증 시스템이 사용할 수 없습니다.');
            }

            Log::info('ChatWrite - JWT 인증 시작', [
                'request_headers' => request()->headers->all()
            ]);

            $jwtUser = \JwtAuth::user(request());

            if (!$jwtUser) {
                throw new \Exception('JWT 토큰이 유효하지 않거나 만료되었습니다.');
            }

            if (!isset($jwtUser->uuid)) {
                throw new \Exception('JWT 사용자 정보에 UUID가 없습니다.');
            }

            Log::info('ChatWrite - JWT 사용자 정보 확인', [
                'jwt_uuid' => $jwtUser->uuid,
                'jwt_name' => $jwtUser->name ?? 'unknown',
                'jwt_email' => $jwtUser->email ?? 'unknown'
            ]);

            // 샤딩된 테이블에서 실제 사용자 조회
            $this->user = \Shard::getUserByUuid($jwtUser->uuid);

            if (!$this->user) {
                throw new \Exception("샤딩된 테이블에서 사용자를 찾을 수 없습니다. UUID: {$jwtUser->uuid}");
            }

            Log::info('ChatWrite - JWT 사용자 로드 성공', [
                'uuid' => $this->user->uuid,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'shard_table' => method_exists($this->user, 'getTable') ? $this->user->getTable() : 'unknown'
            ]);

        } catch (\Exception $e) {
            Log::error('ChatWrite - JWT 사용자 로드 실패', [
                'error' => $e->getMessage(),
                'room_id' => $this->roomId
            ]);
            throw new \Exception('JWT 인증된 사용자만 채팅을 사용할 수 있습니다: ' . $e->getMessage());
        }
    }

    /**
     * 메시지 전송
     */
    public function sendMessage()
    {
        if (empty(trim($this->newMessage))) {
            return;
        }

        try {
            // 메시지 데이터 준비
            $messageData = [
                'content' => trim($this->newMessage),
                'type' => 'text',
            ];

            // 답장 메시지인 경우
            if ($this->replyingTo) {
                $messageData['reply_to_message_id'] = $this->replyingTo;
            }

            // 방 개설 날짜 기반으로 메시지 저장
            $message = $this->saveMessageToRoomDatabase($messageData);

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

            // 입력 필드 초기화
            $this->newMessage = '';
            $this->replyingTo = null;
            $this->replyMessage = null;

            Log::info('ChatWrite - 메시지 전송 성공', [
                'message_id' => $message->id,
                'user_uuid' => $this->user->uuid,
                'room_id' => $this->roomId,
                'content' => $message->content
            ]);

        } catch (\Exception $e) {
            Log::error('ChatWrite - 메시지 전송 실패', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_uuid' => $this->user->uuid ?? 'unknown',
                'room_id' => $this->roomId
            ]);

            session()->flash('error', '메시지 전송에 실패했습니다: ' . $e->getMessage());
        }
    }

    /**
     * 방 개설 날짜 기반으로 메시지를 SQLite DB에 저장
     */
    private function saveMessageToRoomDatabase($messageData)
    {
        // 방 정보 검증
        if (!$this->room) {
            throw new \Exception('채팅방 정보가 없습니다.');
        }

        if (!$this->room->id) {
            throw new \Exception('채팅방 ID가 없습니다.');
        }

        if (!$this->room->code) {
            throw new \Exception('채팅방 코드가 없습니다.');
        }

        // 방 개설 날짜 확인
        $roomCreatedDate = $this->room->created_at;

        Log::info('ChatWrite - 메시지 저장 시작', [
            'room_id' => $this->room->id,
            'room_code' => $this->room->code,
            'room_created_date' => $roomCreatedDate,
            'user_uuid' => $this->user->uuid,
            'room_id_type' => gettype($this->room->id),
            'room_id_is_null' => is_null($this->room->id),
            'room_id_is_empty' => empty($this->room->id)
        ]);

        // roomId 최종 검증
        $roomId = $this->room->id;
        if (!$roomId) {
            throw new \Exception("Room ID is null or empty. Room: {$this->room->code}");
        }

        // ChatRoomMessage를 사용해서 방 개설 날짜 기반 SQLite DB에 저장
        return ChatRoomMessage::createMessage(
            $this->room->code,
            $this->user->uuid,
            $messageData,
            $roomId,
            $roomCreatedDate
        );
    }

    /**
     * 파일 업로드
     */
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

    /**
     * 파일 업로드 처리
     */
    public function processFileUpload($file)
    {
        try {
            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $fileSize = $file->getSize();
            $fileType = $this->determineFileType($mimeType);

            Log::info('ChatWrite - 파일 업로드 시작', [
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'determined_type' => $fileType,
                'room_id' => $this->roomId
            ]);

            // 파일 저장 경로: chat/room/방번호/채팅년도/월/일/
            $year = now()->format('Y');
            $month = now()->format('m');
            $day = now()->format('d');

            $storagePath = "chat/room/{$this->roomId}/{$year}/{$month}/{$day}";
            $fileName = time() . '_' . \Str::random(10) . '.' . $file->getClientOriginalExtension();
            $fullPath = "{$storagePath}/{$fileName}";

            Log::info('ChatWrite - 파일 저장 경로', [
                'storage_path' => $storagePath,
                'file_name' => $fileName,
                'full_path' => $fullPath
            ]);

            // 파일 저장
            $file->storeAs($storagePath, $fileName, 'public');

            // 파일 메시지 생성
            $messageData = [
                'content' => $originalName, // 파일명을 content로
                'type' => $fileType,
                'media' => [
                    'original_name' => $originalName,
                    'file_name' => $fileName,
                    'file_path' => $fullPath,
                    'file_type' => $fileType,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                ]
            ];

            Log::info('ChatWrite - 메시지 데이터 준비', [
                'message_data' => $messageData
            ]);

            // 메시지로 저장
            $message = $this->saveMessageToRoomDatabase($messageData);

            // 파일 업로드 완료 이벤트 디스패치
            $this->dispatch('fileUploaded', [
                'message_id' => $message->id,
                'room_id' => $this->roomId,
                'sender_uuid' => $this->user->uuid,
                'file_name' => $originalName,
                'file_type' => $fileType,
                'file_path' => $fullPath,
                'created_at' => $message->created_at->format('Y-m-d H:i:s')
            ]);

            Log::info('ChatWrite - 파일 업로드 성공', [
                'message_id' => $message->id,
                'original_name' => $originalName,
                'file_size' => $fileSize,
                'file_type' => $fileType,
                'storage_path' => $fullPath
            ]);

        } catch (\Exception $e) {
            Log::error('ChatWrite - 파일 업로드 실패', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file_name' => $file->getClientOriginalName() ?? 'unknown',
                'room_id' => $this->roomId
            ]);

            session()->flash('error', '파일 업로드에 실패했습니다: ' . $e->getMessage());
        }
    }

    /**
     * 파일 타입 결정
     */
    private function determineFileType($mimeType)
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        } elseif (in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain'
        ])) {
            return 'document';
        } else {
            return 'file';
        }
    }

    /**
     * 파일 업로드 토글
     */
    public function toggleFileUpload()
    {
        $this->showFileUpload = !$this->showFileUpload;
    }

    /**
     * 타이핑 시작
     */
    public function startTyping()
    {
        $this->isTyping = true;
        $this->dispatch('userTyping', [
            'user_uuid' => $this->user->uuid,
            'user_name' => $this->user->name,
            'room_id' => $this->roomId
        ]);
    }

    /**
     * 타이핑 중지
     */
    public function stopTyping()
    {
        $this->isTyping = false;
        $this->dispatch('userStoppedTyping', [
            'user_uuid' => $this->user->uuid,
            'user_name' => $this->user->name,
            'room_id' => $this->roomId
        ]);
    }

    /**
     * 답장하기
     */
    #[On('replyToMessage')]
    public function replyToMessage($data)
    {
        try {
            Log::info('ChatWrite - 답글 데이터 수신', [
                'data' => $data,
                'user_uuid' => $this->user->uuid ?? 'unknown'
            ]);

            // 데이터 추출
            $messageId = $data['message_id'] ?? null;
            $content = $data['content'] ?? '';
            $senderName = $data['sender_name'] ?? 'Unknown';
            $messageType = $data['type'] ?? 'text';

            if (!$messageId) {
                Log::error('ChatWrite - 답글 메시지 ID가 없음', ['data' => $data]);
                session()->flash('error', '답글할 메시지를 찾을 수 없습니다.');
                return;
            }

            $this->replyingTo = $messageId;
            $this->replyMessage = [
                'id' => $messageId,
                'content' => $content,
                'sender_name' => $senderName,
                'type' => $messageType
            ];

            Log::info('ChatWrite - 답글 설정 완료', [
                'replying_to' => $this->replyingTo,
                'reply_message' => $this->replyMessage,
                'user_uuid' => $this->user->uuid ?? 'unknown'
            ]);

        } catch (\Exception $e) {
            Log::error('ChatWrite - 답글 설정 실패', [
                'error' => $e->getMessage(),
                'data' => $data,
                'user_uuid' => $this->user->uuid ?? 'unknown'
            ]);
            session()->flash('error', '답글 설정 중 오류가 발생했습니다.');
        }
    }

    /**
     * 답장 취소
     */
    public function cancelReply()
    {
        $this->replyingTo = null;
        $this->replyMessage = null;
    }

    /**
     * 렌더링
     */
    public function render()
    {
        return view('jiny-chat::livewire.chat-write');
    }
}