<?php

namespace Jiny\Chat\Http\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatMessage;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Models\ChatFile;
use Jiny\Chat\Models\ChatMessageFavourite;
use Jiny\Chat\Models\ChatRoomMessage;
use Jiny\Chat\Models\ChatRoomFile;
use Jiny\Chat\Models\ChatRoomMessageFavourite;
use Jiny\Chat\Models\ChatRoomMessageTranslation;
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
    public $messagesLoaded = false;
    public $typingUsers = [];
    public $backgroundColor = '#f8f9fa';

    // 즐겨찾기 관련
    public $favouriteMessages = [];

    // 번역 관련
    public $translatedMessages = [];
    public $showTranslations = true;
    public $currentUserLanguage = 'ko';

    // 폴링 간격 관련
    public $pollingInterval = 3; // 기본 3초
    public $isActive = true; // 사용자 활성 상태
    public $lastActivity; // 마지막 활동 시간
    public $lastMessageTime; // 마지막 메시지 시간
    public $hasNewMessages = false; // 새 메시지 존재 여부

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

    protected function ensureServicesLoaded()
    {
        if (!$this->chatService) {
            $this->chatService = app(ChatService::class);
        }
        if (!$this->translationService) {
            $this->translationService = app(TranslationService::class);
        }
    }

    public function mount($roomId)
    {
        $this->roomId = $roomId;
        $this->loadRoom();
        $this->loadUser();
        $this->loadUserLanguage();
        $this->loadInitialMessages();
        $this->loadFavouriteMessages();

        // 폴링 간격 초기화
        $this->lastActivity = now();
        $this->lastMessageTime = now();
        $this->pollingInterval = 3; // 기본 3초
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
                        \Log::info('ChatMessages - JWT + Shard 사용자 로드 성공', [
                            'uuid' => $this->user->uuid,
                            'name' => $this->user->name,
                            'email' => $this->user->email,
                            'shard_table' => $this->user->getTable() ?? 'unknown'
                        ]);
                        return;
                    } else {
                        \Log::warning('ChatMessages - JWT 사용자를 샤딩 테이블에서 찾을 수 없음', [
                            'jwt_uuid' => $jwtUser->uuid,
                            'jwt_name' => $jwtUser->name ?? 'unknown',
                            'jwt_email' => $jwtUser->email ?? 'unknown'
                        ]);
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('ChatMessages - JWT 인증 실패', [
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

                \Log::info('ChatMessages - 세션 사용자 로드', [
                    'uuid' => $this->user->uuid,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'from_shard' => !($this->user === $sessionUser)
                ]);
            }
        }

        // 3. 채팅방 참여자 정보에서 사용자 조회
        if (!$this->user && $this->roomId) {
            $participant = ChatParticipant::where('room_id', $this->roomId)
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

                \Log::info('ChatMessages - 참여자 정보로 사용자 로드', [
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
                \Log::info('ChatMessages - 개발 환경 테스트 사용자 사용', [
                    'uuid' => $this->user->uuid,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'table' => method_exists($this->user, 'getTable') ? $this->user->getTable() : 'users'
                ]);
            }
        }

        if (!$this->user) {
            \Log::error('ChatMessages - 사용자를 찾을 수 없음', [
                'room_id' => $this->roomId,
                'jwt_available' => class_exists('\JwtAuth'),
                'session_user' => auth()->check(),
                'participant_count' => ChatParticipant::where('room_id', $this->roomId)->count()
            ]);
            throw new \Exception('인증된 사용자를 찾을 수 없습니다.');
        }
    }

    public function loadInitialMessages()
    {
        $this->ensureServicesLoaded();
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

        // 독립 데이터베이스 사용
        if ($this->room->code) {
            $query = ChatRoomMessage::forRoom($this->room->code)
                ->where('is_deleted', false)
                ->orderBy('created_at', 'desc')
                ->limit($this->perPage * $this->currentPage);

            $messages = $query->get()->reverse();

            $this->messages = $messages->map(function ($message) {
                return $this->formatMessage($message);
            })->toArray();

            // 더 로딩할 메시지가 있는지 확인
            $totalMessages = ChatRoomMessage::forRoom($this->room->code)->where('is_deleted', false)->count();
            $this->hasMoreMessages = count($this->messages) < $totalMessages;
        } else {
            // 기존 방식 (하위 호환성)
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

        $isMine = $message->sender_uuid === $this->user->uuid;

        $formattedMessage = [
            'id' => $message->id,
            'content' => $message->content,
            'type' => $message->type ?? 'text',
            'sender_uuid' => $message->sender_uuid,
            'sender_name' => $participant ? $participant->name : $message->sender_uuid,
            'sender_avatar' => $participant ? $participant->avatar : null,
            'created_at' => $timeDisplay,
            'created_at_full' => $message->created_at->format('Y-m-d H:i:s'),
            'is_mine' => $isMine,
            'reply_to_message_id' => $message->reply_to_message_id,
        ];

        // 소유권 판별 로깅
        \Log::info('메시지 소유권 판별', [
            'message_id' => $message->id,
            'message_sender_uuid' => $message->sender_uuid,
            'current_user_uuid' => $this->user->uuid,
            'is_mine' => $isMine,
            'sender_name' => $formattedMessage['sender_name']
        ]);


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
            $chatFile = null;

            // 독립 데이터베이스 사용 여부 확인
            if ($this->room && $this->room->code) {
                $chatFile = ChatRoomFile::forRoom($this->room->code)
                    ->where('message_id', $message->id)
                    ->first();
            } else {
                // 기존 방식 (하위 호환성)
                $chatFile = ChatFile::where('message_id', $message->id)
                    ->where('is_deleted', false)
                    ->first();
            }

            if ($chatFile) {
                $formattedMessage['file'] = [
                    'id' => $chatFile->id,
                    'original_name' => $chatFile->original_name,
                    'file_size' => $this->formatFileSize($chatFile->file_size),
                    'file_type' => $chatFile->file_type,
                    'icon_class' => $this->getFileIconClass($chatFile->file_type),
                    'file_path' => $chatFile->file_path,
                    'thumbnail_path' => $chatFile->thumbnail_path ?? null,
                    'download_url' => asset('storage/' . $chatFile->file_path),
                ];

                // 이미지인 경우 미리보기 URL 추가
                if ($chatFile->file_type === 'image') {
                    $formattedMessage['file']['preview_url'] = asset('storage/' . $chatFile->file_path);
                    if ($chatFile->thumbnail_path) {
                        $formattedMessage['file']['thumbnail_url'] = asset('storage/' . $chatFile->thumbnail_path);
                    }
                }
            }
        }

        return $formattedMessage;
    }

    /**
     * 파일 크기를 읽기 쉬운 형태로 변환
     */
    private function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * 파일 타입에 따른 아이콘 클래스 반환
     */
    private function getFileIconClass($fileType)
    {
        switch ($fileType) {
            case 'image':
                return 'fas fa-image text-success';
            case 'video':
                return 'fas fa-video text-info';
            case 'audio':
                return 'fas fa-music text-warning';
            case 'document':
                return 'fas fa-file-alt text-primary';
            default:
                return 'fas fa-file text-muted';
        }
    }

    /**
     * 파일 삭제
     */
    public function deleteFile($fileId)
    {
        try {
            if ($this->room && $this->room->code) {
                $chatFile = ChatRoomFile::forRoom($this->room->code)->find($fileId);
            } else {
                $chatFile = ChatFile::find($fileId);
            }

            if ($chatFile && $chatFile->uploader_uuid === $this->user->uuid) {
                $chatFile->deleteFile();

                // 메시지 목록 새로고침
                $this->refreshMessages();

                session()->flash('success', '파일이 삭제되었습니다.');
            } else {
                session()->flash('error', '파일을 삭제할 권한이 없습니다.');
            }
        } catch (\Exception $e) {
            \Log::error('파일 삭제 실패', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            session()->flash('error', '파일 삭제에 실패했습니다.');
        }
    }

    #[On('messageSent')]
    public function handleNewMessage($data)
    {
        // ChatWrite 컴포넌트에서 전송된 메시지 이벤트 처리
        if (isset($data['message_id'])) {
            try {
                // 실제 메시지 조회 및 포맷팅
                if ($this->room && $this->room->code) {
                    $message = ChatRoomMessage::forRoom($this->room->code)
                        ->where('id', $data['message_id'])
                        ->first();
                } else {
                    $message = ChatMessage::find($data['message_id']);
                }

                if ($message) {
                    // 실시간 이벤트 처리 시 사용자 정보 강제 동기화
                    \Log::info('실시간 메시지 이벤트 처리', [
                        'message_id' => $message->id,
                        'message_sender_uuid' => $message->sender_uuid,
                        'current_user_uuid' => $this->user->uuid,
                        'event_sender_uuid' => $data['sender_uuid'] ?? null
                    ]);

                    $formattedMessage = $this->formatMessage($message);
                    $this->messages[] = $formattedMessage;
                    $this->dispatch('scroll-to-bottom');

                    // 새 메시지 감지 - 폴링 간격 단축
                    $this->onNewMessage();

                    // 전역 이벤트: ChatHeader 컴포넌트에 메시지 수 업데이트 알림
                    $this->dispatch('chat-message-count-updated', [
                        'roomId' => $this->roomId,
                        'count' => count($this->messages)
                    ])->to('jiny-chat::chat-header');
                }
            } catch (\Exception $e) {
                \Log::error('메시지 수신 처리 실패', [
                    'error' => $e->getMessage(),
                    'data' => $data
                ]);
            }
        }
    }

    #[On('fileUploaded')]
    public function handleFileUpload($data)
    {
        // ChatWrite 컴포넌트에서 전송된 파일 업로드 이벤트 처리
        if (isset($data['message_id'])) {
            try {
                // 실제 메시지 조회 및 포맷팅
                if ($this->room && $this->room->code) {
                    $message = ChatRoomMessage::forRoom($this->room->code)
                        ->where('id', $data['message_id'])
                        ->first();
                } else {
                    $message = ChatMessage::find($data['message_id']);
                }

                if ($message) {
                    $formattedMessage = $this->formatMessage($message);
                    $this->messages[] = $formattedMessage;
                    $this->dispatch('scroll-to-bottom');

                    // 새 메시지 감지 - 폴링 간격 단축
                    $this->onNewMessage();
                }
            } catch (\Exception $e) {
                \Log::error('파일 업로드 수신 처리 실패', [
                    'error' => $e->getMessage(),
                    'data' => $data
                ]);
            }
        }
    }

    #[On('chat-background-color-changed')]
    public function handleBackgroundColorChanged($data)
    {
        // 같은 룸의 이벤트만 처리
        if (($data['roomId'] ?? null) == $this->roomId) {
            $this->backgroundColor = $data['color'] ?? '#f8f9fa';
        }
    }

    #[On('participantListUpdated')]
    public function handleParticipantListUpdated($participants)
    {
        // 참여자 목록이 업데이트되었을 때의 처리
        // 필요에 따라 메시지 목록에 참여/나가기 알림 추가
    }

    #[On('chat-translations-toggled')]
    public function handleTranslationsToggled($data)
    {
        // 같은 룸의 이벤트만 처리
        if (($data['roomId'] ?? null) == $this->roomId) {
            $this->showTranslations = $data['showTranslations'] ?? true;

            if ($this->showTranslations) {
                $this->translateAllMessages();
            } else {
                // 번역 숨기기 - 번역 데이터 제거
                $this->translatedMessages = [];
            }
        }
    }

    #[On('chat-polling-interval-changed')]
    public function handlePollingIntervalChanged($data)
    {
        // 같은 룸의 이벤트만 처리
        if (($data['roomId'] ?? null) == $this->roomId) {
            $this->pollingInterval = $data['pollingInterval'] ?? 3;
            $this->updateActivity();
        }
    }


    #[On('chat-load-more-messages-requested')]
    public function handleLoadMoreMessagesRequested($data)
    {
        // 같은 룸의 이벤트만 처리
        if (($data['roomId'] ?? null) == $this->roomId) {
            $this->loadMoreMessages();
        }
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

                // ChatWrite 컴포넌트로 답장 정보 전송
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
            $this->ensureServicesLoaded();

            // 번역 서비스가 없으면 스킵
            if (!$this->translationService) {
                \Log::warning('번역 서비스를 사용할 수 없습니다');
                return;
            }

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

        // 전역 이벤트: ChatHeader 컴포넌트에 번역 상태 업데이트 알림
        $this->dispatch('chat-translations-state-updated', [
            'roomId' => $this->roomId,
            'showTranslations' => $this->showTranslations
        ]);
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

    /**
     * Polling을 위한 메시지 새로고침 메서드
     */
    public function refreshMessages()
    {
        if (!$this->messagesLoaded) {
            return;
        }

        try {
            $currentMessageCount = count($this->messages);
            $lastMessageId = collect($this->messages)->max('id') ?? 0;

            // 새 메시지 확인
            if ($this->room && $this->room->code) {
                $newMessages = ChatRoomMessage::forRoom($this->room->code)
                    ->where('id', '>', $lastMessageId)
                    ->where('is_deleted', false)
                    ->orderBy('created_at', 'asc')
                    ->get();

                if ($newMessages->count() > 0) {
                    foreach ($newMessages as $message) {
                        $formattedMessage = $this->formatMessage($message);
                        $this->messages[] = $formattedMessage;

                        // 새 메시지 자동 번역
                        if ($this->showTranslations && $message->sender_uuid !== $this->user->uuid) {
                            $this->translateMessage($message->id);
                        }
                    }

                    // 새 메시지 감지 - 폴링 간격 단축
                    $this->onNewMessage();

                    // 스크롤을 하단으로 이동
                    $this->dispatch('scroll-to-bottom');

                    \Log::info('Polling: 새 메시지 로드됨', [
                        'room_id' => $this->roomId,
                        'new_messages_count' => $newMessages->count(),
                        'total_messages' => count($this->messages),
                        'polling_interval' => $this->pollingInterval
                    ]);
                } else {
                    // 새 메시지가 없으면 폴링 간격 조정
                    $this->adjustPollingInterval();
                }
            } else {
                // 기존 방식 (하위 호환성)
                $newMessages = ChatMessage::where('room_id', $this->roomId)
                    ->where('id', '>', $lastMessageId)
                    ->orderBy('created_at', 'asc')
                    ->get();

                if ($newMessages->count() > 0) {
                    foreach ($newMessages as $message) {
                        $formattedMessage = $this->formatMessage($message);
                        $this->messages[] = $formattedMessage;
                    }

                    $this->dispatch('scroll-to-bottom');
                }
            }

        } catch (\Exception $e) {
            \Log::error('Polling 메시지 새로고침 실패', [
                'room_id' => $this->roomId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 사용자 활동 감지 및 폴링 간격 조정
     */
    public function updateActivity()
    {
        $this->lastActivity = now();
        $this->isActive = true;
        $this->adjustPollingInterval();
    }

    /**
     * 단계별 폴링 간격 조정 (메시지 활동에 따라)
     */
    public function adjustPollingInterval()
    {
        if (!$this->lastMessageTime) {
            $this->pollingInterval = 3;
            return;
        }

        $timeSinceLastMessage = now()->diffInSeconds($this->lastMessageTime);

        if ($this->hasNewMessages) {
            // 새 메시지가 감지되면 즉시 빠른 폴링
            $this->pollingInterval = 0.5; // 500ms
            \Log::info('폴링 간격 조정: 새 메시지 감지 - 0.5초');
        } elseif ($timeSinceLastMessage <= 1) {
            // 1초 이내: 빠른 폴링 (1초)
            $this->pollingInterval = 1;
            \Log::info('폴링 간격 조정: 1초 이내 - 1초');
        } elseif ($timeSinceLastMessage <= 3) {
            // 3초 이내: 보통 폴링 (3초)
            $this->pollingInterval = 3;
            \Log::info('폴링 간격 조정: 3초 이내 - 3초');
        } elseif ($timeSinceLastMessage <= 10) {
            // 10초 이내: 느린 폴링 (5초)
            $this->pollingInterval = 5;
        } elseif ($timeSinceLastMessage <= 30) {
            // 30초 이내: 더 느린 폴링 (10초)
            $this->pollingInterval = 10;
        } else {
            // 30초 이상: 매우 느린 폴링 (15초)
            $this->pollingInterval = 15;
        }

        // 새 메시지 플래그 리셋
        $this->hasNewMessages = false;
    }

    /**
     * 폴링 간격 설정 (수동)
     */
    public function setPollingInterval($seconds)
    {
        $this->pollingInterval = max(1, min(60, $seconds)); // 1-60초 범위
        $this->updateActivity();
    }

    /**
     * 새 메시지 감지 시 폴링 간격 단축
     */
    public function onNewMessage()
    {
        $this->hasNewMessages = true;
        $this->lastMessageTime = now();
        $this->adjustPollingInterval(); // 0.5초로 단축
        $this->updateActivity();

        // 전역 이벤트: ChatHeader 컴포넌트에 폴링 간격 업데이트 알림
        $this->dispatch('chat-polling-interval-updated', [
            'roomId' => $this->roomId,
            'interval' => $this->pollingInterval
        ]);
    }

    public function render()
    {
        return view('jiny-chat::livewire.chat-messages');
    }
}