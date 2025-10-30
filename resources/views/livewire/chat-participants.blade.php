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

                            <!-- 액션 버튼들 -->
                            @php
                                $isCurrentUser = $user && $p->user_uuid === $user->uuid;
                                $isOwnerOrAdmin = $participant && in_array($participant->role, ['owner', 'admin']);
                                $canEditOthers = $isOwnerOrAdmin && !$isCurrentUser;
                                $hasAnyOptions = $isCurrentUser || $canEditOthers;
                            @endphp

                            @if($hasAnyOptions)
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                            type="button" data-bs-toggle="dropdown"
                                            style="font-size: 11px; padding: 3px 8px;">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <!-- 디버깅 정보 (개발용) -->
                                        @if(config('app.debug'))
                                            <li><small class="dropdown-item-text text-muted">
                                                현재: {{ $user->uuid ?? 'null' }}<br>
                                                참여자: {{ $p->user_uuid }}<br>
                                                본인: {{ $isCurrentUser ? 'O' : 'X' }}<br>
                                                권한: {{ $participant->role ?? 'none' }}
                                            </small></li>
                                            <li><hr class="dropdown-divider"></li>
                                        @endif

                                        <!-- 자신의 정보 수정 (본인만) -->
                                        @if($isCurrentUser)
                                            <li><a class="dropdown-item" href="#" wire:click="editOwnProfile">
                                                <i class="fas fa-user-edit me-2"></i> 내 정보 수정
                                            </a></li>
                                        @endif

                                        <!-- 방장/관리자 기능 (다른 사람만) -->
                                        @if($canEditOthers)
                                            <li><a class="dropdown-item" href="#" wire:click="editParticipant({{ $p->id }})">
                                                <i class="fas fa-edit me-2"></i> 정보 수정
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" wire:click="showLanguageSettings({{ $p->id }})">
                                                <i class="fas fa-language me-2"></i> 언어 설정
                                            </a></li>
                                            @if($p->role !== 'owner')
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="#" wire:click="confirmRemoveParticipant({{ $p->id }})">
                                                    <i class="fas fa-user-times me-2"></i> 멤버 제거
                                                </a></li>
                                            @endif
                                        @endif
                                    </ul>
                                </div>
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
                                <input type="text" class="form-control" value="{{ $inviteLink }}" readonly id="inviteLink">
                                <button class="btn btn-outline-secondary" onclick="copyInviteLink()">
                                    <i class="fas fa-copy"></i> 복사
                                </button>
                            </div>
                            <small class="text-muted">이 링크를 공유하여 다른 사용자를 채팅방에 초대할 수 있습니다.</small>
                        </div>

                        <div class="mb-3">
                            <div class="alert alert-info">
                                <h6 class="alert-heading"><i class="fas fa-info-circle me-1"></i> 초대 링크 정보</h6>
                                <ul class="mb-0 small">
                                    <li><strong>유효기간:</strong> 24시간</li>
                                    <li><strong>사용 제한:</strong> 무제한</li>
                                    <li><strong>자동 참여:</strong> 링크 클릭 시 즉시 채팅방 참여</li>
                                </ul>
                            </div>
                        </div>

                        <div class="mb-3">
                            <h6>공유 방법</h6>
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-primary btn-sm" onclick="shareViaEmail()">
                                    <i class="fas fa-envelope me-1"></i> 이메일
                                </button>
                                <button class="btn btn-outline-success btn-sm" onclick="shareViaKakao()">
                                    <i class="fab fa-kaggle me-1"></i> 카카오톡
                                </button>
                                <button class="btn btn-outline-secondary btn-sm" onclick="shareGeneric()">
                                    <i class="fas fa-share me-1"></i> 기타
                                </button>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-primary" wire:click="closeInvite">확인</button>
                        </div>
                    </div>

                    <script>
                        function copyInviteLink() {
                            const linkInput = document.getElementById('inviteLink');
                            linkInput.select();
                            linkInput.setSelectionRange(0, 99999);

                            try {
                                navigator.clipboard.writeText(linkInput.value).then(function() {
                                    showToast('초대 링크가 클립보드에 복사되었습니다.', 'success');
                                });
                            } catch (err) {
                                document.execCommand('copy');
                                showToast('초대 링크가 복사되었습니다.', 'success');
                            }
                        }

                        function shareViaEmail() {
                            const subject = encodeURIComponent('채팅방 초대');
                            const body = encodeURIComponent(`안녕하세요! 채팅방에 초대드립니다.\n\n아래 링크를 클릭하여 참여해주세요:\n${document.getElementById('inviteLink').value}`);
                            window.open(`mailto:?subject=${subject}&body=${body}`);
                        }

                        function shareViaKakao() {
                            // 카카오톡 공유 (실제 구현 시 카카오 SDK 필요)
                            copyInviteLink();
                            showToast('링크가 복사되었습니다. 카카오톡에서 붙여넣기 해주세요.', 'info');
                        }

                        function shareGeneric() {
                            if (navigator.share) {
                                navigator.share({
                                    title: '채팅방 초대',
                                    text: '채팅방에 초대드립니다!',
                                    url: document.getElementById('inviteLink').value
                                });
                            } else {
                                copyInviteLink();
                            }
                        }

                        function showToast(message, type = 'info') {
                            // 간단한 토스트 알림 (실제 구현 시 Toast 라이브러리 사용)
                            const toast = document.createElement('div');
                            toast.className = `alert alert-${type} position-fixed`;
                            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                            toast.innerHTML = `${message} <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>`;
                            document.body.appendChild(toast);

                            setTimeout(() => {
                                if (toast.parentElement) {
                                    toast.remove();
                                }
                            }, 3000);
                        }
                    </script>
                </div>
            </div>
        </div>
    @endif

    <!-- 방 설정 모달 -->
    @if($showSettingsModal)
        <div class="modal d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-cog text-primary"></i> 방 설정
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeSettings"></button>
                    </div>
                    <div class="modal-body">
                        <!-- 탭 네비게이션 -->
                        <ul class="nav nav-tabs mb-3" id="settingsTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">
                                    <i class="fas fa-info-circle me-1"></i> 기본정보
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="access-tab" data-bs-toggle="tab" data-bs-target="#access" type="button" role="tab">
                                    <i class="fas fa-shield-alt me-1"></i> 접근설정
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="appearance-tab" data-bs-toggle="tab" data-bs-target="#appearance" type="button" role="tab">
                                    <i class="fas fa-palette me-1"></i> 외관설정
                                </button>
                            </li>
                        </ul>

                        <form wire:submit.prevent="updateRoomSettings">
                            <!-- 탭 콘텐츠 -->
                            <div class="tab-content" id="settingsTabContent">
                                <!-- 기본정보 탭 -->
                                <div class="tab-pane fade show active" id="basic" role="tabpanel">
                                    {{-- 채팅방 제목 --}}
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">
                                            채팅방 제목 <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" wire:model="settingsTitle"
                                               placeholder="채팅방 제목을 입력하세요" required maxlength="255">
                                        @error('settingsTitle')
                                            <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- 설명 --}}
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">설명</label>
                                        <textarea class="form-control" wire:model="settingsDescription" rows="3"
                                                  placeholder="채팅방에 대한 설명을 입력하세요" maxlength="1000"></textarea>
                                        @error('settingsDescription')
                                            <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- 채팅방 타입 --}}
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">채팅방 타입</label>
                                        <select class="form-select" wire:model="settingsType">
                                            <option value="public">
                                                <i class="fas fa-globe"></i> 공개 - 누구나 검색하고 참여할 수 있습니다
                                            </option>
                                            <option value="private">
                                                <i class="fas fa-lock"></i> 비공개 - 초대를 통해서만 참여할 수 있습니다
                                            </option>
                                            <option value="group">
                                                <i class="fas fa-users"></i> 그룹 - 소규모 그룹을 위한 채팅방입니다
                                            </option>
                                        </select>
                                        @error('settingsType')
                                            <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- 최대 참여자 수 --}}
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">최대 참여자 수</label>
                                        <input type="number" class="form-control" wire:model="settingsMaxParticipants"
                                               min="0" max="1000" placeholder="0 (무제한)">
                                        <div class="form-text small">0 또는 비워두면 무제한, 2-1000명 범위에서 제한 가능</div>
                                        @error('settingsMaxParticipants')
                                            <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <!-- 접근설정 탭 -->
                                <div class="tab-pane fade" id="access" role="tabpanel">
                                    {{-- 접근 권한 --}}
                                    <div class="mb-4">
                                        <div class="form-check mb-3">
                                            <input id="settings_is_public" type="checkbox" value="1"
                                                   wire:model="settingsIsPublic" class="form-check-input">
                                            <label for="settings_is_public" class="form-check-label">
                                                <div class="fw-semibold">
                                                    <i class="fas fa-search text-primary me-1"></i> 검색 가능
                                                </div>
                                                <div class="text-muted small">다른 사용자가 채팅방을 검색할 수 있습니다</div>
                                            </label>
                                        </div>

                                        <div class="form-check mb-3">
                                            <input id="settings_allow_join" type="checkbox" value="1"
                                                   wire:model="settingsAllowJoin" class="form-check-input">
                                            <label for="settings_allow_join" class="form-check-label">
                                                <div class="fw-semibold">
                                                    <i class="fas fa-door-open text-success me-1"></i> 자유 참여 허용
                                                </div>
                                                <div class="text-muted small">승인 없이 자유롭게 참여할 수 있습니다</div>
                                            </label>
                                        </div>

                                        <div class="form-check mb-3">
                                            <input id="settings_allow_invite" type="checkbox" value="1"
                                                   wire:model="settingsAllowInvite" class="form-check-input">
                                            <label for="settings_allow_invite" class="form-check-label">
                                                <div class="fw-semibold">
                                                    <i class="fas fa-user-plus text-info me-1"></i> 초대 허용
                                                </div>
                                                <div class="text-muted small">참여자가 다른 사용자를 초대할 수 있습니다</div>
                                            </label>
                                        </div>
                                    </div>

                                    {{-- 비밀번호 --}}
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-key text-warning me-1"></i> 비밀번호 (선택사항)
                                        </label>
                                        <input type="password" class="form-control"
                                               wire:model="settingsPassword" placeholder="참여 시 필요한 비밀번호"
                                               minlength="4">
                                        <div class="form-text small">비밀번호를 설정하면 참여 시 비밀번호 입력이 필요합니다</div>
                                        @error('settingsPassword')
                                            <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <!-- 외관설정 탭 -->
                                <div class="tab-pane fade" id="appearance" role="tabpanel">
                                    {{-- 배경색 --}}
                                    <div class="mb-4">
                                        <label class="form-label fw-semibold">배경색</label>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label small">색상 선택</label>
                                                <input type="color" class="form-control form-control-color w-100"
                                                       wire:model="backgroundColor" style="height: 50px;">
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label small">색상 코드</label>
                                                <input type="text" class="form-control" wire:model="backgroundColor"
                                                       placeholder="#f8f9fa" pattern="^#[a-fA-F0-9]{6}$">
                                                <div class="form-text small">16진수 색상 코드를 입력하세요 (예: #f8f9fa)</div>
                                            </div>
                                        </div>
                                        @error('backgroundColor')
                                            <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- 미리보기 --}}
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">미리보기</label>
                                        <div class="border rounded p-3" style="background-color: {{ $backgroundColor }};">
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-2"
                                                     style="width: 32px; height: 32px;">
                                                    <i class="fas fa-user text-white"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold small">사용자 이름</div>
                                                    <div class="text-muted" style="font-size: 11px;">2분 전</div>
                                                </div>
                                            </div>
                                            <div class="bg-white rounded p-2 shadow-sm">
                                                <small>이것은 채팅 메시지 미리보기입니다.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 버튼 영역 -->
                            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                <button type="button" class="btn btn-secondary" wire:click="closeSettings">취소</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> 저장
                                </button>
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

    <!-- 참여자 정보 수정 모달 -->
    @if($showEditModal)
        <div class="modal d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user-edit text-primary"></i>
                            {{ $editingParticipant && $editingParticipant->user_uuid === $user->uuid ? '내 정보 수정' : '참여자 정보 수정' }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeEditModal"></button>
                    </div>
                    <div class="modal-body">
                        @if($editingParticipant)
                            <form wire:submit.prevent="updateParticipantInfo">
                                <div class="mb-3 text-center">
                                    <div class="d-flex align-items-center justify-content-center mb-3">
                                        @if ($editingParticipant->avatar)
                                            <img src="{{ $editingParticipant->avatar }}" alt="{{ $editingParticipant->name }}"
                                                 class="rounded-circle me-2" style="width: 48px; height: 48px; object-fit: cover;">
                                        @else
                                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold me-2"
                                                 style="width: 48px; height: 48px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                                {{ mb_substr($editingParticipant->name, 0, 1) }}
                                            </div>
                                        @endif
                                        <div>
                                            <div class="fw-bold">{{ $editingParticipant->name }}</div>
                                            <small class="text-muted">{{ $editingParticipant->email }}</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">표시 이름</label>
                                    <input type="text" class="form-control" wire:model="editName"
                                           placeholder="채팅방에서 표시될 이름">
                                    @error('editName')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">언어</label>
                                    <select class="form-select" wire:model="editLanguage" style="font-size: 16px;">
                                        @foreach($availableLanguages as $lang)
                                            <option value="{{ $lang['code'] }}">
                                                {{ $lang['flag'] ?? '🌐' }}  {{ $lang['native_name'] }} ({{ $lang['name'] }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('editLanguage')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>

                                @if($participant && in_array($participant->role, ['owner', 'admin']) && $editingParticipant->user_uuid !== $user->uuid)
                                    <div class="mb-3">
                                        <label class="form-label">역할</label>
                                        <select class="form-select" wire:model="editRole">
                                            <option value="member">일반 멤버</option>
                                            @if($participant->role === 'owner')
                                                <option value="admin">관리자</option>
                                            @endif
                                        </select>
                                        @error('editRole')
                                            <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endif

                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-secondary" wire:click="closeEditModal">취소</button>
                                    <button type="submit" class="btn btn-primary">저장</button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- 참여자 제거 확인 모달 -->
    @if($showRemoveModal)
        <div class="modal d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user-times text-danger"></i> 멤버 제거
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeRemoveModal"></button>
                    </div>
                    <div class="modal-body">
                        @if($removingParticipant)
                            <div class="text-center mb-3">
                                <div class="d-flex align-items-center justify-content-center mb-2">
                                    @if ($removingParticipant->avatar)
                                        <img src="{{ $removingParticipant->avatar }}" alt="{{ $removingParticipant->name }}"
                                             class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                    @else
                                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold me-2"
                                             style="width: 32px; height: 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                            {{ mb_substr($removingParticipant->name, 0, 1) }}
                                        </div>
                                    @endif
                                    <strong>{{ $removingParticipant->name }}</strong>
                                </div>
                            </div>
                            <p class="text-center">정말로 이 멤버를 채팅방에서 제거하시겠습니까?</p>
                            <p class="text-muted small text-center">제거된 멤버는 다시 초대해야 채팅방에 참여할 수 있습니다.</p>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeRemoveModal">취소</button>
                        <button type="button" class="btn btn-danger" wire:click="removeParticipant">제거</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>