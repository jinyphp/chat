{{-- 채팅 대시보드 --}}
@extends('jiny-site::layouts.home')

@push('styles')
<style>
.chat-card {
    transition: transform 0.2s;
    border-radius: 8px;
}
.chat-card:hover {
    transform: translateY(-2px);
}
.chat-image {
    height: 120px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.participant-avatar {
    width: 24px;
    height: 24px;
    margin-left: -6px;
    font-size: 10px;
    z-index: 10;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
}
</style>
@endpush

@section('content')
<div class="container py-4">
    {{-- 헤더 --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">
                <i class="fas fa-comments text-primary"></i>
                채팅 대시보드
            </h2>
            <p class="text-muted mb-0">참여 중인 채팅방 목록</p>
        </div>
        <div>
            <a href="{{ route('home.chat.rooms.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>새 채팅방 만들기
            </a>
        </div>
    </div>

    {{-- 채팅방 목록 --}}
    @if($participatingRooms->count() > 0)
        <div class="row g-3">
            @foreach($participatingRooms as $room)
                <div class="col-md-6 col-lg-4">
                    <div class="card chat-card h-100">
                        {{-- 대표 이미지 --}}
                        <div class="chat-image d-flex align-items-center justify-content-center text-white position-relative">
                            @if($room->image)
                                <img src="{{ asset('storage/' . $room->image) }}"
                                     alt="{{ $room->title }}"
                                     class="w-100 h-100" style="object-fit: cover;">
                            @else
                                <i class="fas fa-comments" style="font-size: 2.5rem; opacity: 0.8;"></i>
                            @endif

                            {{-- 읽지 않은 메시지 배지 --}}
                            @if($room->unread_count > 0)
                                <span class="badge bg-danger position-absolute top-0 end-0 m-2">
                                    {{ $room->unread_count }}
                                </span>
                            @endif
                        </div>

                        <div class="card-body">
                            {{-- 제목 --}}
                            <h6 class="card-title mb-2">{{ Str::limit($room->title, 25) }}</h6>

                            {{-- 배지 --}}
                            <div class="mb-3">
                                @if($room->is_public)
                                    <span class="badge bg-success-subtle text-success">
                                        <i class="fas fa-globe me-1"></i>공개방
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">
                                        <i class="fas fa-lock me-1"></i>비공개방
                                    </span>
                                @endif

                                @if($room->is_owner)
                                    <span class="badge bg-warning-subtle text-warning ms-1">
                                        <i class="fas fa-crown me-1"></i>방장
                                    </span>
                                @endif
                            </div>

                            {{-- 설명 --}}
                            <p class="text-muted small mb-3" style="height: 40px; overflow: hidden;">
                                {{ $room->description ?? '새로운 대화를 시작해보세요!' }}
                            </p>

                            {{-- 참여자 정보 --}}
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="d-flex me-2">
                                        @foreach($room->activeParticipants->take(3) as $participant)
                                            <div class="participant-avatar rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white fw-bold">
                                                @php
                                                    $name = $participant->name ?? 'U';
                                                    echo mb_substr(trim($name), 0, 1, 'UTF-8') ?: 'U';
                                                @endphp
                                            </div>
                                        @endforeach
                                    </div>
                                    <small class="text-muted">{{ $room->activeParticipants->count() }}명</small>
                                </div>
                                <small class="text-muted">
                                    {{ $room->created_at->diffForHumans() }}
                                </small>
                            </div>
                        </div>

                        {{-- 액션 버튼 --}}
                        <div class="card-footer bg-transparent border-0">
                            @if($room->is_owner)
                                {{-- 방장용: 관리 메뉴 + 입장 버튼 --}}
                                <div class="d-flex gap-2">
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle"
                                                data-bs-toggle="dropdown"
                                                style="min-width: 70px;">
                                            <i class="fas fa-cog me-1"></i>관리
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('home.chat.rooms.edit', $room->id) }}">
                                                    <i class="fas fa-edit me-2 text-primary"></i>수정
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#">
                                                    <i class="fas fa-users me-2 text-info"></i>참여자
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="#"
                                                   onclick="confirmDeleteRoom({{ $room->id }}, '{{ $room->title }}')">
                                                    <i class="fas fa-trash me-2"></i>삭제
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    <a href="{{ route('home.chat.room.show', $room->id) }}" class="btn btn-primary btn-sm flex-fill">
                                        <i class="fas fa-sign-in-alt me-1"></i>입장하기
                                    </a>
                                </div>
                            @else
                                {{-- 일반 참여자용: 입장 버튼만 --}}
                                <a href="{{ route('home.chat.room.show', $room->id) }}" class="btn btn-primary btn-sm w-100">
                                    <i class="fas fa-sign-in-alt me-1"></i>입장하기
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- 페이지네이션 --}}
        @if($participatingRooms->hasPages())
            <div class="mt-4 d-flex justify-content-center">
                {{ $participatingRooms->links() }}
            </div>
        @endif
    @else
        {{-- 빈 상태 --}}
        <div class="text-center py-5">
            <i class="fas fa-comments text-muted mb-3" style="font-size: 3rem;"></i>
            <h5 class="text-muted mb-2">참여 중인 채팅방이 없습니다</h5>
            <p class="text-muted">새로운 채팅방에 참여하거나 생성해보세요.</p>
        </div>
    @endif
