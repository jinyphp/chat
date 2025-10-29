<?php

namespace Jiny\Chat\Http\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatMessage;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Models\ChatFile;
use Jiny\Chat\Models\ChatMessageFavourite;
use Jiny\Chat\Services\ChatService;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

class ChatMessages extends Component
{
    use WithFileUploads;
    public $roomId;
    public $room;
    public $messages = [];
    public $user;
    public $newMessage = '';
    public $messagesLoaded = false;
    public $isTyping = false;
    public $typingUsers = [];
    public $backgroundColor = '#f8f9fa';
    public $showBackgroundModal = false;

    // 파일 업로드 관련
    public $uploadedFiles = [];
    public $showFileUpload = false;

    // 답장 관련
    public $replyingTo = null;
    public $replyMessage = null;

    // 즐겨찾기 관련
    public $favouriteMessages = [];

    // 페이지네이션
    public $currentPage = 1;
    public $perPage = 50;
    public $hasMoreMessages = true;
    public $loadingMore = false;

    protected $chatService;

    public function boot(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    public function mount($roomId)
    {
        $this->roomId = $roomId;
        $this->loadRoom();
        $this->loadUser();
        $this->loadInitialMessages();
        $this->loadFavouriteMessages();
    }

    public function loadFavouriteMessages()
    {
        // 데이터베이스에서 사용자의 즐겨찾기 목록 로드
        if ($this->user) {
            $this->favouriteMessages = ChatMessageFavourite::getUserFavouriteMessageIds(
                $this->user->uuid,
                $this->roomId
            );
        } else {
            $this->favouriteMessages = [];
        }
    }

    public function loadRoom()
    {
        $this->room = ChatRoom::find($this->roomId);
        if ($this->room) {
            $this->backgroundColor = $this->room->background_color ?? '#f8f9fa';
        }
    }

    public function loadUser()
    {
        // JWT 인증 확인
        $this->user = \JwtAuth::user(request());
        if (!$this->user) {
            // 임시 테스트 사용자
            $this->user = (object) [
                'uuid' => 'test-user-001',
                'name' => 'Test User',
                'email' => 'test@example.com'
            ];
        }
    }

    public function loadInitialMessages()
    {
        $this->loadMessages();
        $this->messagesLoaded = true;
        $this->dispatch('scroll-to-bottom');
    }

    public function loadMessages()
    {
        if (!$this->room) return;

        $query = ChatMessage::where('room_id', $this->roomId)
            ->orderBy('created_at', 'desc')
            ->limit($this->perPage * $this->currentPage);

        $messages = $query->get()->reverse();

        $this->messages = $messages->map(function ($message) {
            return $this->formatMessage($message);
        })->toArray();

        // 더 로딩할 메시지가 있는지 확인
        $totalMessages = ChatMessage::where('room_id', $this->roomId)->count();
        $this->hasMoreMessages = count($this->messages) < $totalMessages;
    }

    public function loadMoreMessages()
    {
        if (!$this->hasMoreMessages || $this->loadingMore) {
            return;
        }

        $this->loadingMore = true;
        $this->currentPage++;

        // 이전 메시지들 로딩
        $query = ChatMessage::where('room_id', $this->roomId)
            ->orderBy('created_at', 'desc')
            ->skip(($this->currentPage - 1) * $this->perPage)
            ->limit($this->perPage);

        $olderMessages = $query->get()->reverse();

        if ($olderMessages->count() > 0) {
            $formattedOlderMessages = $olderMessages->map(function ($message) {
                return $this->formatMessage($message);
            })->toArray();

            // 기존 메시지 앞에 추가
            $this->messages = array_merge($formattedOlderMessages, $this->messages);
        }

        // 더 로딩할 메시지가 있는지 확인
        $totalMessages = ChatMessage::where('room_id', $this->roomId)->count();
        $this->hasMoreMessages = count($this->messages) < $totalMessages;

        $this->loadingMore = false;
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

            // 직접 데이터베이스에 메시지 저장
            $messageData = [
                'room_id' => $this->roomId,
                'room_uuid' => $room->uuid,
                'sender_uuid' => $this->user->uuid,
                'content' => trim($this->newMessage),
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // 답장 메시지인 경우 답장 정보 추가
            if ($this->replyingTo) {
                $messageData['reply_to_message_id'] = $this->replyingTo;
            }

            $message = ChatMessage::create($messageData);

            // 메시지를 포맷팅하여 화면에 추가
            $formattedMessage = $this->formatMessage($message);
            $this->messages[] = $formattedMessage;

            // 다른 컴포넌트에 메시지 전송 알림
            $this->dispatch('messageSent', [
                'message' => $formattedMessage,
                'room_id' => $this->roomId
            ]);

            $this->newMessage = '';

            // 답장 상태 초기화
            $this->replyingTo = null;
            $this->replyMessage = null;

            $this->dispatch('scroll-to-bottom');

            \Log::info('메시지 전송 성공', [
                'message_id' => $message->id,
                'user_uuid' => $this->user->uuid,
                'room_id' => $this->roomId,
                'content' => $message->content,
                'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                'formatted_time' => $this->formatMessage($message)['created_at']
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

    public function formatMessage($message)
    {
        // 발신자 정보 조회 (ChatParticipant 모델에서 참여자 정보 확인)
        $participant = ChatParticipant::where('room_id', $this->roomId)
            ->where('user_uuid', $message->sender_uuid)
            ->first();

        // 시간 포맷팅 - 오늘이 아닌 경우 날짜도 포함
        $createdAt = $message->created_at;
        $now = now();

        // 날짜만 비교하기 위해 Y-m-d 형식으로 비교
        $todayDate = $now->format('Y-m-d');
        $yesterdayDate = $now->copy()->subDay()->format('Y-m-d');
        $messageDate = $createdAt->format('Y-m-d');

        if ($messageDate === $todayDate) {
            // 오늘인 경우: 시간만 표시
            $timeDisplay = $createdAt->format('H:i');
        } elseif ($messageDate === $yesterdayDate) {
            // 어제인 경우: "어제 시간" 형식
            $timeDisplay = '어제 ' . $createdAt->format('H:i');
        } elseif ($createdAt->year === $now->year) {
            // 올해인 경우: "월.일 시간" 형식
            $timeDisplay = $createdAt->format('n.j H:i');
        } else {
            // 다른 해인 경우: "년.월.일 시간" 형식
            $timeDisplay = $createdAt->format('Y.n.j H:i');
        }

        $formattedMessage = [
            'id' => $message->id,
            'content' => $message->content,
            'type' => $message->type ?? 'text',
            'sender_uuid' => $message->sender_uuid,
            'sender_name' => $participant ? $participant->name : $message->sender_uuid,
            'sender_avatar' => $participant ? $participant->avatar : null,
            'created_at' => $timeDisplay,
            'created_at_full' => $message->created_at->format('Y-m-d H:i:s'),
            'is_mine' => $message->sender_uuid === $this->user->uuid,
            'reply_to_message_id' => $message->reply_to_message_id,
        ];

        // 답장 메시지인 경우 원본 메시지 정보 추가
        if ($message->reply_to_message_id) {
            $originalMessage = ChatMessage::find($message->reply_to_message_id);
            if ($originalMessage) {
                $originalParticipant = ChatParticipant::where('room_id', $this->roomId)
                    ->where('user_uuid', $originalMessage->sender_uuid)
                    ->first();

                $formattedMessage['reply_to'] = [
                    'id' => $originalMessage->id,
                    'content' => $originalMessage->content,
                    'sender_name' => $originalParticipant ? $originalParticipant->name : $originalMessage->sender_uuid,
                    'sender_uuid' => $originalMessage->sender_uuid,
                ];
            }
        }

        // 파일 메시지인 경우 파일 정보 추가
        if (in_array($message->type, ['image', 'document', 'video', 'audio'])) {
            $chatFile = ChatFile::where('message_id', $message->id)
                ->where('is_deleted', false)
                ->first();

            if ($chatFile) {
                $formattedMessage['file'] = [
                    'uuid' => $chatFile->uuid,
                    'original_name' => $chatFile->original_name,
                    'file_size' => $chatFile->file_size_human,
                    'file_type' => $chatFile->file_type,
                    'icon_class' => $chatFile->icon_class,
                    'download_url' => $chatFile->download_url,
                ];
            }
        }

        return $formattedMessage;
    }

    #[On('messageSent')]
    public function handleNewMessage($data)
    {
        if (isset($data['message'])) {
            $message = $data['message'];
            if ($message['sender_uuid'] !== $this->user->uuid) {
                $this->messages[] = $message;
                $this->dispatch('scroll-to-bottom');
            }
        }
    }

    #[On('backgroundColorChanged')]
    public function handleBackgroundColorChanged($data)
    {
        $this->backgroundColor = $data['color'];
    }

    #[On('participantListUpdated')]
    public function handleParticipantListUpdated($participants)
    {
        // 참여자 목록이 업데이트되었을 때의 처리
        // 필요에 따라 메시지 목록에 참여/나가기 알림 추가
    }

    public function pollForNewMessages()
    {
        if (!$this->messagesLoaded) return;

        // 마지막 메시지 이후의 새 메시지 체크
        $lastMessageId = collect($this->messages)->max('id') ?? 0;

        $newMessages = ChatMessage::where('room_id', $this->roomId)
            ->where('id', '>', $lastMessageId)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($newMessages->count() > 0) {
            foreach ($newMessages as $message) {
                if ($message->sender_uuid !== $this->user->uuid) {
                    $this->messages[] = $this->formatMessage($message);
                }
            }
            $this->dispatch('scroll-to-bottom');
        }
    }

    public function startTyping()
    {
        $this->isTyping = true;
        $this->dispatch('userTyping', [
            'user_uuid' => $this->user->uuid,
            'user_name' => $this->user->name,
            'room_id' => $this->roomId
        ]);
    }

    public function stopTyping()
    {
        $this->isTyping = false;
        $this->dispatch('userStoppedTyping', [
            'user_uuid' => $this->user->uuid,
            'room_id' => $this->roomId
        ]);
    }

    #[On('userTyping')]
    public function handleUserTyping($data)
    {
        if ($data['user_uuid'] !== $this->user->uuid) {
            $this->typingUsers[$data['user_uuid']] = $data['user_name'];
        }
    }

    #[On('userStoppedTyping')]
    public function handleUserStoppedTyping($data)
    {
        unset($this->typingUsers[$data['user_uuid']]);
    }

    public function showBackgroundSettings()
    {
        $this->showBackgroundModal = true;
    }

    public function closeBackgroundSettings()
    {
        $this->showBackgroundModal = false;
    }

    public function setBackgroundColor($color)
    {
        $this->backgroundColor = $color;
        $this->updateBackgroundColor();
    }

    public function updateBackgroundColor()
    {
        try {
            if ($this->room) {
                $this->room->update(['background_color' => $this->backgroundColor]);

                // 다른 컴포넌트에 배경색 변경 알림
                $this->dispatch('backgroundColorChanged', ['color' => $this->backgroundColor]);
            }

            $this->showBackgroundModal = false;
        } catch (\Exception $e) {
            \Log::error('배경색 변경 실패', [
                'error' => $e->getMessage(),
                'room_id' => $this->roomId,
                'background_color' => $this->backgroundColor
            ]);
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

        // 파일 업로드 후 스크롤 하단 이동
        $this->dispatch('scroll-to-bottom');
    }

    public function processFileUpload($file)
    {
        try {
            // Room UUID 가져오기
            $room = ChatRoom::find($this->roomId);
            if (!$room) {
                throw new \Exception('채팅방을 찾을 수 없습니다.');
            }

            // 파일 정보 추출
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

            // 메시지를 포맷팅하여 화면에 추가
            $formattedMessage = $this->formatMessage($message);
            $formattedMessage['file'] = [
                'uuid' => $chatFile->uuid,
                'original_name' => $originalName,
                'file_size' => $chatFile->file_size_human,
                'file_type' => $fileType,
                'icon_class' => $chatFile->icon_class,
                'download_url' => $chatFile->download_url,
            ];

            $this->messages[] = $formattedMessage;

            // 다른 컴포넌트에 메시지 전송 알림
            $this->dispatch('messageSent', [
                'message' => $formattedMessage,
                'room_id' => $this->roomId
            ]);

            $this->dispatch('scroll-to-bottom');

            \Log::info('파일 업로드 성공', [
                'file_id' => $chatFile->uuid,
                'message_id' => $message->id,
                'original_name' => $originalName,
                'file_size' => $fileSize,
                'file_type' => $fileType,
            ]);

        } catch (\Exception $e) {
            \Log::error('파일 업로드 실패', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file_name' => $file->getClientOriginalName() ?? 'unknown',
            ]);

            session()->flash('error', '파일 업로드에 실패했습니다: ' . $e->getMessage());
        }
    }

    public function deleteFile($fileUuid)
    {
        try {
            $chatFile = ChatFile::where('uuid', $fileUuid)
                ->where('uploader_uuid', $this->user->uuid)
                ->where('is_deleted', false)
                ->first();

            if (!$chatFile) {
                throw new \Exception('파일을 찾을 수 없거나 삭제 권한이 없습니다.');
            }

            // 실제 파일 삭제
            if (Storage::disk('public')->exists($chatFile->file_path)) {
                Storage::disk('public')->delete($chatFile->file_path);
            }

            // 데이터베이스에서 삭제 표시
            $chatFile->update([
                'is_deleted' => true,
                'deleted_at' => now(),
            ]);

            // 메시지 다시 로드
            $this->loadInitialMessages();

            \Log::info('파일 삭제 성공', [
                'file_id' => $fileUuid,
                'user_uuid' => $this->user->uuid,
            ]);

        } catch (\Exception $e) {
            \Log::error('파일 삭제 실패', [
                'error' => $e->getMessage(),
                'file_uuid' => $fileUuid,
                'user_uuid' => $this->user->uuid,
            ]);

            session()->flash('error', '파일 삭제에 실패했습니다: ' . $e->getMessage());
        }
    }

    public function toggleFileUpload()
    {
        $this->showFileUpload = !$this->showFileUpload;
    }

    // 메시지 상호작용 메서드들
    public function copyMessage($messageId)
    {
        // 클라이언트 사이드에서 처리되므로 서버 측에서는 로그만 남김
        \Log::info('메시지 복사', [
            'message_id' => $messageId,
            'user_uuid' => $this->user->uuid
        ]);
    }

    public function replyToMessage($messageId)
    {
        try {
            $message = ChatMessage::find($messageId);
            if ($message && $message->room_id == $this->roomId) {
                // 발신자 정보 조회
                $participant = ChatParticipant::where('room_id', $this->roomId)
                    ->where('user_uuid', $message->sender_uuid)
                    ->first();

                $senderName = $participant ? $participant->name : $message->sender_uuid;

                $this->replyingTo = $messageId;
                $this->replyMessage = [
                    'id' => $message->id,
                    'content' => $message->content,
                    'sender_name' => $senderName,
                    'created_at' => $message->created_at->format('H:i')
                ];

                // JavaScript 이벤트 발생
                $this->dispatch('replyToMessage', [
                    'messageId' => $messageId,
                    'content' => $message->content,
                    'senderName' => $senderName,
                    'timestamp' => $message->created_at->format('H:i')
                ]);

                session()->flash('info', $senderName . '님의 메시지에 답장 중...');
            }
        } catch (\Exception $e) {
            \Log::error('답장 설정 실패', ['error' => $e->getMessage()]);
        }
    }

    public function cancelReply()
    {
        $this->replyingTo = null;
        $this->replyMessage = null;
    }

    public function forwardMessage($messageId)
    {
        try {
            $message = ChatMessage::find($messageId);
            if ($message && $message->room_id == $this->roomId) {
                // 메시지 클립보드에 복사하여 다른 곳에 붙여넣기 할 수 있도록
                $forwardText = "[전달] " . $message->content . " - " . ($message->sender_name ?? 'Unknown');

                $this->dispatch('copyForwardMessage', ['text' => $forwardText]);
                session()->flash('success', '메시지가 전달용으로 복사되었습니다. 다른 채팅방에 붙여넣으세요.');
            }
        } catch (\Exception $e) {
            \Log::error('메시지 전달 실패', ['error' => $e->getMessage()]);
            session()->flash('error', '메시지 전달에 실패했습니다.');
        }
    }

    public function toggleFavourite($messageId)
    {
        try {
            if (!$this->user) {
                session()->flash('error', '로그인이 필요합니다.');
                return;
            }

            $wasAlreadyFavourite = in_array($messageId, $this->favouriteMessages);

            // 데이터베이스에서 즐겨찾기 토글
            $isFavourite = ChatMessageFavourite::toggleFavourite(
                $messageId,
                $this->user->uuid,
                $this->roomId,
                $this->room->uuid,
                \Jiny\Auth\Facades\Shard::getShardNumber($this->user->uuid)
            );

            // 로컬 배열 업데이트
            $this->loadFavouriteMessages();

            // 플래시 메시지
            if ($isFavourite) {
                session()->flash('success', '즐겨찾기에 추가되었습니다.');
            } else {
                session()->flash('info', '즐겨찾기에서 제거되었습니다.');
            }

            // JavaScript 이벤트 디스패치
            $this->dispatch('toggleFavourite', [
                'messageId' => $messageId,
                'isFavourite' => $isFavourite
            ]);

        } catch (\Exception $e) {
            \Log::error('즐겨찾기 실패', [
                'error' => $e->getMessage(),
                'message_id' => $messageId,
                'user_uuid' => $this->user->uuid ?? 'unknown',
                'room_id' => $this->roomId
            ]);
            session()->flash('error', '즐겨찾기 처리에 실패했습니다.');
        }
    }

    public function deleteMessage($messageId)
    {
        try {
            $message = ChatMessage::find($messageId);

            if (!$message || $message->room_id != $this->roomId) {
                session()->flash('error', '메시지를 찾을 수 없습니다.');
                return;
            }

            // 본인 메시지만 삭제 가능
            if ($message->sender_uuid !== $this->user->uuid) {
                session()->flash('error', '본인이 작성한 메시지만 삭제할 수 있습니다.');
                return;
            }

            // 소프트 삭제
            $message->update([
                'is_deleted' => true,
                'deleted_at' => now(),
                'content' => '삭제된 메시지입니다.'
            ]);

            // 메시지 목록 새로고침
            $this->loadInitialMessages();

            session()->flash('success', '메시지가 삭제되었습니다.');

        } catch (\Exception $e) {
            \Log::error('메시지 삭제 실패', [
                'error' => $e->getMessage(),
                'message_id' => $messageId,
                'user_uuid' => $this->user->uuid
            ]);
            session()->flash('error', '메시지 삭제에 실패했습니다.');
        }
    }

    public function render()
    {
        return view('jiny-chat::livewire.chat-messages');
    }
}