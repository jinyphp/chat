<?php

namespace Jiny\Chat\Http\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatRoomMessage;
use Jiny\Chat\Models\ChatRoomFile;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Services\TranslationService;
use Jiny\Chat\Models\ChatMessageTranslation;
use Illuminate\Support\Facades\Log;

class ChatMessages extends Component
{
    public $roomId;
    public $room;
    public $user;
    public $messages = [];
    public $perPage = 20;
    public $currentPage = 1;
    public $hasMoreMessages = false;
    public $loadingMore = false;
    public $messagesLoaded = false;
    public $showTranslations = true;
    public $favouriteMessages = [];
    public $showFileUpload = false;
    public $replyingTo = null;
    public $replyMessage = [];
    public $showBackgroundModal = false;
    public $backgroundColor = '#ffffff';
    public $translatedMessages = [];

    public function mount($roomId)
    {
        $this->roomId = $roomId;
        $this->loadRoom();
        $this->loadUser();
        $this->loadMessages();
        $this->messagesLoaded = true;
    }

    /**
     * 번역 토글 이벤트 리스너 (ChatHeader에서 오는 이벤트)
     */
    #[On('chat-translations-toggled')]
    public function handleTranslationsToggled($data)
    {
        // 같은 룸의 이벤트만 처리
        if (($data['roomId'] ?? null) == $this->roomId) {
            $this->showTranslations = $data['showTranslations'] ?? true;

            Log::info('ChatMessages - 번역 토글 이벤트 수신', [
                'room_id' => $this->roomId,
                'show_translations' => $this->showTranslations
            ]);
        }
    }

    /**
     * 채팅방 정보 로드
     */
    public function loadRoom()
    {
        $this->room = ChatRoom::find($this->roomId);
        if (!$this->room) {
            throw new \Exception("채팅방을 찾을 수 없습니다. ID: {$this->roomId}");
        }
    }

    /**
     * 사용자 정보 로드
     */
    public function loadUser()
    {
        try {
            // JWT 인증 시도
            if (class_exists('\\JwtAuth')) {
                $jwtUser = \JwtAuth::user(request());
                if ($jwtUser && isset($jwtUser->uuid)) {
                    $this->user = \Shard::getUserByUuid($jwtUser->uuid);
                }
            }

            // 세션 인증 fallback
            if (!$this->user && auth()->check()) {
                $this->user = auth()->user();
            }

            if (!$this->user) {
                throw new \Exception('인증된 사용자를 찾을 수 없습니다.');
            }
        } catch (\Exception $e) {
            Log::error('ChatMessages - 사용자 로드 실패', [
                'error' => $e->getMessage(),
                'room_id' => $this->roomId
            ]);
            throw new \Exception('인증된 사용자만 채팅을 사용할 수 있습니다.');
        }
    }

    /**
     * 메시지 로드
     */
    public function loadMessages()
    {
        if (!$this->room || !$this->room->code) {
            $this->messages = [];
            return;
        }

        try {
            $query = ChatRoomMessage::forRoom($this->room->code, $this->room->id, $this->room->created_at)
                ->where('is_deleted', false)
                ->orderBy('created_at', 'desc')
                ->limit($this->perPage * $this->currentPage);

            $messages = $query->get()->reverse();

            $this->messages = $messages->map(function ($message) {
                return $this->formatMessage($message);
            })->toArray();

            // 더 로딩할 메시지가 있는지 확인
            $totalMessages = ChatRoomMessage::forRoom($this->room->code, $this->room->id, $this->room->created_at)
                ->where('is_deleted', false)->count();
            $this->hasMoreMessages = count($this->messages) < $totalMessages;

            // 번역 데이터 로드 (기존 번역이 있는 메시지들)
            $this->loadTranslationsForMessages();

        } catch (\Exception $e) {
            Log::error('ChatMessages - 메시지 로드 실패', [
                'error' => $e->getMessage(),
                'room_id' => $this->roomId
            ]);
            $this->messages = [];
        }
    }

