<?php

namespace Jiny\Chat\Http\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;

class ChatHeader extends Component
{
    public $roomId;
    public $room;
    public $messageCount = 0;
    public $pollingInterval = 3;
    public $showTranslations = true;
    public $backgroundColor = '#ffffff';
    public $showBackgroundModal = false;

    // 방 설정 데이터
    public $settingsTitle = '';
    public $settingsDescription = '';
    public $settingsType = 'public';
    public $settingsIsPublic = true;
    public $settingsAllowJoin = true;
    public $settingsAllowInvite = true;
    public $settingsPassword = '';
    public $settingsMaxParticipants = 0;

    // 모달 상태
    public $showSettingsModal = false;
    public $user;

    // 참여자 수
    public $participantsCount = 0;

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
        $this->loadUser();
    }

    public function loadRoom()
    {
        $this->room = ChatRoom::find($this->roomId);
        if ($this->room) {
            // ui_settings에서 background_color 로드
            $uiSettings = $this->room->ui_settings ?? [];
            $this->backgroundColor = $uiSettings['background_color'] ?? $this->room->background_color ?? '#ffffff';

            // 방 설정 데이터 로드
            $this->settingsTitle = $this->room->title ?? '';
            $this->settingsDescription = $this->room->description ?? '';
            $this->settingsType = $this->room->type ?? 'public';
            $this->settingsIsPublic = $this->room->is_public ?? true;
            $this->settingsAllowJoin = $this->room->allow_join ?? true;
            $this->settingsAllowInvite = $this->room->allow_invite ?? true;
            $this->settingsPassword = ''; // 보안상 비밀번호는 빈 값으로 표시
            $this->settingsMaxParticipants = $this->room->max_participants ?? 0;

            // 참여자 수 조회
            $this->participantsCount = $this->room->activeParticipants()->count();
        }
    }

    public function loadUser()
    {
        // JWT 인증된 사용자만 처리
        $this->user = null;

        try {
            // JWT 인증으로만 사용자 정보 확인
            if (!class_exists('\JwtAuth')) {
                throw new \Exception('JWT 인증 시스템이 사용할 수 없습니다.');
            }

            $jwtUser = \JwtAuth::user(request());

            if (!$jwtUser) {
                throw new \Exception('JWT 토큰이 유효하지 않거나 만료되었습니다.');
            }

            if (!isset($jwtUser->uuid)) {
                throw new \Exception('JWT 사용자 정보에 UUID가 없습니다.');
            }

            // 샤딩된 테이블에서 실제 사용자 조회
            $this->user = \Shard::getUserByUuid($jwtUser->uuid);

            if (!$this->user) {
                throw new \Exception("샤딩된 테이블에서 사용자를 찾을 수 없습니다. UUID: {$jwtUser->uuid}");
            }

        } catch (\Exception $e) {
            \Log::error('ChatHeader - JWT 사용자 로드 실패', [
                'error' => $e->getMessage(),
                'room_id' => $this->roomId
            ]);
            // 오류 시에도 빈 사용자로 계속 진행
            $this->user = null;
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




    /**
     * 방 설정 모달 표시
     */
    public function showSettings()
    {
        $this->showSettingsModal = true;
    }

    /**
     * 방 설정 업데이트
     */
    public function updateRoomSettings()
    {
        \Log::info('방 설정 업데이트 시작', [
            'room_id' => $this->roomId,
            'user_uuid' => $this->user->uuid ?? 'unknown',
            'settings' => [
                'title' => $this->settingsTitle,
                'description' => $this->settingsDescription,
                'type' => $this->settingsType,
                'is_public' => $this->settingsIsPublic,
                'allow_join' => $this->settingsAllowJoin,
                'allow_invite' => $this->settingsAllowInvite,
                'max_participants' => $this->settingsMaxParticipants,
                'background_color' => $this->backgroundColor,
            ]
        ]);

        $this->validate([
            'settingsTitle' => 'required|string|max:255',
            'settingsDescription' => 'nullable|string|max:1000',
            'settingsType' => 'required|in:public,private,group',
            'settingsIsPublic' => 'boolean',
            'settingsAllowJoin' => 'boolean',
            'settingsAllowInvite' => 'boolean',
            'settingsPassword' => 'nullable|string|min:4|max:255',
            'settingsMaxParticipants' => 'nullable|integer|min:0|max:1000',
            'backgroundColor' => 'required|regex:/^#[a-fA-F0-9]{6}$/'
        ]);

        try {
            // 기존 UI 설정 가져오기
            $uiSettings = $this->room->ui_settings ?? [];
            $uiSettings['background_color'] = $this->backgroundColor;

            $updateData = [
                'title' => $this->settingsTitle,
                'description' => $this->settingsDescription,
                'type' => $this->settingsType,
                'is_public' => $this->settingsIsPublic,
                'allow_join' => $this->settingsAllowJoin,
                'allow_invite' => $this->settingsAllowInvite,
                'max_participants' => $this->settingsMaxParticipants ?: 0,
                'ui_settings' => $uiSettings,
                'updated_at' => now(),
            ];

            // 비밀번호가 입력된 경우에만 업데이트
            if (!empty($this->settingsPassword)) {
                $updateData['password'] = bcrypt($this->settingsPassword);
            }

            \Log::info('방 설정 업데이트 데이터', [
                'room_id' => $this->roomId,
                'update_data' => $updateData
            ]);

            $updated = $this->room->update($updateData);

            \Log::info('방 설정 업데이트 결과', [
                'room_id' => $this->roomId,
                'updated' => $updated,
                'room_after_update' => [
                    'title' => $this->room->title,
                    'description' => $this->room->description,
                    'type' => $this->room->type,
                    'is_public' => $this->room->is_public,
                    'allow_join' => $this->room->allow_join,
                    'allow_invite' => $this->room->allow_invite,
                    'max_participants' => $this->room->max_participants,
                    'ui_settings' => $this->room->ui_settings,
                ]
            ]);

            $this->showSettingsModal = false;

            // 방 설정 변경 이벤트 발송
            $this->dispatch('roomSettingsUpdated', [
                'room_id' => $this->roomId,
                'title' => $this->settingsTitle,
                'background_color' => $this->backgroundColor
            ]);

            // 배경색 변경 이벤트 발송
            $this->dispatch('chat-background-color-changed', [
                'roomId' => $this->roomId,
                'color' => $this->backgroundColor
            ]);

            session()->flash('success', '방 설정이 성공적으로 변경되었습니다.');

            // 방 정보 다시 로드
            $this->loadRoom();

        } catch (\Exception $e) {
            \Log::error('방 설정 업데이트 실패', [
                'room_id' => $this->roomId,
                'error' => $e->getMessage(),
                'user_uuid' => $this->user->uuid ?? 'unknown'
            ]);

            session()->flash('error', '설정 저장 중 오류가 발생했습니다. 다시 시도해 주세요.');
        }
    }

    /**
     * 방 설정 모달 닫기
     */
    public function closeSettings()
    {
        $this->showSettingsModal = false;
    }

    /**
     * 채팅방 목록으로 이동
     */
    public function goToChatList()
    {
        return redirect()->route('home.chat.index');
    }

    /**
     * 방 나가기
     */
    public function leaveRoom()
    {
        try {
            if ($this->user) {
                \Jiny\Chat\Models\ChatParticipant::where('room_id', $this->roomId)
                    ->where('user_uuid', $this->user->uuid)
                    ->update(['left_at' => now()]);

                $this->dispatch('participantLeft', [
                    'user_uuid' => $this->user->uuid,
                    'room_id' => $this->roomId
                ]);

                return redirect()->route('home.chat.index');
            }
        } catch (\Exception $e) {
            session()->flash('error', '방 나가기 중 오류가 발생했습니다.');
        }
    }

    public function render()
    {
        return view('jiny-chat::livewire.chat-header');
    }
}