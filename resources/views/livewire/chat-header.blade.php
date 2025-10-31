<div>

    <div class="bg-white border-top border-bottom px-4 py-3 sticky-top">
        <div class="d-flex justify-content-between align-items-center">
            <!-- media -->
            <div class="d-flex align-items-center">
                <a href="#" class="me-2 d-xl-none d-block" data-close=""><i class="fe fe-arrow-left"></i></a>
                <div class="avatar avatar-md avatar-indicators avatar-online">
                    <img src="../../assets/images/avatar/avatar-4.jpg" alt="" class="rounded-circle">
                </div>
                <!-- media body -->
                <div class="ms-2">
                    <h4 class="mb-0">Sharad Mishra 채팅</h4>
                    <p class="mb-0">Online</p>
                </div>


            </div>
            <div class="d-flex align-items-center">
                <a href="#" class="me-3 text-link texttooltip" data-template="phone">
                    <i class="fe fe-phone-call fs-3"></i>
                    <!-- text -->
                    <div id="phone" class="d-none">
                        <span>Voice Call</span>
                    </div>
                </a>
                <a href="#" class="me-3 text-link texttooltip" data-template="video">
                    <i class="fe fe-video fs-3"></i>
                    <!-- text -->
                    <div id="video" class="d-none">
                        <span>Video Call</span>
                    </div>
                </a>
                <a href="#" class="me-3 text-link texttooltip" data-template="adduser">
                    <i class="fe fe-user-plus fs-3"></i>3
                    <!-- text -->
                    <div id="adduser" class="d-none">
                        <span>Add User</span>
                    </div>
                </a>
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
                        <li><a class="dropdown-item" href="#" wire:click="toggleTranslations">
                                <i class="fas fa-language me-2"></i>
                                {{ $showTranslations ? '번역 숨기기' : '번역 표시' }}
                            </a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
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
                    </ul>
                </div>
            </div>
        </div>
    </div>









    <!-- Card Header - 채팅방 정보 및 설정 -->
    {{-- <div class="d-flex align-items-center justify-content-between">
        <div>
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="fas fa-comments text-primary me-2"></i>

                <!-- 동적 폴링 상태 표시 -->
                <span class="badge bg-success ms-2 d-flex align-items-center">
                    <i class="fas fa-sync-alt me-1" title="폴링 간격: {{ $pollingInterval }}초"></i>
                    <span>{{ $pollingInterval }}s</span>
                </span>
            </h6>
            <small class="text-muted">
                총 {{ $messageCount }}개의 메시지
                @if ($pollingInterval <= 1)
                    <span class="text-success ms-2">
                        <i class="fas fa-bolt"></i> 빠른 업데이트
                    </span>
                @endif
            </small>
        </div>


    </div> --}}

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


</div>