    /**
     * 메시지 포맷팅
     */
    public function formatMessage($message)
    {
        // 시간 포맷팅
        $createdAt = $message->created_at;
        $now = now();
        $messageDate = $createdAt->format('Y-m-d');
        $todayDate = $now->format('Y-m-d');

        if ($messageDate === $todayDate) {
            $timeDisplay = $createdAt->format('H:i');
        } else {
            $timeDisplay = $createdAt->format('n.j H:i');
        }

        $isMine = $message->sender_uuid === $this->user->uuid;

        // 좋아요 카운트 계산
        $likes = json_decode($message->likes ?? '[]', true);
        $likesCount = is_array($likes) ? count($likes) : 0;

        $formattedMessage = [
            'id' => $message->id,
            'content' => $message->content,
            'type' => $message->type ?? 'text',
            'sender_uuid' => $message->sender_uuid,
            'sender_name' => $message->sender_name ?: $message->sender_uuid,
            'sender_avatar' => $message->sender_avatar ?? null,
            'created_at' => $timeDisplay,
            'is_mine' => $isMine,
            'likes_count' => $likesCount,
        ];

        // 파일 메시지 처리
        if (in_array($message->type, ['image', 'document', 'video', 'audio', 'file'])) {
            $this->addFileInfo($formattedMessage, $message);
        }

        // 답글 메시지 처리
        $this->addReplyInfo($formattedMessage, $message);

        return $formattedMessage;
    }

