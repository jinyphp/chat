<!-- 채팅방 참여자 목록 컴포넌트 -->
<div class="card h-100 border-0 d-flex flex-column">
    <!-- Card Header -->
    <div class="card-header bg-white border-bottom px-3 py-3 flex-shrink-0">
        <div class="d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-bold">
                <i class="fas fa-users text-primary me-2"></i>
                참여자 ({{ count($participants) }})
            </h6>

            @if($participant && in_array($participant->role, ['owner', 'admin']))
                <div class="dropdown">
                    <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-plus"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" wire:click="showAddMember">
                            <i class="fas fa-user-plus me-2"></i> 멤버 추가
                        </a></li>
                        <li><a class="dropdown-item" href="#" wire:click="generateInviteLink">
                            <i class="fas fa-link me-2"></i> 초대 링크
                        </a></li>
                    </ul>
                </div>
            @endif
        </div>
    </div>

    <!-- Card Body - 참여자 목록 -->
    <div class="card-body p-0 overflow-auto flex-grow-1">
        <!-- 알림 메시지 -->
        @if(session()->has('success'))
            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session()->has('error'))
            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @foreach ($participants as $p)
            <div class="p-3 border-bottom {{ $p->user_uuid === $user->uuid ? 'bg-light' : '' }}">
                <div class="d-flex align-items-center">
                    <!-- 아바타 -->
                    <div class="position-relative me-3">
                        @if ($p->avatar)
                            <img src="{{ $p->avatar }}" alt="{{ $p->name }}"
                                 class="rounded-circle" style="width: 44px; height: 44px; object-fit: cover;">
                        @else
                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                 style="width: 44px; height: 44px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-size: 16px;">
                                {{ mb_substr($p->name, 0, 1) }}
                            </div>
                        @endif

                        <!-- 온라인 상태 -->
                        @if (in_array($p->user_uuid, $onlineParticipants))
                            <span class="position-absolute bottom-0 end-0 bg-success border border-white rounded-circle"
                                  style="width: 14px; height: 14px;"></span>
                        @endif
                    </div>

                    <!-- 사용자 정보 -->
                    <div class="flex-grow-1">
                        <!-- 첫 번째 줄: 이름과 온라인 상태 -->
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="d-flex align-items-center">
                                <span class="fw-medium me-2">{{ $p->name }}</span>
                                <small class="text-{{ in_array($p->user_uuid, $onlineParticipants) ? 'success' : 'muted' }}">
                                    {{ in_array($p->user_uuid, $onlineParticipants) ? 'Online' : 'Offline' }}
                                </small>
                            </div>

                            <!-- 언어 설정 버튼 (방장/관리자만 표시) -->
                            @if($participant && in_array($participant->role, ['owner', 'admin']))
                                <button class="btn btn-sm btn-outline-secondary"
                                        wire:click="showLanguageSettings({{ $p->id }})"
                                        style="font-size: 11px; padding: 3px 8px;">
                                    <i class="fas fa-language"></i>
                                </button>
                            @endif
                        </div>

                        <!-- 두 번째 줄: 배지들 -->
                        <div class="d-flex align-items-center flex-wrap gap-1">
                            @if ($p->role === 'owner')
                                <span class="badge bg-warning text-dark" style="font-size: 10px;">
                                    <i class="fas fa-crown"></i> 방장
                                </span>
                            @endif

                            @if ($p->user_uuid === $user->uuid)
                                <span class="badge bg-primary" style="font-size: 10px;">나</span>
                            @endif

                            <!-- 국기로 언어 표시 -->
                            <span style="font-size: 18px;">
                                {{ $this->getLanguageFlag($p->language ?? 'ko') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Card Footer - 액션 버튼들 -->
    <div class="card-footer border-top bg-white p-3 flex-shrink-0">
        @if($participant && $participant->role === 'owner')
            <button class="btn btn-outline-primary btn-sm w-100 mb-2" wire:click="showSettings">
                <i class="fas fa-cog me-1"></i> 방 설정
            </button>
        @endif
        <button class="btn btn-outline-danger btn-sm w-100" wire:click="leaveRoom">
            <i class="fas fa-sign-out-alt me-1"></i> 방 나가기
        </button>
    </div>

    <!-- 멤버 추가 모달 -->
    @if($showAddMemberModal)
        <div class="modal d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user-plus text-primary"></i> 멤버 추가
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeAddMember"></button>
                    </div>
                    <div class="modal-body">
                        <form wire:submit.prevent="addMember">
                            <div class="mb-3">
                                <label class="form-label">이메일 주소</label>
                                <div class="position-relative">
                                    <input type="email" class="form-control {{ $emailValidation === 'invalid' ? 'is-invalid' : ($emailValidation === 'valid' ? 'is-valid' : '') }}"
                                           wire:model.live="memberEmail"
                                           placeholder="초대할 사용자의 이메일을 입력하세요">

                                    @if($emailValidation === 'checking')
                                        <div class="position-absolute end-0 top-50 translate-middle-y me-3">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                <span class="visually-hidden">확인 중...</span>
                                            </div>
                                        </div>
                                    @elseif($emailValidation === 'valid')
                                        <div class="position-absolute end-0 top-50 translate-middle-y me-3">
                                            <i class="fas fa-check text-success"></i>
                                        </div>
                                    @elseif($emailValidation === 'invalid')
                                        <div class="position-absolute end-0 top-50 translate-middle-y me-3">
                                            <i class="fas fa-times text-danger"></i>
                                        </div>
                                    @elseif($emailValidation === 'exists')
                                        <div class="position-absolute end-0 top-50 translate-middle-y me-3">
                                            <i class="fas fa-exclamation-triangle text-warning"></i>
                                        </div>
                                    @endif
                                </div>

                                @if($emailValidation === 'valid' && $validatedUser)
                                    <div class="text-success small mt-1">
                                        <i class="fas fa-user"></i> {{ $validatedUser->name }} ({{ $validatedUser->email }})
                                    </div>
                                @elseif($emailValidation === 'invalid')
                                    <div class="text-danger small mt-1">등록된 회원을 찾을 수 없습니다.</div>
                                @elseif($emailValidation === 'exists')
                                    <div class="text-warning small mt-1">이미 채팅방에 참여 중인 회원입니다.</div>
                                @endif

                                @error('memberEmail')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">기본 언어</label>
                                <select class="form-select" wire:model="memberLanguage" style="font-size: 16px;">
                                    @foreach($availableLanguages as $lang)
                                        <option value="{{ $lang['code'] }}">
                                            {{ $lang['flag'] ?? '🌐' }}  {{ $lang['native_name'] }} ({{ $lang['name'] }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('memberLanguage')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-secondary" wire:click="closeAddMember">취소</button>
                                <button type="submit"
                                        class="btn btn-primary"
                                        {{ $emailValidation !== 'valid' ? 'disabled' : '' }}>
                                    @if($emailValidation === 'checking')
                                        <i class="fas fa-spinner fa-spin me-1"></i> 확인 중
                                    @else
                                        추가
                                    @endif
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- 초대 링크 모달 -->
    @if($showInviteModal)
        <div class="modal d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-link text-primary"></i> 초대 링크
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeInvite"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">초대 링크</label>
                            <div class="input-group">
                                <input type="text" class="form-control" value="{{ $inviteLink }}" readonly>
                                <button class="btn btn-outline-secondary"
                                        onclick="navigator.clipboard.writeText('{{ $inviteLink }}')">
                                    <i class="fas fa-copy"></i> 복사
                                </button>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-primary" wire:click="closeInvite">확인</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- 방 설정 모달 -->
    @if($showSettingsModal)
        <div class="modal d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-cog text-primary"></i> 방 설정
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeSettings"></button>
                    </div>
                    <div class="modal-body">
                        <form wire:submit.prevent="updateBackgroundColor">
                            <div class="mb-3">
                                <label class="form-label">배경색</label>
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="color" class="form-control form-control-color" wire:model="backgroundColor">
                                    <input type="text" class="form-control" wire:model="backgroundColor" placeholder="#ffffff">
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-secondary" wire:click="closeSettings">취소</button>
                                <button type="submit" class="btn btn-primary">저장</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- 언어 설정 모달 -->
    @if($showLanguageModal)
        <div class="modal d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-language text-primary"></i> 언어 설정
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeLanguageSettings"></button>
                    </div>
                    <div class="modal-body">
                        @if($editingParticipant)
                            <div class="mb-3 text-center">
                                <div class="d-flex align-items-center justify-content-center mb-2">
                                    @if ($editingParticipant->avatar)
                                        <img src="{{ $editingParticipant->avatar }}" alt="{{ $editingParticipant->name }}"
                                             class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                    @else
                                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold me-2"
                                             style="width: 32px; height: 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                            {{ mb_substr($editingParticipant->name, 0, 1) }}
                                        </div>
                                    @endif
                                    <strong>{{ $editingParticipant->name }}</strong>
                                    <span class="ms-2" style="font-size: 20px;">
                                        {{ $this->getLanguageFlag($editingParticipant->language ?? 'ko') }}
                                    </span>
                                </div>
                            </div>

                            <form wire:submit.prevent="updateParticipantLanguage">
                                <div class="mb-3">
                                    <label class="form-label">언어 선택</label>
                                    <select class="form-select" wire:model="memberLanguage" style="font-size: 16px;">
                                        @foreach($availableLanguages as $lang)
                                            <option value="{{ $lang['code'] }}">
                                                {{ $lang['flag'] ?? '🌐' }}  {{ $lang['native_name'] }} ({{ $lang['name'] }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('memberLanguage')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-secondary" wire:click="closeLanguageSettings">취소</button>
                                    <button type="submit" class="btn btn-primary">저장</button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>