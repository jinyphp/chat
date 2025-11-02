<div>

    <div class="card mb-2">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <!-- media -->
                <div class="d-flex align-items-center">
                    <button type="button" class="btn btn-link me-2 p-1" wire:click="goToChatList" title="채팅방 목록">
                        <i class="fe fe-arrow-left"></i>
                    </button>

                    <!-- 채팅방 정보 -->
                    <div class="ms-2">
                        <div class="d-flex align-items-center">
                            <!-- 클릭 가능한 채팅방 타이틀 -->
                            <button type="button" class="btn btn-link text-start p-0 text-decoration-none"
                                wire:click="goToChatList">
                                <h4 class="mb-0 me-2 text-dark">{{ $room->title ?? '채팅방' }}</h4>
                            </button>

                            <!-- 채팅방 타입 (타이틀 옆으로 이동) -->
                            @if ($room && $room->type)
                                <span
                                    class="badge me-2
                                @if ($room->type === 'private') bg-warning text-dark
                                @elseif($room->type === 'public') bg-success
                                @elseif($room->type === 'group') bg-info @endif">
                                    @if ($room->type === 'private')
                                        <i class="fas fa-lock me-1"></i>비공개
                                    @elseif($room->type === 'public')
                                        <i class="fas fa-globe me-1"></i>공개
                                    @elseif($room->type === 'group')
                                        <i class="fas fa-users me-1"></i>그룹
                                    @endif
                                </span>
                            @endif
                        </div>

                        <!-- 참여자 정보 -->
                        <p class="mb-0 text-muted small">
                            <i class="fas fa-user-friends me-1"></i>
                            참여자 {{ $participantsCount }}명
                        </p>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">

                    {{-- 채팅방 액션 버튼들 --}}
                    <div class="btn-group" role="group">
                        <a href="{{ route('home.chat.room.images', $room->id) }}" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-images me-1"></i>
                            이미지 갤러리
                        </a>
                        {{-- 추후 다른 기능 버튼들 추가 가능 --}}
                    </div>


                    <!-- 번역 토글 버튼 (설정에서 분리) -->
                    <button type="button"
                            class="btn {{ $showTranslations ? 'btn-primary' : 'btn-outline-secondary' }} btn-sm d-flex align-items-center texttooltip"
                            wire:click="toggleTranslations"
                            data-template="translation">
                        <i class="fas fa-language me-1"></i>
                        <span class="d-none d-md-inline">{{ $showTranslations ? '번역 OFF' : '번역 ON' }}</span>
                        <span class="d-md-none">번역</span>
                        <!-- tooltip text -->
                        <div id="translation" class="d-none">
                            <span>{{ $showTranslations ? '번역 숨기기' : '번역 표시' }}</span>
                        </div>
                    </button>

                    <!-- 채팅방 설정 드롭다운 -->
                    <div class="dropdown">
                        <a href="#" class="text-link texttooltip" data-bs-toggle="dropdown" aria-haspopup="true"
                            aria-expanded="false" data-template="settings">
                            <i class="fe fe-settings fs-3"></i>
                            <!-- text -->
                            <div id="settings" class="d-none">
                                <span>Settings</span>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <h6 class="dropdown-header">
                                    <i class="fas fa-sync-alt me-2"></i> 폴링 간격 설정
                                </h6>
                            </li>
                            <li><a class="dropdown-item" href="#" wire:click="setPollingInterval(0.5)">
                                    <i class="fas fa-bolt me-2"></i> 빠르게 (0.5초)
                                </a></li>
                            <li><a class="dropdown-item" href="#" wire:click="setPollingInterval(1)">
                                    <i class="fas fa-tachometer-alt me-2"></i> 보통 (1초)
                                </a></li>
                            <li><a class="dropdown-item" href="#" wire:click="setPollingInterval(3)">
                                    <i class="fas fa-clock me-2"></i> 기본 (3초)
                                </a></li>
                            <li><a class="dropdown-item" href="#" wire:click="setPollingInterval(10)">
                                    <i class="fas fa-hourglass-half me-2"></i> 느리게 (10초)
                                </a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="#" wire:click="showBackgroundSettings">
                                    <i class="fas fa-palette me-2"></i> 배경색 변경
                                </a></li>
                            <li><a class="dropdown-item" href="#" wire:click="loadMoreMessages">
                                    <i class="fas fa-history me-2"></i> 이전 메시지 불러오기
                                </a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="#" wire:click="showSettings">
                                    <i class="fas fa-cog me-2"></i> 방 설정
                                </a></li>
                            <li><a class="dropdown-item text-danger" href="#" wire:click="leaveRoom">
                                    <i class="fas fa-sign-out-alt me-2"></i> 방 나가기
                                </a></li>
                        </ul>
                    </div>

                </div>
            </div>
        </div>
    </div>












    <!-- 배경색 설정 모달 -->
    @if ($showBackgroundModal)
        <div class="modal d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-palette text-primary"></i> 채팅방 배경색 변경
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeBackgroundModal"></button>
                    </div>
                    <div class="modal-body">
                        <form wire:submit.prevent="updateBackgroundColor">
                            <div class="mb-3">
                                <label class="form-label">배경색 선택</label>
                                <div class="d-flex gap-2 align-items-center mb-3">
                                    <input type="color" class="form-control form-control-color"
                                        wire:model="backgroundColor">
                                    <input type="text" class="form-control" wire:model="backgroundColor"
                                        placeholder="#ffffff">
                                </div>

                                <!-- 미리 정의된 색상 팔레트 -->
                                <div class="row g-2">
                                    <div class="col-2">
                                        <div class="bg-white border rounded p-2 text-center" style="cursor: pointer;"
                                            wire:click="setBackgroundColor('#ffffff')">
                                            <small>기본</small>
                                        </div>
                                    </div>
                                    <div class="col-2">
                                        <div class="rounded p-2 text-center text-white"
                                            style="background: #e3f2fd; color: #333 !important; cursor: pointer;"
                                            wire:click="setBackgroundColor('#e3f2fd')">
                                            <small>하늘</small>
                                        </div>
                                    </div>
                                    <div class="col-2">
                                        <div class="rounded p-2 text-center text-white"
                                            style="background: #f3e5f5; color: #333 !important; cursor: pointer;"
                                            wire:click="setBackgroundColor('#f3e5f5')">
                                            <small>라벤더</small>
                                        </div>
                                    </div>
                                    <div class="col-2">
                                        <div class="rounded p-2 text-center text-white"
                                            style="background: #e8f5e8; color: #333 !important; cursor: pointer;"
                                            wire:click="setBackgroundColor('#e8f5e8')">
                                            <small>민트</small>
                                        </div>
                                    </div>
                                    <div class="col-2">
                                        <div class="rounded p-2 text-center text-white"
                                            style="background: #fff3e0; color: #333 !important; cursor: pointer;"
                                            wire:click="setBackgroundColor('#fff3e0')">
                                            <small>복숭아</small>
                                        </div>
                                    </div>
                                    <div class="col-2">
                                        <div class="rounded p-2 text-center text-white"
                                            style="background: #fce4ec; color: #333 !important; cursor: pointer;"
                                            wire:click="setBackgroundColor('#fce4ec')">
                                            <small>핑크</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-secondary"
                                    wire:click="closeBackgroundModal">취소</button>
                                <button type="submit" class="btn btn-primary">적용</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif


    <!-- 방 설정 모달 -->
    @if ($showSettingsModal)
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
                                <button class="nav-link active" id="basic-tab" data-bs-toggle="tab"
                                    data-bs-target="#basic" type="button" role="tab">
                                    <i class="fas fa-info-circle me-1"></i> 기본정보
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="access-tab" data-bs-toggle="tab"
                                    data-bs-target="#access" type="button" role="tab">
                                    <i class="fas fa-shield-alt me-1"></i> 접근설정
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="appearance-tab" data-bs-toggle="tab"
                                    data-bs-target="#appearance" type="button" role="tab">
                                    <i class="fas fa-palette me-1"></i> 외관설정
                                </button>
                            </li>
                        </ul>

                        <form wire:submit.prevent="updateRoomSettings">
                            <!-- 탭 콘텐츠 -->
                            <div class="tab-content" id="settingsTabContent">
                                <!-- 기본정보 탭 -->
                                <div class="tab-pane fade show active" id="basic" role="tabpanel">
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

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">설명</label>
                                        <textarea class="form-control" wire:model="settingsDescription" rows="3" placeholder="채팅방에 대한 설명을 입력하세요"
                                            maxlength="1000"></textarea>
                                        @error('settingsDescription')
                                            <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>

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

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">최대 참여자 수</label>
                                        <input type="number" class="form-control"
                                            wire:model="settingsMaxParticipants" min="0" max="1000"
                                            placeholder="0 (무제한)">
                                        <div class="form-text small">0 또는 비워두면 무제한, 2-1000명 범위에서 제한 가능</div>
                                        @error('settingsMaxParticipants')
                                            <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <!-- 접근설정 탭 -->
                                <div class="tab-pane fade" id="access" role="tabpanel">
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

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-key text-warning me-1"></i> 비밀번호 (선택사항)
                                        </label>
                                        <input type="password" class="form-control" wire:model="settingsPassword"
                                            placeholder="참여 시 필요한 비밀번호" minlength="4">
                                        <div class="form-text small">비밀번호를 설정하면 참여 시 비밀번호 입력이 필요합니다</div>
                                        @error('settingsPassword')
                                            <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <!-- 외관설정 탭 -->
                                <div class="tab-pane fade" id="appearance" role="tabpanel">
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
                                                <input type="text" class="form-control"
                                                    wire:model="backgroundColor" placeholder="#f8f9fa"
                                                    pattern="^#[a-fA-F0-9]{6}$">
                                                <div class="form-text small">16진수 색상 코드를 입력하세요 (예: #f8f9fa)
                                                </div>
                                            </div>
                                        </div>
                                        @error('backgroundColor')
                                            <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">미리보기</label>
                                        <div class="border rounded p-3"
                                            style="background-color: {{ $backgroundColor }};">
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-2"
                                                    style="width: 32px; height: 32px;">
                                                    <i class="fas fa-user text-white"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold small">사용자 이름</div>
                                                    <div class="text-muted" style="font-size: 11px;">2분 전
                                                    </div>
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
                                <button type="button" class="btn btn-secondary"
                                    wire:click="closeSettings">취소</button>
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

</div>
