<?php

namespace Jiny\Chat\Http\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Jiny\Chat\Models\ChatRoom;

class ChatHeader extends Component
{
    public $roomId;
    public $room;
    public $messageCount = 0;
    public $pollingInterval = 3;
    public $showTranslations = true;
    public $backgroundColor = '#ffffff';
    public $showBackgroundModal = false;

    // 폴링 상태 관련
    public $isActive = true;
    public $lastActivity;
    public $hasNewMessages = false;

    public function mount($roomId, $messageCount = 0, $pollingInterval = 3, $showTranslations = true, $backgroundColor = '#ffffff')
    {
        $this->roomId = $roomId;
        $this->messageCount = $messageCount;
        $this->pollingInterval = $pollingInterval;
        $this->showTranslations = $showTranslations;
        $this->backgroundColor = $backgroundColor;
        $this->lastActivity = now();

        $this->loadRoom();
    }

    public function loadRoom()
    {
        $this->room = ChatRoom::find($this->roomId);
        if ($this->room) {
            $this->backgroundColor = $this->room->background_color ?? '#ffffff';
        }
    }

    /**
     * 번역 표시/숨기기 토글
     */
    public function toggleTranslations()
    {
        $this->showTranslations = !$this->showTranslations;

        // 전역 이벤트: ChatMessages 컴포넌트에 이벤트 전송
        $this->dispatch('chat-translations-toggled', [
            'roomId' => $this->roomId,
            'showTranslations' => $this->showTranslations
        ]);
    }

    /**
     * 폴링 간격 설정
     */
    public function setPollingInterval($seconds)
    {
        $this->pollingInterval = max(0.5, min(60, $seconds)); // 0.5-60초 범위

        // 전역 이벤트: ChatMessages 컴포넌트에 이벤트 전송
        $this->dispatch('chat-polling-interval-changed', [
            'roomId' => $this->roomId,
            'pollingInterval' => $this->pollingInterval
        ]);

        $this->updateActivity();
    }

    /**
     * 배경색 변경 모달 표시
     */
    public function showBackgroundSettings()
    {
        $this->showBackgroundModal = true;
    }

    /**
     * 배경색 설정
     */
    public function setBackgroundColor($color)
    {
        $this->backgroundColor = $color;
        $this->updateBackgroundColor();
    }

    /**
     * 배경색 업데이트
     */
    public function updateBackgroundColor()
    {
        try {
            if ($this->room) {
                $this->room->update(['background_color' => $this->backgroundColor]);

                // 전역 이벤트: 다른 컴포넌트에 배경색 변경 알림
                $this->dispatch('chat-background-color-changed', [
                    'roomId' => $this->roomId,
                    'color' => $this->backgroundColor
                ]);
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

    /**
     * 이전 메시지 불러오기
     */
    public function loadMoreMessages()
    {
        // 전역 이벤트: ChatMessages 컴포넌트에 이벤트 전송
        $this->dispatch('chat-load-more-messages-requested', [
            'roomId' => $this->roomId
        ]);
    }

    /**
     * 메시지 수 업데이트 이벤트 리스너 (형제 컴포넌트에서 오는 전역 이벤트)
     */
    #[On('chat-message-count-updated')]
    public function handleMessageCountUpdated($data)
    {
        // 같은 룸의 이벤트만 처리
        if (($data['roomId'] ?? null) == $this->roomId) {
            $this->messageCount = $data['count'] ?? 0;
        }
    }

    /**
     * 폴링 간격 업데이트 이벤트 리스너 (형제 컴포넌트에서 오는 전역 이벤트)
     */
    #[On('chat-polling-interval-updated')]
    public function handlePollingIntervalUpdated($data)
    {
        // 같은 룸의 이벤트만 처리
        if (($data['roomId'] ?? null) == $this->roomId) {
            $this->pollingInterval = $data['interval'] ?? 3;
        }
    }

    /**
     * 번역 상태 업데이트 이벤트 리스너 (형제 컴포넌트에서 오는 전역 이벤트)
     */
    #[On('chat-translations-state-updated')]
    public function handleTranslationsStateUpdated($data)
    {
        // 같은 룸의 이벤트만 처리
        if (($data['roomId'] ?? null) == $this->roomId) {
            $this->showTranslations = $data['showTranslations'] ?? true;
        }
    }

    /**
     * 배경색 변경 이벤트 리스너 (다른 컴포넌트에서 오는 이벤트)
     */
    #[On('chat-background-color-changed')]
    public function handleBackgroundColorChanged($data)
    {
        // 같은 룸의 이벤트만 처리하고, 자신이 발송한 이벤트는 제외
        if (($data['roomId'] ?? null) == $this->roomId) {
            $this->backgroundColor = $data['color'] ?? '#ffffff';
        }
    }

    /**
     * 새 메시지 이벤트 리스너
     */
    #[On('messageSent')]
    public function handleNewMessage($data)
    {
        $this->hasNewMessages = true;
        $this->onNewMessage();

        // 메시지 수 증가
        $this->messageCount++;
    }

    /**
     * 파일 업로드 이벤트 리스너
     */
    #[On('fileUploaded')]
    public function handleFileUpload($data)
    {
        $this->hasNewMessages = true;
        $this->onNewMessage();

        // 메시지 수 증가
        $this->messageCount++;
    }

    /**
     * 사용자 활동 감지 및 폴링 간격 조정
     */
    public function updateActivity()
    {
        $this->lastActivity = now();
        $this->isActive = true;
    }

    /**
     * 새 메시지 감지 시 폴링 간격 단축
     */
    public function onNewMessage()
    {
        $this->hasNewMessages = true;
        $this->pollingInterval = 0.5; // 즉시 빠른 폴링으로 변경
        $this->updateActivity();

        // 전역 이벤트: ChatMessages 컴포넌트에 폴링 간격 변경 알림
        $this->dispatch('chat-polling-interval-changed', [
            'roomId' => $this->roomId,
            'pollingInterval' => $this->pollingInterval
        ]);
    }

    /**
     * 배경색 모달 닫기
     */
    public function closeBackgroundModal()
    {
        $this->showBackgroundModal = false;
    }

    public function render()
    {
        return view('jiny-chat::livewire.chat-header');
    }
}