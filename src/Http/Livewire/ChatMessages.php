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
use Jiny\Chat\Services\TranslationService;
use Jiny\Chat\Models\ChatMessageTranslation;
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

    // 번역 관련
    public $translatedMessages = [];
    public $showTranslations = true;
    public $currentUserLanguage = 'ko';

    // 페이지네이션
    public $currentPage = 1;
    public $perPage = 50;
    public $hasMoreMessages = true;
    public $loadingMore = false;

    protected $chatService;
    protected $translationService;

    public function boot(ChatService $chatService, TranslationService $translationService)
    {
        $this->chatService = $chatService;
        $this->translationService = $translationService;
    }

    public function mount($roomId)
    {
        $this->roomId = $roomId;
        $this->loadRoom();
        $this->loadUser();
        $this->loadUserLanguage();
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

        // 기존 번역 로드
        $this->loadExistingTranslations();

        // 자동 번역 실행 (기존 번역이 없는 메시지만)
        if ($this->showTranslations) {
            $this->translateAllMessages();
        }

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

            // SSE를 통한 실시간 알림 (캐시 기반 간단한 방식)
            $this->broadcastNewMessage($formattedMessage);

            // 레거시 Livewire 이벤트 (호환성 유지)
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

    /**
     * SSE를 통해 새 메시지 수신 (JavaScript에서 호출)
     */
    public function handleSseMessage($messageData)
    {
        if (!$this->messagesLoaded) return;

        // 이미 존재하는 메시지인지 확인
        $existingMessage = collect($this->messages)->firstWhere('id', $messageData['id']);
        if ($existingMessage) {
            return;
        }

        // 자신의 메시지가 아닌 경우에만 추가
        if ($messageData['sender_uuid'] !== $this->user->uuid) {
            $this->messages[] = $messageData;

            // 새 메시지 자동 번역
            if ($this->showTranslations) {
                $this->translateMessage($messageData['id']);
            }

            $this->dispatch('scroll-to-bottom');
        }
    }

    /**
     * SSE 연결 상태 확인용 메서드
     */
    public function getSseEndpoint()
    {
        return route('chat.sse.stream', ['roomId' => $this->roomId]);
    }

    /**
     * 마지막 메시지 ID 반환 (SSE 연결 시 사용)
     */
    public function getLastMessageId()
    {
        return collect($this->messages)->max('id') ?? 0;
    }

    /**
     * SSE를 통한 새 메시지 브로드캐스팅
     */
    private function broadcastNewMessage($formattedMessage)
    {
        try {
            // 캐시를 사용한 간단한 브로드캐스팅
            // SSE 스트림에서 이 데이터를 확인하여 실시간 전송
            $broadcastKey = "chat_broadcast:{$this->roomId}";
            $broadcasts = \Cache::get($broadcastKey, []);

            $broadcasts[] = [
                'type' => 'new_message',
                'message' => $formattedMessage,
                'room_id' => $this->roomId,
                'timestamp' => now()->toISOString(),
                'sender_uuid' => $this->user->uuid
            ];

            // 최근 10개 브로드캐스트만 유지
            if (count($broadcasts) > 10) {
                $broadcasts = array_slice($broadcasts, -10);
            }

            \Cache::put($broadcastKey, $broadcasts, 60); // 1분 TTL

            \Log::info('SSE 브로드캐스트 전송', [
                'room_id' => $this->roomId,
                'message_id' => $formattedMessage['id'],
                'sender_uuid' => $this->user->uuid,
                'broadcast_count' => count($broadcasts)
            ]);

        } catch (\Exception $e) {
            \Log::error('SSE 브로드캐스트 실패', [
                'room_id' => $this->roomId,
                'error' => $e->getMessage(),
                'message_id' => $formattedMessage['id'] ?? 'unknown'
            ]);
        }
    }

    public function startTyping()
    {
        $this->isTyping = true;
        // SSE를 통해 타이핑 상태 전송
        $this->dispatch('send-typing-status', ['is_typing' => true]);
    }

    public function stopTyping()
    {
        $this->isTyping = false;
        // SSE를 통해 타이핑 상태 전송
        $this->dispatch('send-typing-status', ['is_typing' => false]);
    }

    /**
     * SSE를 통해 받은 타이핑 상태 업데이트
     */
    public function updateTypingUsers($typingUsers)
    {
        $this->typingUsers = $typingUsers;
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

    /**
     * 사용자 언어 설정 로드
     */
    public function loadUserLanguage()
    {
        if ($this->user) {
            // 참여자 정보에서 언어 설정 가져오기
            $participant = ChatParticipant::where('room_id', $this->roomId)
                ->where('user_uuid', $this->user->uuid)
                ->where('status', 'active')
                ->first();

            $this->currentUserLanguage = $participant->language ?? 'ko';
        }
    }

    /**
     * 기존 번역 로드
     */
    public function loadExistingTranslations()
    {
        try {
            if (empty($this->messages) || !$this->currentUserLanguage) {
                return;
            }

            // 현재 로드된 메시지 ID 수집
            $messageIds = collect($this->messages)
                ->where('sender_uuid', '!=', $this->user->uuid) // 자신의 메시지 제외
                ->pluck('id')
                ->toArray();

            if (empty($messageIds)) {
                return;
            }

            // 데이터베이스에서 기존 번역 조회
            $existingTranslations = ChatMessageTranslation::getTranslationsForMessages(
                $messageIds,
                $this->currentUserLanguage
            );

            // 번역 결과를 translatedMessages 배열에 저장
            foreach ($existingTranslations as $messageId => $translation) {
                $this->translatedMessages[$messageId] = $translation->toTranslationArray();
            }

            \Log::info('기존 번역 로드 완료', [
                'room_id' => $this->roomId,
                'target_language' => $this->currentUserLanguage,
                'total_messages' => count($messageIds),
                'existing_translations' => $existingTranslations->count()
            ]);

        } catch (\Exception $e) {
            \Log::error('기존 번역 로드 실패', [
                'room_id' => $this->roomId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 메시지 번역 (데이터베이스 기반)
     */
    public function translateMessage($messageId)
    {
        try {
            $message = collect($this->messages)->firstWhere('id', $messageId);

            if (!$message) {
                return;
            }

            // 이미 번역된 메시지인지 확인
            if (isset($this->translatedMessages[$messageId])) {
                return;
            }

            // 메시지 발신자의 언어 확인
            $sender = ChatParticipant::where('room_id', $this->roomId)
                ->where('user_uuid', $message['sender_uuid'])
                ->first();

            $senderLanguage = $sender->language ?? 'auto';

            // 번역이 필요한지 확인
            if (!$this->translationService->needsTranslation($senderLanguage, $this->currentUserLanguage)) {
                return;
            }

            // 데이터베이스 기반 번역 실행
            $translationResult = $this->translationService->translateChatMessage(
                $messageId,
                $message['content'],
                $this->currentUserLanguage,
                $senderLanguage
            );

            // 번역 결과 저장
            $this->translatedMessages[$messageId] = $translationResult;

            \Log::info('메시지 번역 완료', [
                'message_id' => $messageId,
                'original' => $message['content'],
                'translated' => $translationResult['translated'],
                'from' => $senderLanguage,
                'to' => $this->currentUserLanguage,
                'from_database' => isset($translationResult['translation_id'])
            ]);

        } catch (\Exception $e) {
            \Log::error('메시지 번역 실패', [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 모든 메시지 자동 번역 (배치 처리로 최적화)
     */
    public function translateAllMessages()
    {
        try {
            // 번역이 필요한 메시지 필터링 (자신의 메시지 제외)
            $messagesToTranslate = [];
            foreach ($this->messages as $message) {
                if ($message['sender_uuid'] === $this->user->uuid) {
                    continue;
                }

                // 이미 번역된 메시지 제외
                if (isset($this->translatedMessages[$message['id']])) {
                    continue;
                }

                // 발신자 언어 정보 추가
                $sender = ChatParticipant::where('room_id', $this->roomId)
                    ->where('user_uuid', $message['sender_uuid'])
                    ->first();

                $messagesToTranslate[] = [
                    'id' => $message['id'],
                    'content' => $message['content'],
                    'sender_language' => $sender->language ?? 'auto'
                ];
            }

            if (empty($messagesToTranslate)) {
                return;
            }

            // 배치 번역 실행
            $translationResults = $this->translationService->translateMultipleMessages(
                $messagesToTranslate,
                $this->currentUserLanguage
            );

            // 결과 저장
            $this->translatedMessages = array_merge($this->translatedMessages, $translationResults);

            \Log::info('배치 번역 완료', [
                'room_id' => $this->roomId,
                'target_language' => $this->currentUserLanguage,
                'total_messages' => count($messagesToTranslate),
                'successful_translations' => count($translationResults)
            ]);

        } catch (\Exception $e) {
            \Log::error('배치 번역 실패', [
                'room_id' => $this->roomId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 번역 표시 토글
     */
    public function toggleTranslations()
    {
        $this->showTranslations = !$this->showTranslations;

        if ($this->showTranslations) {
            $this->translateAllMessages();
        }
    }

    /**
     * 특정 메시지의 번역 토글
     */
    public function toggleMessageTranslation($messageId)
    {
        if (isset($this->translatedMessages[$messageId])) {
            // 번역 숨기기
            unset($this->translatedMessages[$messageId]);
        } else {
            // 번역 표시
            $this->translateMessage($messageId);
        }
    }

    /**
     * 번역 언어 변경
     */
    public function changeTranslationLanguage($language)
    {
        $this->currentUserLanguage = $language;
        $this->translatedMessages = []; // 기존 번역 초기화

        if ($this->showTranslations) {
            $this->translateAllMessages();
        }
    }

    /**
     * 번역된 메시지 가져오기
     */
    public function getTranslatedMessage($messageId)
    {
        return $this->translatedMessages[$messageId] ?? null;
    }

    /**
     * 메시지가 번역되었는지 확인
     */
    public function isMessageTranslated($messageId)
    {
        return isset($this->translatedMessages[$messageId]);
    }

    public function render()
    {
        return view('jiny-chat::livewire.chat-messages');
    }
}