</div>

{{-- 채팅방 삭제 확인 모달 --}}
<div class="modal fade" id="deleteRoomModal" tabindex="-1" aria-labelledby="deleteRoomModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteRoomModalLabel">
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                    채팅방 삭제 확인
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <strong>⚠️ 주의:</strong> 이 작업은 되돌릴 수 없습니다!
                </div>
                <p>정말로 "<span id="roomTitleToDelete" class="fw-bold text-danger"></span>" 채팅방을 삭제하시겠습니까?</p>
                <p class="text-muted small">삭제하면 다음 항목들이 모두 제거됩니다:</p>
                <ul class="small text-muted">
                    <li>채팅방 정보 및 설정</li>
                    <li>모든 참여자 목록</li>
                    <li>채팅 메시지 기록</li>
                    <li>첨부파일 및 이미지</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-danger" onclick="deleteRoom()">
                    <i class="fas fa-trash me-1"></i>삭제하기
                </button>
            </div>
        </div>
    </div>
</div>

{{-- 로딩 스피너 모달 --}}
<div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mb-0">채팅방을 삭제하고 있습니다...</p>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let roomToDelete = null;

// 삭제 확인 모달 표시
function confirmDeleteRoom(roomId, roomTitle) {
    roomToDelete = roomId;
    document.getElementById('roomTitleToDelete').textContent = roomTitle;

    const modal = new bootstrap.Modal(document.getElementById('deleteRoomModal'));
    modal.show();
}

// 실제 삭제 실행
async function deleteRoom() {
    if (!roomToDelete) return;

    // 확인 모달 닫기
    const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteRoomModal'));
    deleteModal.hide();

    // 로딩 모달 표시
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();

    try {
        const response = await fetch(`/home/chat/room/${roomToDelete}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        const result = await response.json();

        // 로딩 모달 닫기
        loadingModal.hide();

        if (result.success) {
            // 성공 알림
            showAlert('success', result.message);

            // 페이지 새로고침 (1초 후)
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showAlert('danger', result.message);
        }

    } catch (error) {
        // 로딩 모달 닫기
        loadingModal.hide();

        console.error('삭제 중 오류:', error);
        showAlert('danger', '채팅방 삭제 중 오류가 발생했습니다.');
    }

    roomToDelete = null;
}

// 알림 메시지 표시
function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    document.body.appendChild(alertDiv);

    // 5초 후 자동 제거
    setTimeout(() => {
        if (alertDiv.parentElement) {
            alertDiv.remove();
        }
    }, 5000);
}
</script>
@endpush
@endsection