    /**
     * 파일 정보 추가
     */
    private function addFileInfo(&$formattedMessage, $message)
    {
        try {
            // ChatRoomFile에서 파일 정보 조회
            $chatFile = ChatRoomFile::forRoom($this->room->code, $this->room->id, $this->room->created_at)
                ->where('message_id', $message->id)
                ->first();

            if ($chatFile) {
                $formattedMessage['file'] = [
                    'id' => $chatFile->id,
                    'original_name' => $chatFile->original_name,
                    'file_type' => $chatFile->file_type,
                    'file_size' => $chatFile->file_size,
                    'mime_type' => $chatFile->mime_type,
                    'file_path' => $chatFile->file_path,
                ];

                Log::info('ChatMessages - 파일 정보 로드 성공', [
                    'message_id' => $message->id,
                    'file_id' => $chatFile->id,
                    'file_type' => $chatFile->file_type,
                    'original_name' => $chatFile->original_name
                ]);
            } else {
                Log::warning('ChatMessages - 파일 정보 없음', [
                    'message_id' => $message->id,
                    'message_type' => $message->type
                ]);
            }
        } catch (\Exception $e) {
            Log::error('ChatMessages - 파일 정보 로드 실패', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 답글 정보 추가
     */
    private function addReplyInfo(&$formattedMessage, $message)
    {
        try {
            // reply_to_message_id 필드 확인
            $replyToMessageId = $message->reply_to_message_id ?? null;

            if (!$replyToMessageId) {
                // 답글이 아닌 경우
                $formattedMessage['is_reply'] = false;
                $formattedMessage['reply_to'] = null;
                return;
            }

            // 답글인 경우 원본 메시지 조회
            $originalMessage = ChatRoomMessage::forRoom($this->room->code, $this->room->id, $this->room->created_at)
                ->where('id', $replyToMessageId)
                ->first();

            if ($originalMessage) {
                $formattedMessage['is_reply'] = true;
                $formattedMessage['reply_to'] = [
                    'id' => $originalMessage->id,
                    'content' => $originalMessage->content,
                    'type' => $originalMessage->type ?? 'text',
                    'sender_name' => $originalMessage->sender_name ?: $originalMessage->sender_uuid,
                    'sender_uuid' => $originalMessage->sender_uuid,
                    'created_at' => $originalMessage->created_at->format('Y-m-d H:i:s')
                ];

                Log::info('ChatMessages - 답글 정보 로드 성공', [
                    'message_id' => $message->id,
                    'reply_to_message_id' => $replyToMessageId,
                    'original_sender' => $originalMessage->sender_name
                ]);
            } else {
                // 원본 메시지를 찾을 수 없는 경우
                $formattedMessage['is_reply'] = true;
                $formattedMessage['reply_to'] = [
                    'id' => $replyToMessageId,
                    'content' => '원본 메시지를 찾을 수 없습니다.',
                    'type' => 'text',
                    'sender_name' => 'Unknown',
                    'sender_uuid' => null,
                    'created_at' => null
                ];

                Log::warning('ChatMessages - 원본 메시지 없음', [
                    'message_id' => $message->id,
                    'reply_to_message_id' => $replyToMessageId
                ]);
            }

        } catch (\Exception $e) {
            Log::error('ChatMessages - 답글 정보 로드 실패', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);

            // 에러 발생 시 기본값 설정
            $formattedMessage['is_reply'] = false;
            $formattedMessage['reply_to'] = null;
        }
    }

    /**
     * 더 많은 메시지 로드
     */
    public function loadMoreMessages()
    {
        if ($this->loadingMore || !$this->hasMoreMessages) {
            return;
        }

        $this->loadingMore = true;
        $this->currentPage++;

        $this->loadMessages();
        $this->loadingMore = false;
    }

    /**
     * 새 메시지 수신 이벤트
     */
    #[On('messageSent')]
    public function onMessageSent($data)
    {
        try {
            // 새 메시지 조회
            $message = ChatRoomMessage::forRoom($this->room->code, $this->room->id, $this->room->created_at)
                ->find($data['message_id']);

            if ($message) {
                $formattedMessage = $this->formatMessage($message);
                $this->messages[] = $formattedMessage;
                $this->dispatch('scroll-to-bottom');
            }
        } catch (\Exception $e) {
            Log::error('ChatMessages - 새 메시지 처리 실패', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
        }
    }

    /**
     * 파일 업로드 완료 이벤트
     */
    #[On('fileUploaded')]
    public function onFileUploaded($data)
    {
        $this->onMessageSent($data);
    }

    /**
     * 메시지 삭제
     */
    public function deleteMessage($messageId)
    {
        try {
            // 메시지 조회
            $message = ChatRoomMessage::forRoom($this->room->code, $this->room->id, $this->room->created_at)
                ->find($messageId);

            if (!$message) {
                session()->flash('error', '메시지를 찾을 수 없습니다.');
                return;
            }

            // 본인 메시지인지 확인
            if ($message->sender_uuid !== $this->user->uuid) {
                session()->flash('error', '자신의 메시지만 삭제할 수 있습니다.');
                return;
            }

            // 소프트 삭제
            $message->update(['is_deleted' => true]);

            // 메시지 목록에서 제거
            $this->messages = array_filter($this->messages, function($msg) use ($messageId) {
                return $msg['id'] != $messageId;
            });

            // 배열 인덱스 재정렬
            $this->messages = array_values($this->messages);

            session()->flash('success', '메시지가 삭제되었습니다.');

            Log::info('ChatMessages - 메시지 삭제 성공', [
                'message_id' => $messageId,
                'user_uuid' => $this->user->uuid
            ]);

        } catch (\Exception $e) {
            Log::error('ChatMessages - 메시지 삭제 실패', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
                'user_uuid' => $this->user->uuid
            ]);
            session()->flash('error', '메시지 삭제 중 오류가 발생했습니다.');
        }
    }

    /**
     * 메시지 좋아요
     */
    public function likeMessage($messageId)
    {
        try {
            // 메시지 조회
            $message = ChatRoomMessage::forRoom($this->room->code, $this->room->id, $this->room->created_at)
                ->find($messageId);

            if (!$message) {
                session()->flash('error', '메시지를 찾을 수 없습니다.');
                return;
            }

            // 본인 메시지는 좋아요 불가
            if ($message->sender_uuid === $this->user->uuid) {
                session()->flash('error', '자신의 메시지에는 좋아요를 할 수 없습니다.');
                return;
            }

            // 좋아요 정보 가져오기 (JSON 형태로 저장)
            $likes = json_decode($message->likes ?? '[]', true);

            if (in_array($this->user->uuid, $likes)) {
                // 좋아요 취소
                $likes = array_filter($likes, function($uuid) {
                    return $uuid !== $this->user->uuid;
                });
            } else {
                // 좋아요 추가
                $likes[] = $this->user->uuid;
            }

            // 좋아요 정보 업데이트
            $message->update(['likes' => json_encode(array_values($likes))]);

            // 메시지 목록에서 해당 메시지의 좋아요 카운트 업데이트
            foreach ($this->messages as &$msg) {
                if ($msg['id'] == $messageId) {
                    $msg['likes_count'] = count($likes);
                    break;
                }
            }

            Log::info('ChatMessages - 좋아요 토글 성공', [
                'message_id' => $messageId,
                'user_uuid' => $this->user->uuid,
                'likes_count' => count($likes)
            ]);

        } catch (\Exception $e) {
            Log::error('ChatMessages - 좋아요 처리 실패', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
                'user_uuid' => $this->user->uuid
            ]);
            session()->flash('error', '좋아요 처리 중 오류가 발생했습니다.');
        }
    }

    /**
     * 메시지 답글
     */
    public function replyToMessage($messageId)
    {
        try {
            // 메시지 조회
            $message = ChatRoomMessage::forRoom($this->room->code, $this->room->id, $this->room->created_at)
                ->find($messageId);

            if (!$message) {
                session()->flash('error', '메시지를 찾을 수 없습니다.');
                return;
            }

            // 답글 상태 설정
            $this->replyingTo = $messageId;
            $this->replyMessage = [
                'id' => $message->id,
                'sender_name' => $message->sender_name,
                'content' => $message->content,
                'type' => $message->type
            ];

            // 답글 이벤트 발송 (ChatWrite 컴포넌트에서도 처리 가능)
            $this->dispatch('replyToMessage', [
                'message_id' => $messageId,
                'sender_name' => $message->sender_name,
                'content' => $message->content,
                'type' => $message->type
            ]);

            Log::info('ChatMessages - 답글 요청', [
                'message_id' => $messageId,
                'user_uuid' => $this->user->uuid
            ]);

        } catch (\Exception $e) {
            Log::error('ChatMessages - 답글 처리 실패', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
                'user_uuid' => $this->user->uuid
            ]);
            session()->flash('error', '답글 처리 중 오류가 발생했습니다.');
        }
    }

    /**
     * 답글 취소
     */
    public function cancelReply()
    {
        $this->replyingTo = null;
        $this->replyMessage = [];

        Log::info('ChatMessages - 답글 취소', [
            'user_uuid' => $this->user->uuid ?? 'unknown'
        ]);
    }

    /**
     * 번역 표시 토글
     */
    public function toggleTranslations()
    {
        $this->showTranslations = !$this->showTranslations;

        Log::info('ChatMessages - 번역 토글', [
            'show_translations' => $this->showTranslations,
            'user_uuid' => $this->user->uuid ?? 'unknown'
        ]);
    }

    /**
     * 개별 메시지 번역 토글
     */
    public function toggleMessageTranslation($messageId)
    {
        try {
            // 이미 번역된 메시지인지 확인
            if (isset($this->translatedMessages[$messageId])) {
                // 번역 숨기기 (메모리에서 제거)
                unset($this->translatedMessages[$messageId]);

                Log::info('ChatMessages - 메시지 번역 숨기기', [
                    'message_id' => $messageId,
                    'user_uuid' => $this->user->uuid ?? 'unknown'
                ]);
            } else {
                // 번역 요청
                $this->translateMessage($messageId);
            }

        } catch (\Exception $e) {
            Log::error('ChatMessages - 메시지 번역 토글 실패', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
                'user_uuid' => $this->user->uuid ?? 'unknown'
            ]);
            session()->flash('error', '번역 처리 중 오류가 발생했습니다.');
        }
    }

