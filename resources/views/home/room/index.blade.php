{{-- 채팅방 목록 페이지 --}}
@extends('jiny-site::layouts.home')

@section('content')

    <div class="container-fluid py-5">

        {{-- 헤더 --}}
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-2">
                            <i class="fas fa-door-open text-primary"></i>
                            채팅방 목록
                        </h2>
                        <p class="text-muted mb-0">참여 가능한 채팅방을 찾아보세요</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('home.chat.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> 대시보드로
                        </a>
                        <a href="{{ route('home.chat.rooms.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> 새 채팅방 만들기
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- 필터 및 검색 --}}
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('home.chat.rooms.index') }}">
                        <div class="row g-3">
                            {{-- 검색 --}}
                            <div class="col-md-6">
                                <label for="search" class="form-label fw-semibold">검색</label>
                                <input type="text"
                                       name="search"
                                       id="search"
                                       value="{{ request('search') }}"
                                       placeholder="채팅방 제목 또는 설명 검색..."
                                       class="form-control">
                            </div>

                            {{-- 타입 필터 --}}
                            <div class="col-md-3">
                                <label for="type" class="form-label fw-semibold">유형</label>
                                <select name="type" id="type" class="form-select">
                                    <option value="all" {{ request('type') === 'all' ? 'selected' : '' }}>전체</option>
                                    <option value="public" {{ request('type') === 'public' ? 'selected' : '' }}>공개방</option>
                                    <option value="joined" {{ request('type') === 'joined' ? 'selected' : '' }}>참여 중</option>
                                    <option value="owned" {{ request('type') === 'owned' ? 'selected' : '' }}>내가 만든 방</option>
                                </select>
                            </div>

                            {{-- 검색 버튼 --}}
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    검색
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- 채팅방 목록 --}}
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    @if($rooms->count() > 0)
                        <div class="row g-4">
                            @foreach($rooms as $room)
                                <div class="col-lg-6">
                                    <div class="card border h-100">
                                        <div class="card-body p-4">
                                            {{-- 채팅방 헤더 --}}
                                            <div class="d-flex align-items-start justify-content-between mb-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                                        <span class="text-white fw-semibold fs-5">
                                                            {{ substr($room->title, 0, 1) }}
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h3 class="fs-5 fw-semibold text-dark mb-1">{{ $room->title }}</h3>
                                                        <div class="d-flex align-items-center gap-2">
                                                            {{-- 방 타입 --}}
                                                            @if($room->is_public)
                                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                                                                    공개
                                                                </span>
                                                            @else
                                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">
                                                                    비공개
                                                                </span>
                                                            @endif

                                                            {{-- 비밀번호 여부 --}}
                                                            @if($room->password)
                                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill">
                                                                    🔒 비밀번호
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- 참여자 수 --}}
                                                <div class="text-end">
                                                    <div class="text-muted small">
                                                        {{ $room->activeParticipants->count() }}
                                                        @if($room->max_participants)
                                                            / {{ $room->max_participants }}
                                                        @endif
                                                        명
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- 설명 --}}
                                            @if($room->description)
                                                <p class="text-muted small mb-3">{{ Str::limit($room->description, 150) }}</p>
                                            @endif

                                            {{-- 채팅방 정보 --}}
                                            <div class="d-flex align-items-center justify-content-between text-muted small mb-3">
                                                <div>
                                                    방장: {{ optional($room->owner)->name ?? '알 수 없음' }}
                                                </div>
                                                <div>
                                                    {{ $room->last_activity_at ? $room->last_activity_at->diffForHumans() : $room->created_at->diffForHumans() }}
                                                </div>
                                            </div>

                                            {{-- 참여자 미리보기 --}}
                                            @if($room->activeParticipants->count() > 0)
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="d-flex" style="gap: -8px;">
                                                        @foreach($room->activeParticipants->take(5) as $participant)
                                                            <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center text-white border border-white" style="width: 32px; height: 32px; font-size: 12px; margin-left: -8px;">
                                                                {{ substr($participant->user_name ?? 'U', 0, 1) }}
                                                            </div>
                                                        @endforeach
                                                        @if($room->activeParticipants->count() > 5)
                                                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center text-muted border border-white" style="width: 32px; height: 32px; font-size: 12px; margin-left: -8px;">
                                                                +{{ $room->activeParticipants->count() - 5 }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif

                                            {{-- 액션 버튼 --}}
                                            <div class="d-flex justify-content-between align-items-center">
                                                @php
                                                    $isParticipant = $room->activeParticipants->where('user_uuid', $user->uuid)->first();
                                                    $canJoin = $room->canJoin($user->uuid);
                                                @endphp

                                                @if($isParticipant)
                                                    <a href="{{ route('home.chat.room', $room->id) }}"
                                                       class="btn btn-success btn-sm fw-semibold">
                                                        입장하기
                                                    </a>
                                                @elseif($canJoin)
                                                    @if($room->password)
                                                        <button onclick="openPasswordModal({{ $room->id }}, '{{ $room->title }}')"
                                                                class="btn btn-primary btn-sm fw-semibold">
                                                            참여하기
                                                        </button>
                                                    @else
                                                        <form action="{{ route('home.chat.rooms.join', $room->id) }}" method="POST" class="d-inline">
                                                            @csrf
                                                            <button type="submit"
                                                                    class="btn btn-primary btn-sm fw-semibold">
                                                                참여하기
                                                            </button>
                                                        </form>
                                                    @endif
                                                @else
                                                    <span class="text-muted small">참여 불가</span>
                                                @endif

                                                {{-- 방 설정 (방장만) --}}
                                                @if($room->owner_uuid === $user->uuid)
                                                    <button class="btn btn-outline-secondary btn-sm">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- 페이지네이션 --}}
                        <div class="mt-4">
                            {{ $rooms->appends(request()->query())->links() }}
                        </div>
                    @else
                        {{-- 빈 상태 --}}
                        <div class="text-center py-5">
                            <div class="text-muted mb-4">
                                <i class="fas fa-comments" style="font-size: 48px;"></i>
                            </div>
                            <h3 class="h5 fw-semibold text-dark mb-2">채팅방을 찾을 수 없습니다</h3>
                            <p class="text-muted mb-4">
                                @if(request('search'))
                                    검색 조건에 맞는 채팅방이 없습니다. 다른 키워드로 검색해보세요.
                                @else
                                    아직 채팅방이 없습니다. 새로운 채팅방을 만들어보세요.
                                @endif
                            </p>
                            <div class="mt-4">
                                <a href="{{ route('home.chat.rooms.create') }}"
                                   class="btn btn-primary">
                                    새 채팅방 만들기
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

    </div>

    {{-- 비밀번호 입력 모달 --}}
    <div class="modal fade" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">채팅방 비밀번호 입력</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="passwordForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="passwordInput" class="form-label">비밀번호</label>
                            <input type="password"
                                   name="password"
                                   id="passwordInput"
                                   required
                                   class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            취소
                        </button>
                        <button type="submit" class="btn btn-primary">
                            참여하기
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openPasswordModal(roomId, roomTitle) {
            const modal = new bootstrap.Modal(document.getElementById('passwordModal'));
            document.getElementById('modalTitle').textContent = roomTitle + ' - 비밀번호 입력';
            document.getElementById('passwordForm').action = '/chat/rooms/' + roomId + '/join';
            modal.show();
            // 모달이 열린 후 입력 포커스
            setTimeout(() => {
                document.getElementById('passwordInput').focus();
            }, 500);
        }

        // 모달이 닫힐 때 입력값 초기화
        document.getElementById('passwordModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('passwordInput').value = '';
        });
    </script>
@endsection
