<?php

namespace Jiny\Chat\Http\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Services\ChatService;
use Jiny\Site\Models\SiteLanguage;
use Jiny\Auth\Facades\Shard;

class ChatParticipants extends Component
{
    public $roomId;
    public $room;
    public $participants = [];
    public $participant;
    public $user;
    public $onlineParticipants = [];

    // 모달 상태
    public $showAddMemberModal = false;
    public $showInviteModal = false;
    public $showSettingsModal = false;
    public $showLanguageModal = false;

    // 폼 데이터
    public $memberEmail = '';
    public $memberLanguage = 'ko';
    public $inviteLink = '';
    public $backgroundColor = '#f8f9fa';

    // 언어 관련
    public $availableLanguages = [];
    public $editingParticipant = null;

    // 이메일 검증 관련
    public $emailValidation = null; // null, 'checking', 'valid', 'invalid', 'exists'
    public $validatedUser = null;

    protected $chatService;

    public function boot(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    public function mount($roomId)
    {
        $this->roomId = $roomId;
        $this->loadRoom();
        $this->loadParticipants();
        $this->loadUser();
        $this->loadAvailableLanguages();
    }

    public function loadRoom()
    {
        $this->room = ChatRoom::find($this->roomId);
        if ($this->room) {
            $this->backgroundColor = $this->room->background_color ?? '#f8f9fa';
        }
    }

    public function loadParticipants()
    {
        if ($this->room) {
            $this->participants = $this->room->activeParticipants()
                ->orderBy('role', 'desc')
                ->orderBy('joined_at', 'asc')
                ->get()
                ->map(function ($participant) {
                    // ChatParticipant 모델의 필드 직접 사용 (샤딩 시스템에서는 user 정보가 모델에 저장됨)
                    return (object) [
                        'id' => $participant->id,
                        'user_uuid' => $participant->user_uuid,
                        'name' => $participant->name ?? $participant->user_uuid,
                        'email' => $participant->email ?? '',
                        'avatar' => $participant->avatar ?? null,
                        'language' => $participant->language ?? 'ko',
                        'role' => $participant->role,
                        'joined_at' => $participant->joined_at,
                    ];
                });

            // 온라인 참여자 체크 (실제 구현에서는 Redis나 캐시 사용)
            $this->onlineParticipants = $this->participants->pluck('user_uuid')->toArray();
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

        // 현재 사용자의 참여자 정보 찾기
        $this->participant = $this->participants->firstWhere('user_uuid', $this->user->uuid);
    }

    public function loadAvailableLanguages()
    {
        $this->availableLanguages = SiteLanguage::where('enable', true)
            ->orderBy('order')
            ->get()
            ->map(function ($lang) {
                return [
                    'code' => $lang->lang,
                    'name' => $lang->name,
                    'native_name' => $lang->native_name,
                    'flag' => $lang->flag,
                ];
            });
    }

    public function getLanguageFlag($languageCode)
    {
        $flags = [
            'ko' => '🇰🇷',
            'en' => '🇺🇸',
            'ja' => '🇯🇵',
            'zh' => '🇨🇳',
            'es' => '🇪🇸',
        ];

        return $flags[$languageCode] ?? '🌐';
    }

    #[On('participantJoined')]
    public function handleParticipantJoined($data)
    {
        $this->loadParticipants();
        $this->dispatch('participantListUpdated', $this->participants);
    }

    #[On('participantLeft')]
    public function handleParticipantLeft($data)
    {
        $this->loadParticipants();
        $this->dispatch('participantListUpdated', $this->participants);
    }

    public function showAddMember()
    {
        $this->showAddMemberModal = true;
        $this->memberEmail = '';
        $this->memberLanguage = 'ko';
        $this->emailValidation = null;
        $this->validatedUser = null;
    }

    public function updatedMemberEmail()
    {
        if (empty($this->memberEmail)) {
            $this->emailValidation = null;
            $this->validatedUser = null;
            return;
        }

        // 이메일 형식 검증
        if (!filter_var($this->memberEmail, FILTER_VALIDATE_EMAIL)) {
            $this->emailValidation = 'invalid';
            $this->validatedUser = null;
            return;
        }

        $this->emailValidation = 'checking';
        $this->validateUserEmail();
    }

    protected function validateUserEmail()
    {
        try {
            // 사용자 존재 확인
            $user = Shard::getUserByEmail($this->memberEmail);

            if (!$user) {
                $this->emailValidation = 'invalid';
                $this->validatedUser = null;
                return;
            }

            // 이미 채팅방에 참여 중인지 확인
            $existingParticipant = ChatParticipant::where('room_id', $this->roomId)
                ->where('user_uuid', $user->uuid)
                ->where('status', 'active')
                ->first();

            if ($existingParticipant) {
                $this->emailValidation = 'exists';
                $this->validatedUser = $user;
                return;
            }

            $this->emailValidation = 'valid';
            $this->validatedUser = $user;

        } catch (\Exception $e) {
            $this->emailValidation = 'invalid';
            $this->validatedUser = null;
        }
    }

    public function addMember()
    {
        $this->validate([
            'memberEmail' => 'required|email',
            'memberLanguage' => 'required|string|max:10'
        ]);

        try {
            // 이메일로 사용자 존재 확인
            $user = Shard::getUserByEmail($this->memberEmail);

            if (!$user) {
                session()->flash('error', '해당 이메일로 등록된 회원을 찾을 수 없습니다.');
                return;
            }

            // 이미 채팅방에 참여 중인지 확인
            $existingParticipant = ChatParticipant::where('room_id', $this->roomId)
                ->where('user_uuid', $user->uuid)
                ->where('status', 'active')
                ->first();

            if ($existingParticipant) {
                session()->flash('error', '해당 회원은 이미 채팅방에 참여 중입니다.');
                return;
            }

            // 채팅방 참여자 추가
            ChatParticipant::create([
                'room_id' => $this->roomId,
                'room_uuid' => $this->room->uuid,
                'user_uuid' => $user->uuid,
                'shard_id' => Shard::getShardNumber($user->uuid),
                'email' => $user->email,
                'name' => $user->name,
                'avatar' => $user->avatar ?? null,
                'language' => $this->memberLanguage,
                'role' => 'member',
                'status' => 'active',
                'can_send_message' => true,
                'can_invite' => false,
                'can_moderate' => false,
                'notifications_enabled' => true,
                'joined_at' => now(),
                'invited_by_uuid' => $this->user->uuid,
                'join_reason' => 'invited',
            ]);

            // 참여자 목록 새로고침
            $this->loadParticipants();

            // 이벤트 발송
            $this->dispatch('memberAdded', [
                'user_uuid' => $user->uuid,
                'user_name' => $user->name,
                'language' => $this->memberLanguage,
                'room_id' => $this->roomId
            ]);

            $this->showAddMemberModal = false;
            $this->memberEmail = '';
            $this->memberLanguage = 'ko';

            session()->flash('success', "{$user->name}님이 채팅방에 추가되었습니다.");

        } catch (\Exception $e) {
            \Log::error('멤버 추가 실패', [
                'error' => $e->getMessage(),
                'email' => $this->memberEmail,
                'room_id' => $this->roomId,
                'invited_by' => $this->user->uuid ?? 'unknown'
            ]);

            session()->flash('error', '멤버 추가 중 오류가 발생했습니다. 다시 시도해 주세요.');
        }
    }

    public function generateInviteLink()
    {
        $token = \Str::random(32);
        $this->inviteLink = route('home.chat.join', ['token' => $token]);
        $this->showInviteModal = true;

        // 토큰을 데이터베이스에 저장하는 로직 추가 필요
    }

    public function showSettings()
    {
        $this->showSettingsModal = true;
    }

    public function updateBackgroundColor()
    {
        $this->validate([
            'backgroundColor' => 'required|regex:/^#[a-fA-F0-9]{6}$/'
        ]);

        try {
            $this->room->update(['background_color' => $this->backgroundColor]);
            $this->showSettingsModal = false;

            // 메시지 컴포넌트에 배경색 변경 알림
            $this->dispatch('backgroundColorChanged', [
                'color' => $this->backgroundColor
            ]);

            session()->flash('success', '배경색이 변경되었습니다.');

        } catch (\Exception $e) {
            session()->flash('error', '설정 저장 중 오류가 발생했습니다.');
        }
    }

    public function leaveRoom()
    {
        try {
            if ($this->participant) {
                ChatParticipant::where('room_id', $this->roomId)
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

    public function closeAddMember()
    {
        $this->showAddMemberModal = false;
        $this->memberEmail = '';
        $this->memberLanguage = 'ko';
        $this->emailValidation = null;
        $this->validatedUser = null;
    }

    public function closeInvite()
    {
        $this->showInviteModal = false;
        $this->inviteLink = '';
    }

    public function closeSettings()
    {
        $this->showSettingsModal = false;
    }

    public function showLanguageSettings($participantId)
    {
        $participant = $this->participants->firstWhere('id', $participantId);
        if ($participant) {
            $this->editingParticipant = $participant;
            $this->memberLanguage = $participant->language ?? 'ko';
            $this->showLanguageModal = true;
        }
    }

    public function updateParticipantLanguage()
    {
        $this->validate([
            'memberLanguage' => 'required|string|max:10'
        ]);

        try {
            if ($this->editingParticipant) {
                ChatParticipant::where('id', $this->editingParticipant->id)
                    ->update(['language' => $this->memberLanguage]);

                $this->loadParticipants();
                $this->showLanguageModal = false;
                $this->editingParticipant = null;

                session()->flash('success', '언어 설정이 변경되었습니다.');
            }
        } catch (\Exception $e) {
            session()->flash('error', '언어 설정 변경 중 오류가 발생했습니다.');
        }
    }

    public function closeLanguageSettings()
    {
        $this->showLanguageModal = false;
        $this->editingParticipant = null;
        $this->memberLanguage = 'ko';
    }

    public function render()
    {
        return view('jiny-chat::livewire.chat-participants');
    }
}