    /**
     * 메시지 번역 실행
     */
    private function translateMessage($messageId)
    {
        try {
            // 메시지 조회
            $message = ChatRoomMessage::forRoom($this->room->code, $this->room->id, $this->room->created_at)
                ->find($messageId);

            if (!$message) {
                session()->flash('error', '번역할 메시지를 찾을 수 없습니다.');
                return;
            }

            // 텍스트 메시지만 번역 가능
            if ($message->type !== 'text' || empty($message->content)) {
                session()->flash('error', '텍스트 메시지만 번역할 수 있습니다.');
                return;
            }

            // 사용자 언어 설정 (현재는 한국어 고정, 향후 사용자 설정으로 변경)
            $targetLanguage = $this->getUserTargetLanguage();

            // TranslationService를 사용하여 번역
            $translationService = app(TranslationService::class);
            $translationResult = $translationService->translateChatMessage(
                $messageId,
                $message->content,
                $targetLanguage
            );

            if ($translationResult['success']) {
                $this->translatedMessages[$messageId] = $translationResult;

                Log::info('ChatMessages - 번역 완료', [
                    'message_id' => $messageId,
                    'source_language' => $translationResult['source_language'],
                    'target_language' => $translationResult['target_language'],
                    'user_uuid' => $this->user->uuid ?? 'unknown'
                ]);
            } else {
                session()->flash('error', '번역에 실패했습니다: ' . ($translationResult['error'] ?? '알 수 없는 오류'));
            }

        } catch (\Exception $e) {
            Log::error('ChatMessages - 메시지 번역 실패', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_uuid' => $this->user->uuid ?? 'unknown'
            ]);
            session()->flash('error', '번역 중 오류가 발생했습니다.');
        }
    }

    /**
     * 사용자 대상 언어 가져오기
     */
    private function getUserTargetLanguage()
    {
        // 현재는 한국어 고정, 향후 사용자 설정에서 가져오도록 확장
        return 'ko';

        // 향후 구현 예시:
        // return $this->user->language_preference ?? 'ko';
    }

    /**
     * 메시지 리스트에서 번역이 필요한 메시지들 자동 번역
     */
    private function loadTranslationsForMessages()
    {
        Log::info('ChatMessages - loadTranslationsForMessages 시작', [
            'messages_count' => count($this->messages),
            'user_uuid' => $this->user->uuid ?? 'unknown'
        ]);

        try {
            if (empty($this->messages)) {
                Log::warning('ChatMessages - 메시지가 비어있어 번역 건너뜀');
                return;
            }

            $targetLanguage = $this->getUserTargetLanguage();
            $messageIds = collect($this->messages)->pluck('id')->toArray();

            // 기존 번역 데이터 로드
            $existingTranslations = ChatMessageTranslation::getTranslationsForMessages($messageIds, $targetLanguage);

            // 기존 번역이 있는 메시지들은 번역 데이터 추가
            foreach ($existingTranslations as $messageId => $translation) {
                $this->translatedMessages[$messageId] = $translation->toTranslationArray();
            }

            // 번역이 없는 텍스트 메시지들에 대해 자동 번역 수행
            $translationService = app(TranslationService::class);
            $newTranslations = 0;

            foreach ($this->messages as $message) {
                // 이미 번역이 있는 메시지는 건너뛰기
                if (isset($this->translatedMessages[$message['id']])) {
                    Log::debug('ChatMessages - 이미 번역 존재, 건너뜀', ['message_id' => $message['id']]);
                    continue;
                }

                // 텍스트 메시지만 번역
                if ($message['type'] === 'text' && !empty($message['content'])) {
                    Log::info('ChatMessages - 새 번역 시작', [
                        'message_id' => $message['id'],
                        'content' => $message['content'],
                        'target_language' => $targetLanguage
                    ]);

                    try {
                        $translationResult = $translationService->translateChatMessage(
                            $message['id'],
                            $message['content'],
                            $targetLanguage
                        );

                        if ($translationResult['success']) {
                            $this->translatedMessages[$message['id']] = $translationResult;
                            $newTranslations++;

                            Log::info('ChatMessages - 번역 성공 및 저장', [
                                'message_id' => $message['id'],
                                'translated' => $translationResult['translated']
                            ]);
                        } else {
                            Log::warning('ChatMessages - 번역 실패', [
                                'message_id' => $message['id'],
                                'result' => $translationResult
                            ]);
                        }

                    } catch (\Exception $e) {
                        Log::error('ChatMessages - 메시지 번역 예외', [
                            'message_id' => $message['id'],
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                } else {
                    Log::debug('ChatMessages - 번역 대상 아님', [
                        'message_id' => $message['id'],
                        'type' => $message['type'],
                        'content_empty' => empty($message['content'])
                    ]);
                }
            }

            Log::info('ChatMessages - 번역 데이터 로드 및 생성 완료', [
                'existing_translations' => count($existingTranslations),
                'new_translations' => $newTranslations,
                'total_translations' => count($this->translatedMessages),
                'total_messages' => count($this->messages),
                'target_language' => $targetLanguage,
                'user_uuid' => $this->user->uuid ?? 'unknown',
                'translated_message_ids' => array_keys($this->translatedMessages),
                'sample_message_ids' => array_slice(array_column($this->messages, 'id'), 0, 3)
            ]);

        } catch (\Exception $e) {
            Log::error('ChatMessages - 번역 데이터 로드 실패', [
                'error' => $e->getMessage(),
                'user_uuid' => $this->user->uuid ?? 'unknown'
            ]);
        }
    }

    /**
     * 메시지 즐겨찾기 추가/제거
     */
    public function toggleFavourite($messageId)
    {
        try {
            if (in_array($messageId, $this->favouriteMessages)) {
                // 즐겨찾기에서 제거
                $this->favouriteMessages = array_filter($this->favouriteMessages, function($id) use ($messageId) {
                    return $id != $messageId;
                });
                $this->favouriteMessages = array_values($this->favouriteMessages);

                session()->flash('success', '즐겨찾기에서 제거되었습니다.');
            } else {
                // 즐겨찾기에 추가
                $this->favouriteMessages[] = $messageId;

                session()->flash('success', '즐겨찾기에 추가되었습니다.');
            }

            Log::info('ChatMessages - 즐겨찾기 토글', [
                'message_id' => $messageId,
                'is_favourite' => in_array($messageId, $this->favouriteMessages),
                'user_uuid' => $this->user->uuid ?? 'unknown'
            ]);

        } catch (\Exception $e) {
            Log::error('ChatMessages - 즐겨찾기 처리 실패', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
                'user_uuid' => $this->user->uuid ?? 'unknown'
            ]);
            session()->flash('error', '즐겨찾기 처리 중 오류가 발생했습니다.');
        }
    }

    /**
     * 즐겨찾기 메시지인지 확인
     */
    public function isFavourite($messageId)
    {
        return in_array($messageId, $this->favouriteMessages);
    }

    /**
     * 배경색 설정 모달 표시
     */
    public function showBackgroundSettings()
    {
        $this->showBackgroundModal = true;

        Log::info('ChatMessages - 배경색 설정 모달 열기', [
            'user_uuid' => $this->user->uuid ?? 'unknown'
        ]);
    }

    /**
     * 배경색 설정 모달 닫기
     */
    public function closeBackgroundSettings()
    {
        $this->showBackgroundModal = false;

        Log::info('ChatMessages - 배경색 설정 모달 닫기', [
            'user_uuid' => $this->user->uuid ?? 'unknown'
        ]);
    }

    /**
     * 배경색 업데이트
     */
    public function updateBackgroundColor()
    {
        try {
            // 배경색 유효성 검사 (간단한 hex 색상 코드 체크)
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $this->backgroundColor)) {
                session()->flash('error', '올바른 색상 코드를 입력하세요 (예: #ffffff).');
                return;
            }

            $this->closeBackgroundSettings();
            session()->flash('success', '배경색이 변경되었습니다.');

            Log::info('ChatMessages - 배경색 업데이트', [
                'background_color' => $this->backgroundColor,
                'user_uuid' => $this->user->uuid ?? 'unknown'
            ]);

        } catch (\Exception $e) {
            Log::error('ChatMessages - 배경색 업데이트 실패', [
                'error' => $e->getMessage(),
                'user_uuid' => $this->user->uuid ?? 'unknown'
            ]);
            session()->flash('error', '배경색 변경 중 오류가 발생했습니다.');
        }
    }

    /**
     * 미리 정의된 배경색 설정
     */
    public function setBackgroundColor($color)
    {
        $this->backgroundColor = $color;

        Log::info('ChatMessages - 미리 정의된 배경색 설정', [
            'background_color' => $color,
            'user_uuid' => $this->user->uuid ?? 'unknown'
        ]);
    }

    /**
     * 마지막 메시지 ID 반환 (SSE용)
     */
    public function getLastMessageId()
    {
        if (empty($this->messages)) {
            return 0;
        }

        // 마지막 메시지의 ID 반환
        $lastMessage = end($this->messages);
        return $lastMessage['id'] ?? 0;
    }

    /**
     * 메시지 새로고침 (폴링용)
     */
    public function refreshMessages()
    {
        try {
            // 현재 메시지 수 저장
            $currentMessageCount = count($this->messages);

            // 메시지 다시 로드
            $this->loadMessages();

            // 새 메시지가 있을 경우 로그
            $newMessageCount = count($this->messages);
            if ($newMessageCount > $currentMessageCount) {
                Log::info('ChatMessages - 새 메시지 감지됨', [
                    'room_id' => $this->roomId,
                    'previous_count' => $currentMessageCount,
                    'new_count' => $newMessageCount,
                    'user_uuid' => $this->user->uuid ?? 'unknown'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('ChatMessages - 메시지 새로고침 실패', [
                'error' => $e->getMessage(),
                'room_id' => $this->roomId,
                'user_uuid' => $this->user->uuid ?? 'unknown'
            ]);
        }
    }

    /**
     * 메시지 전달
     */
    public function forwardMessage($messageId)
    {
        try {
            // 메시지 조회
            $message = ChatRoomMessage::forRoom($this->room->code, $this->room->id, $this->room->created_at)
                ->find($messageId);

            if (!$message) {
                session()->flash('error', '전달할 메시지를 찾을 수 없습니다.');
                return;
            }

            // 전달 이벤트 발송 (ChatWrite 컴포넌트에서 처리)
            $this->dispatch('forwardMessage', [
                'message_id' => $messageId,
                'sender_name' => $message->sender_name,
                'content' => $message->content,
                'type' => $message->type,
                'created_at' => $message->created_at->toISOString()
            ]);

            session()->flash('success', '메시지 전달 창이 열렸습니다.');

            Log::info('ChatMessages - 메시지 전달 요청', [
                'message_id' => $messageId,
                'user_uuid' => $this->user->uuid ?? 'unknown',
                'original_sender' => $message->sender_name
            ]);

        } catch (\Exception $e) {
            Log::error('ChatMessages - 메시지 전달 실패', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
                'user_uuid' => $this->user->uuid ?? 'unknown'
            ]);
            session()->flash('error', '메시지 전달 중 오류가 발생했습니다.');
        }
    }

    public function render()
    {
        return view('jiny-chat::livewire.chat-messages');
    }
}