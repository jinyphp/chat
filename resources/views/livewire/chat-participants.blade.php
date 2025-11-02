<div>
    <aside class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <!-- 왼쪽: 타이틀 -->
            <div>
                <h4 class="mb-1 fw-bold">
                    <i class="fas fa-users text-primary me-2"></i>참여자 목록
                </h4>
                {{-- <p class="text-muted mb-0">총 {{ count($participants) }}명이 참여 중입니다</p> --}}
            </div>

            <!-- 오른쪽: 관리자 액션 버튼 -->
            @if($participant && in_array($participant->role ?? 'member', ['owner', 'admin']))
                <div class="d-flex gap-1">
                    <button class="btn btn-primary btn-sm rounded-circle d-flex align-items-center justify-content-center"
                            wire:click="showAddMember"
                            title="멤버 추가"
                            style="width: 32px; height: 32px;">
                        <i class="fas fa-user-plus"></i>
                    </button>
                    <button class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center"
                            wire:click="generateInviteLink"
                            title="초대 링크 생성"
                            style="width: 32px; height: 32px;">
                        <i class="fas fa-link"></i>
                    </button>
                </div>
            @endif
        </div>
        <div class="card-body">
            @if($participants && count($participants) > 0)
                @foreach($participants as $p)
                    <div class="list-group-item list-group-item-action border-0">
                        <div class="d-flex align-items-center">
                            <!-- 아바타 -->
                            <div class="avatar avatar-md avatar-indicators {{ in_array($p->user_uuid, $onlineParticipants) ? 'avatar-online' : 'avatar-offline' }} me-3">
                                @if($p->avatar)
                                    <img src="{{ $p->avatar }}" alt="{{ $p->name }}" class="rounded-circle">
                                @else
                                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                         style="width: 44px; height: 44px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-size: 16px;">
                                        {{ mb_substr($p->name, 0, 1) }}
                                    </div>
                                @endif
                            </div>

                            <!-- 사용자 정보 -->
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-1">
                                    <h6 class="mb-0 me-2">{{ $p->name }}</h6>

                                    <!-- 역할 배지 -->
                                    @if($p->role === 'owner')
                                        <span class="badge bg-warning text-dark me-1">
                                            <i class="fas fa-crown"></i> 방장
                                        </span>
                                    @elseif($p->role === 'admin')
                                        <span class="badge bg-info me-1">
                                            <i class="fas fa-shield-alt"></i> 관리자
                                        </span>
                                    @endif

                                    <!-- 나 표시 -->
                                    @if($p->user_uuid === ($user->uuid ?? ''))
                                        <span class="badge bg-primary me-1">나</span>
                                    @endif

                                    <!-- 언어 플래그 -->
                                    <span class="me-2">{{ $this->getLanguageFlag($p->language ?? 'ko') }}</span>
                                </div>

                                <div class="d-flex align-items-center">
                                    <!-- 온라인 상태 -->
                                    <small class="text-{{ in_array($p->user_uuid, $onlineParticipants) ? 'success' : 'muted' }} me-3">
                                        <i class="fas fa-circle me-1" style="font-size: 6px;"></i>
                                        {{ in_array($p->user_uuid, $onlineParticipants) ? 'Online' : 'Offline' }}
                                    </small>

                                    <!-- 참여일 -->
                                    @if($p->joined_at)
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            {{ $p->joined_at->format('Y.m.d') }} 참여
                                        </small>
                                    @endif
                                </div>

                                <!-- 이메일 (있는 경우) -->
                                @if($p->email)
                                    <small class="text-muted d-block">{{ $p->email }}</small>
                                @endif
                            </div>

                            <!-- 액션 버튼 -->
                            @php
                                $isCurrentUser = $user && $p->user_uuid === $user->uuid;
                                $isOwnerOrAdmin = $participant && in_array($participant->role ?? 'member', ['owner', 'admin']);
                                $canEditOthers = $isOwnerOrAdmin && !$isCurrentUser;
                                $hasAnyOptions = $isCurrentUser || $canEditOthers;
                            @endphp

                            @if($hasAnyOptions)
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        @if($isCurrentUser)
                                            <li><a class="dropdown-item" href="#" wire:click="editOwnProfile">
                                                <i class="fas fa-user-edit me-2"></i> 내 정보 수정
                                            </a></li>
                                        @endif

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
                    </div>
                @endforeach
            @else
                <!-- 빈 상태 -->
                <div class="list-group-item text-center py-5">
                    <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">참여자가 없습니다</h6>
                    <p class="text-muted mb-0">아직 이 채팅방에 참여한 사용자가 없습니다.</p>
                </div>
            @endif
        </div>
    </aside>


    <!-- 멤버 추가 모달 -->
    @if ($showAddMemberModal)
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
                                    <input type="email"
                                        class="form-control {{ $emailValidation === 'invalid' ? 'is-invalid' : ($emailValidation === 'valid' ? 'is-valid' : '') }}"
                                        wire:model.live="memberEmail" placeholder="초대할 사용자의 이메일을 입력하세요">

                                    @if ($emailValidation === 'checking')
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

                                @if ($emailValidation === 'valid' && $validatedUser)
                                    <div class="text-success small mt-1">
                                        <i class="fas fa-user"></i> {{ $validatedUser->name }}
                                        ({{ $validatedUser->email }})
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
                                    @foreach ($availableLanguages as $lang)
                                        <option value="{{ $lang['code'] }}">
                                            {{ $lang['flag'] ?? '🌐' }} {{ $lang['native_name'] }}
                                            ({{ $lang['name'] }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('memberLanguage')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-secondary"
                                    wire:click="closeAddMember">취소</button>
                                <button type="submit" class="btn btn-primary"
                                    {{ $emailValidation !== 'valid' ? 'disabled' : '' }}>
                                    @if ($emailValidation === 'checking')
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
    @if ($showInviteModal)
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
                                <input type="text" class="form-control" value="{{ $inviteLink }}" readonly
                                    id="inviteLink">
                                <button class="btn btn-outline-secondary" onclick="copyInviteLink()">
                                    <i class="fas fa-copy"></i> 복사
                                </button>
                            </div>
                            <small class="text-muted">이 링크를 공유하여 다른 사용자를 채팅방에 초대할 수 있습니다.</small>
                        </div>

                        <div class="mb-3">
                            <div class="alert alert-info">
                                <h6 class="alert-heading"><i class="fas fa-info-circle me-1"></i> 초대 링크 정보
                                </h6>
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
                            const body = encodeURIComponent(
                                `안녕하세요! 채팅방에 초대드립니다.\n\n아래 링크를 클릭하여 참여해주세요:\n${document.getElementById('inviteLink').value}`);
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
                            toast.innerHTML =
                                `${message} <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>`;
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

    <!-- 언어 설정 모달 -->
    @if ($showLanguageModal)
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
                        @if ($editingParticipant)
                            <div class="mb-3 text-center">
                                <div class="d-flex align-items-center justify-content-center mb-2">
                                    @if ($editingParticipant->avatar)
                                        <img src="{{ $editingParticipant->avatar }}"
                                            alt="{{ $editingParticipant->name }}" class="rounded-circle me-2"
                                            style="width: 32px; height: 32px; object-fit: cover;">
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
                                        @foreach ($availableLanguages as $lang)
                                            <option value="{{ $lang['code'] }}">
                                                {{ $lang['flag'] ?? '🌐' }} {{ $lang['native_name'] }}
                                                ({{ $lang['name'] }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('memberLanguage')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-secondary"
                                        wire:click="closeLanguageSettings">취소</button>
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
    @if ($showEditModal)
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
                        @if ($editingParticipant)
                            <form wire:submit.prevent="updateParticipantInfo">
                                <div class="mb-3 text-center">
                                    <div class="d-flex align-items-center justify-content-center mb-3">
                                        @if ($editingParticipant->avatar)
                                            <img src="{{ $editingParticipant->avatar }}"
                                                alt="{{ $editingParticipant->name }}" class="rounded-circle me-2"
                                                style="width: 48px; height: 48px; object-fit: cover;">
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
                                        @foreach ($availableLanguages as $lang)
                                            <option value="{{ $lang['code'] }}">
                                                {{ $lang['flag'] ?? '🌐' }} {{ $lang['native_name'] }}
                                                ({{ $lang['name'] }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('editLanguage')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>

                                @if ($participant && in_array($participant->role ?? 'member', ['owner', 'admin']) && $editingParticipant->user_uuid !== $user->uuid)
                                    <div class="mb-3">
                                        <label class="form-label">역할</label>
                                        <select class="form-select" wire:model="editRole">
                                            <option value="member">일반 멤버</option>
                                            @if (($participant->role ?? 'member') === 'owner')
                                                <option value="admin">관리자</option>
                                            @endif
                                        </select>
                                        @error('editRole')
                                            <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endif

                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-secondary"
                                        wire:click="closeEditModal">취소</button>
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
    @if ($showRemoveModal)
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
                        @if ($removingParticipant)
                            <div class="text-center mb-3">
                                <div class="d-flex align-items-center justify-content-center mb-2">
                                    @if ($removingParticipant->avatar)
                                        <img src="{{ $removingParticipant->avatar }}"
                                            alt="{{ $removingParticipant->name }}" class="rounded-circle me-2"
                                            style="width: 32px; height: 32px; object-fit: cover;">
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
