@if ($rooms->count() > 0)
    <div class="row g-4">
        @foreach ($rooms as $room)
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100 chat-room-card">
                    {{-- 채팅방 이미지 --}}
                    <div class="position-relative">
                        @if ($room->image)
                            <img src="{{ asset('storage/' . $room->image) }}" alt="{{ $room->title }}"
                                class="card-img-top object-fit-cover" style="height: 180px;">
                        @else
                            <div class="bg-light d-flex align-items-center justify-content-center"
                                style="height: 180px; background-color: #f8f9fa;">
                                <div class="text-center">
                                    <i class="fas fa-comments text-muted" style="font-size: 2.5rem;"></i>
                                    <div class="mt-2">
                                        <span class="text-muted fw-semibold" style="font-size: 0.9rem;">
                                            {{ Str::limit($room->title, 10) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- 참여자 수 배지 --}}
                        <div class="position-absolute top-0 end-0 m-2">
                            <span class="badge bg-dark bg-opacity-75 text-white">
                                <i class="fas fa-users me-1"></i>
                                {{ $room->activeParticipants->count() }}
                                @if ($room->max_participants)
                                    / {{ $room->max_participants }}
                                @endif
                            </span>
                        </div>

                        {{-- 방 타입 배지 --}}
                        <div class="position-absolute top-0 start-0 m-2">
                            @if ($room->is_public)
                                <span class="badge bg-success">공개</span>
                            @else
                                <span class="badge bg-secondary">비공개</span>
                            @endif
                            @if ($room->password)
                                <span class="badge bg-warning ms-1">🔒</span>
                            @endif
                        </div>
                    </div>

                    <div class="card-body p-3">
                        {{-- 채팅방 제목 --}}
                        <h5 class="card-title fw-semibold mb-2">{{ Str::limit($room->title, 30) }}</h5>

                        {{-- 설명 --}}
                        @if ($room->description)
                            <p class="text-muted small mb-2" style="height: 36px; overflow: hidden;">
                                {{ Str::limit($room->description, 60) }}
                            </p>
                        @else
                            <div class="mb-2" style="height: 36px;"></div>
                        @endif

                        {{-- 채팅방 정보 --}}
                        <div class="text-muted small mb-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <span>
                                    <i class="fas fa-crown me-1"></i>
                                    {{ Str::limit(optional($room->owner)->name ?? '알 수 없음', 15) }}
                                </span>
                                <span>
                                    {{ $room->last_activity_at ? $room->last_activity_at->diffForHumans() : $room->created_at->diffForHumans() }}
                                </span>
                            </div>
                        </div>

                        {{-- 참여자 미리보기 --}}
                        @if ($room->activeParticipants->count() > 0)
                            <div class="d-flex align-items-center justify-content-center mb-3">
                                <div class="d-flex" style="gap: -8px;">
                                    @foreach ($room->activeParticipants->take(4) as $participant)
                                        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center text-white border border-white participant-avatar"
                                            style="width: 24px; height: 24px; font-size: 10px; margin-left: -4px;">
                                            {{ substr($participant->user_name ?? 'U', 0, 1) }}
                                        </div>
                                    @endforeach
                                    @if ($room->activeParticipants->count() > 4)
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center text-muted border border-white"
                                            style="width: 24px; height: 24px; font-size: 10px; margin-left: -4px;">
                                            +{{ $room->activeParticipants->count() - 4 }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- 액션 버튼 --}}
                    <div class="card-footer border-0 p-3">
                        @php
                            $isParticipant = $room->activeParticipants->where('user_uuid', $user->uuid)->first();
                            $canJoin = $room->canJoin($user->uuid);
                        @endphp

                        <div class="room-actions">
                            {{-- 방 관리 드롭다운 (방장만) --}}
                            @if ($room->owner_uuid === $user->uuid)
                                <div class="dropdown">
                                    <button class="btn btn-gradient-secondary btn-sm dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                        <span style="color: white; font-weight: bold; font-size: 14px;">⋮</span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-start" style="z-index: 9999;">
                                        <li>
                                            <a class="dropdown-item"
                                                href="{{ route('home.chat.rooms.edit', $room->id) }}">
                                                <i class="fas fa-edit me-2"></i>설정 변경
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="#"
                                                onclick="copyInviteLink('{{ $room->invite_code }}')">
                                                <i class="fas fa-link me-2"></i>초대 링크
                                            </a>
                                        </li>
                                        <li>
                                            <hr class="dropdown-divider">
                                        </li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="#"
                                                onclick="deleteRoom({{ $room->id }})">
                                                <i class="fas fa-trash me-2"></i>방 삭제
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            @else
                                <div></div> {{-- 빈 공간 유지 --}}
                            @endif

                            {{-- 메인 액션 버튼 --}}
                            @if ($isParticipant)
                                <a href="{{ route('home.chat.room', $room->id) }}"
                                    class="btn btn-gradient-primary btn-sm fw-semibold flex-fill">
                                    <i class="fas fa-sign-in-alt me-1"></i>
                                    입장하기
                                </a>
                            @elseif($canJoin)
                                @if ($room->password)
                                    <button onclick="openPasswordModal({{ $room->id }}, '{{ $room->title }}')"
                                        class="btn btn-gradient-primary btn-sm fw-semibold flex-fill">
                                        <i class="fas fa-users me-1"></i>
                                        참여하기
                                    </button>
                                @else
                                    <form action="{{ route('home.chat.rooms.join', $room->id) }}" method="POST"
                                        class="flex-fill">
                                        @csrf
                                        <button type="submit"
                                            class="btn btn-gradient-primary btn-sm fw-semibold w-100">
                                            <i class="fas fa-users me-1"></i>
                                            참여하기
                                        </button>
                                    </form>
                                @endif
                            @else
                                <button class="btn btn-outline-secondary btn-sm flex-fill" disabled>
                                    <i class="fas fa-ban me-1"></i>
                                    참여 불가
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- 번호 방식 페이지네이션 --}}
    @if ($rooms->hasPages())
        <div class="mt-4">
            <nav aria-label="채팅방 목록 페이지네이션">
                <ul class="pagination justify-content-center">
                    {{-- 이전 페이지 버튼 --}}
                    @if ($rooms->onFirstPage())
                        <li class="page-item disabled">
                            <span class="page-link">
                                <i class="fas fa-chevron-left"></i>
                            </span>
                        </li>
                    @else
                        <li class="page-item">
                            <a class="page-link" href="{{ $rooms->appends(request()->query())->previousPageUrl() }}">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                    @endif

                    {{-- 페이지 번호 --}}
                    @php
                        $start = max(1, $rooms->currentPage() - 2);
                        $end = min($rooms->lastPage(), $rooms->currentPage() + 2);
                    @endphp

                    {{-- 첫 페이지 --}}
                    @if ($start > 1)
                        <li class="page-item">
                            <a class="page-link" href="{{ $rooms->appends(request()->query())->url(1) }}">1</a>
                        </li>
                        @if ($start > 2)
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        @endif
                    @endif

                    {{-- 현재 페이지 주변 번호들 --}}
                    @for ($i = $start; $i <= $end; $i++)
                        @if ($i == $rooms->currentPage())
                            <li class="page-item active">
                                <span class="page-link">{{ $i }}</span>
                            </li>
                        @else
                            <li class="page-item">
                                <a class="page-link"
                                    href="{{ $rooms->appends(request()->query())->url($i) }}">{{ $i }}</a>
                            </li>
                        @endif
                    @endfor

                    {{-- 마지막 페이지 --}}
                    @if ($end < $rooms->lastPage())
                        @if ($end < $rooms->lastPage() - 1)
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        @endif
                        <li class="page-item">
                            <a class="page-link"
                                href="{{ $rooms->appends(request()->query())->url($rooms->lastPage()) }}">{{ $rooms->lastPage() }}</a>
                        </li>
                    @endif

                    {{-- 다음 페이지 버튼 --}}
                    @if ($rooms->hasMorePages())
                        <li class="page-item">
                            <a class="page-link" href="{{ $rooms->appends(request()->query())->nextPageUrl() }}">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    @else
                        <li class="page-item disabled">
                            <span class="page-link">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        </li>
                    @endif
                </ul>
            </nav>

            {{-- 페이지 정보 --}}
            <div class="text-center mt-3">
                <small class="text-muted">
                    {{ $rooms->firstItem() }}~{{ $rooms->lastItem() }}개 (총 {{ $rooms->total() }}개)
                </small>
            </div>
        </div>
    @endif
@else
    {{-- 빈 상태 --}}
    <div class="text-center py-5">
        <div class="text-muted mb-4">
            <i class="fas fa-comments" style="font-size: 48px;"></i>
        </div>
        <h3 class="h5 fw-semibold text-dark mb-2">채팅방을 찾을 수 없습니다</h3>
        <p class="text-muted mb-4">
            @if (request('search'))
                검색 조건에 맞는 채팅방이 없습니다. 다른 키워드로 검색해보세요.
            @else
                아직 채팅방이 없습니다. 새로운 채팅방을 만들어보세요.
            @endif
        </p>
        <div class="mt-4">
            <a href="{{ route('home.chat.rooms.create') }}" class="btn btn-primary">
                새 채팅방 만들기
            </a>
        </div>
    </div>
@endif
