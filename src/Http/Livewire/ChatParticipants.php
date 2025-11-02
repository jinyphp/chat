<?php

namespace Jiny\Chat\Http\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Jiny\Chat\Models\ChatRoom;
use Jiny\Chat\Models\ChatParticipant;
use Jiny\Chat\Services\ChatService;
use Jiny\Chat\Models\ChatInviteToken;
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
    public $showLanguageModal = false;
    public $showEditModal = false;
    public $showRemoveModal = false;

    // 폼 데이터
    public $memberEmail = '';
    public $memberLanguage = 'ko';
    public $inviteLink = '';
    public $backgroundColor = '#f8f9fa';

    // 언어 관련
    public $availableLanguages = [];
    public $editingParticipant = null;
    public $removingParticipant = null;

    // 편집 폼 데이터
    public $editName = '';
    public $editLanguage = 'ko';
    public $editRole = 'member';

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
            // ui_settings에서 background_color 로드
            $uiSettings = $this->room->ui_settings ?? [];
            $this->backgroundColor = $uiSettings['background_color'] ?? '#f8f9fa';
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
                        \Log::info('ChatParticipants - JWT + Shard 사용자 로드 성공', [
                            'uuid' => $this->user->uuid,
                            'name' => $this->user->name,
                            'email' => $this->user->email,
                            'shard_table' => $this->user->getTable() ?? 'unknown'
                        ]);
                    } else {
                        \Log::warning('ChatParticipants - JWT 사용자를 샤딩 테이블에서 찾을 수 없음', [
                            'jwt_uuid' => $jwtUser->uuid,
                            'jwt_name' => $jwtUser->name ?? 'unknown',
                            'jwt_email' => $jwtUser->email ?? 'unknown'
                        ]);
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('ChatParticipants - JWT 인증 실패', [
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

                \Log::info('ChatParticipants - 세션 사용자 로드', [
                    'uuid' => $this->user->uuid,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'from_shard' => !($this->user === $sessionUser)
                ]);
            }
        }

        if (!$this->user) {
            \Log::error('ChatParticipants - 사용자를 찾을 수 없음', [
                'room_id' => $this->roomId,
                'jwt_available' => class_exists('\JwtAuth'),
                'session_user' => auth()->check()
            ]);
            throw new \Exception('인증된 사용자를 찾을 수 없습니다.');
        }

        // 현재 사용자의 참여자 정보 찾기
        $this->participant = $this->participants->firstWhere('user_uuid', $this->user->uuid);

        // 디버깅을 위한 로그
        \Log::info('ChatParticipants - 인증된 사용자 정보', [
            'uuid' => $this->user->uuid,
            'email' => $this->user->email,
            'name' => $this->user->name,
            'table' => method_exists($this->user, 'getTable') ? $this->user->getTable() : 'users'
        ]);

        if ($this->participant) {
            \Log::info('ChatParticipants - 참여자 정보 찾음', [
                'participant_id' => $this->participant->id,
                'role' => $this->participant->role,
                'user_uuid' => $this->participant->user_uuid
            ]);
        } else {
            \Log::warning('ChatParticipants - 현재 사용자의 참여자 정보 없음', [
                'user_uuid' => $this->user->uuid,
                'participants_count' => $this->participants->count()
            ]);
        }
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
        \Log::info('generateInviteLink 메서드 호출됨', [
            'room_id' => $this->roomId,
            'user_uuid' => $this->user->uuid ?? 'null'
        ]);

        // 단순화된 테스트: 바로 모달 표시
        $this->inviteLink = 'https://example.com/invite/test-token';
        $this->showInviteModal = true;

        session()->flash('success', '테스트 초대 링크가 생성되었습니다.');
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

    /**
     * 참여자 정보 수정 (방장/관리자용)
     */
    public function editParticipant($participantId)
    {
        $participant = $this->participants->firstWhere('id', $participantId);
        if ($participant) {
            $this->editingParticipant = $participant;
            $this->editName = $participant->name;
            $this->editLanguage = $participant->language ?? 'ko';
            $this->editRole = $participant->role;
            $this->showEditModal = true;
        }
    }

    /**
     * 자신의 프로필 수정
     */
    public function editOwnProfile()
    {
        $participant = $this->participants->firstWhere('user_uuid', $this->user->uuid);
        if ($participant) {
            $this->editingParticipant = $participant;
            $this->editName = $participant->name;
            $this->editLanguage = $participant->language ?? 'ko';
            $this->editRole = $participant->role;
            $this->showEditModal = true;
        }
    }

    /**
     * 참여자 정보 업데이트
     */
    public function updateParticipantInfo()
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editLanguage' => 'required|string|max:10',
            'editRole' => 'required|in:member,admin'
        ]);

        try {
            if ($this->editingParticipant) {
                $updateData = [
                    'name' => $this->editName,
                    'language' => $this->editLanguage,
                ];

                // 역할 변경은 방장/관리자만 가능하고, 자신이 아닌 경우에만
                if ($this->participant &&
                    in_array($this->participant->role, ['owner', 'admin']) &&
                    $this->editingParticipant->user_uuid !== $this->user->uuid) {
                    $updateData['role'] = $this->editRole;
                }

                $participantId = $this->editingParticipant->id;

                ChatParticipant::where('id', $participantId)
                    ->update($updateData);

                $this->loadParticipants();
                $this->showEditModal = false;
                $this->editingParticipant = null;

                // 참여자 목록 업데이트 이벤트 발송
                $this->dispatch('participantUpdated', [
                    'participant_id' => $participantId,
                    'room_id' => $this->roomId
                ]);

                session()->flash('success', '정보가 성공적으로 업데이트되었습니다.');
            }
        } catch (\Exception $e) {
            \Log::error('참여자 정보 업데이트 실패', [
                'error' => $e->getMessage(),
                'participant_id' => $this->editingParticipant->id ?? 'unknown'
            ]);
            session()->flash('error', '정보 업데이트 중 오류가 발생했습니다.');
        }
    }

    /**
     * 참여자 제거 확인 모달 표시
     */
    public function confirmRemoveParticipant($participantId)
    {
        $participant = $this->participants->firstWhere('id', $participantId);
        if ($participant && $participant->role !== 'owner') {
            $this->removingParticipant = $participant;
            $this->showRemoveModal = true;
        }
    }

    /**
     * 참여자 제거 실행
     */
    public function removeParticipant()
    {
        try {
            if ($this->removingParticipant && $this->removingParticipant->role !== 'owner') {
                $userUuid = $this->removingParticipant->user_uuid;
                $userName = $this->removingParticipant->name;
                $participantId = $this->removingParticipant->id;

                // 참여자 상태를 'removed'로 변경하고 left_at 설정
                ChatParticipant::where('id', $participantId)
                    ->update([
                        'status' => 'removed',
                        'left_at' => now(),
                        'removed_by_uuid' => $this->user->uuid,
                        'remove_reason' => 'kicked_by_admin'
                    ]);

                // 참여자 목록 새로고침
                $this->loadParticipants();

                // 제거 이벤트 발송
                $this->dispatch('participantRemoved', [
                    'user_uuid' => $userUuid,
                    'user_name' => $userName,
                    'room_id' => $this->roomId,
                    'removed_by' => $this->user->uuid
                ]);

                $this->showRemoveModal = false;
                $this->removingParticipant = null;

                session()->flash('success', '멤버가 채팅방에서 제거되었습니다.');
            }
        } catch (\Exception $e) {
            \Log::error('참여자 제거 실패', [
                'error' => $e->getMessage(),
                'participant_id' => $this->removingParticipant->id ?? 'unknown',
                'removed_by' => $this->user->uuid ?? 'unknown'
            ]);
            session()->flash('error', '멤버 제거 중 오류가 발생했습니다.');
        }
    }

    /**
     * 편집 모달 닫기
     */
    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingParticipant = null;
        $this->editName = '';
        $this->editLanguage = 'ko';
        $this->editRole = 'member';
    }

    /**
     * 제거 확인 모달 닫기
     */
    public function closeRemoveModal()
    {
        $this->showRemoveModal = false;
        $this->removingParticipant = null;
    }

    /**
     * Polling을 위한 참여자 새로고침 메서드
     */
    public function refreshParticipants()
    {
        try {
            // 기존 참여자 수
            $currentParticipantCount = count($this->participants);

            // 새로운 참여자 목록 로드
            $this->loadParticipants();

            // 온라인 참여자 목록 업데이트 (시뮬레이션)
            $this->updateOnlineParticipants();

            // 변경사항이 있는 경우 로그
            $newParticipantCount = count($this->participants);
            if ($currentParticipantCount !== $newParticipantCount) {
                \Log::info('Polling: 참여자 목록 변경됨', [
                    'room_id' => $this->roomId,
                    'previous_count' => $currentParticipantCount,
                    'current_count' => $newParticipantCount
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Polling 참여자 새로고침 실패', [
                'room_id' => $this->roomId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 온라인 참여자 목록 업데이트 (시뮬레이션)
     */
    private function updateOnlineParticipants()
    {
        // 실제 구현에서는 Redis나 세션을 통해 온라인 상태를 확인
        // 현재는 시뮬레이션으로 랜덤하게 일부 참여자를 온라인으로 표시
        $onlineCount = min(count($this->participants), rand(1, 3));
        $this->onlineParticipants = collect($this->participants)
            ->random(min($onlineCount, count($this->participants)))
            ->pluck('user_uuid')
            ->toArray();

        // 현재 사용자는 항상 온라인으로 표시
        if ($this->user && !in_array($this->user->uuid, $this->onlineParticipants)) {
            $this->onlineParticipants[] = $this->user->uuid;
        }
    }

    public function render()
    {
        return view('jiny-chat::livewire.chat-participants');
    }
}