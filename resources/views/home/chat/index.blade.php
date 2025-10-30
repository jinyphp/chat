{{-- 채팅 대시보드 메인 페이지 --}}
@extends('jiny-site::layouts.home')

@push('styles')
<style>
    /* 채팅방 카드 스타일 */
    .chat-room-card {
        transition: all 0.3s ease;
        border-radius: 12px !important;
        background: #ffffff;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08) !important;
        overflow: hidden;
    }

    .chat-room-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15) !important;
    }

    /* 카드 이미지 영역 */
    .chat-room-card .card-img-top {
        border-radius: 0;
        transition: transform 0.3s ease;
    }

    .chat-room-card:hover .card-img-top {
        transform: scale(1.05);
    }

    /* 메뉴 버튼 스타일 */
    .chat-room-card .btn-light {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(8px);
        border: none;
        transition: all 0.2s ease;
    }

    .chat-room-card .btn-light:hover {
        background: rgba(255, 255, 255, 1);
        transform: scale(1.1);
    }

    /* 아바타 스타일링 */
    .chat-room-card .rounded-circle {
        transition: transform 0.2s ease;
    }

    .chat-room-card .rounded-circle:hover {
        transform: scale(1.15);
    }

    /* 배지 스타일 */
    .chat-room-card .badge {
        font-weight: 500;
        font-size: 0.75rem;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
    }

    /* 버튼 공통 스타일 */
    .chat-room-card .btn {
        border-radius: 10px;
        padding: 12px 24px;
        font-weight: 600;
        font-size: 0.9rem;
        letter-spacing: 0.03em;
        transition: all 0.3s ease;
        border: none;
    }

    /* 입장 버튼 스타일 */
    .chat-room-card .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
    }

    .chat-room-card .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
    }

    /* 관리 버튼 스타일 */
    .chat-room-card .btn-secondary {
        background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        box-shadow: 0 4px 15px rgba(108, 117, 125, 0.2);
    }

    .chat-room-card .btn-secondary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
        background: linear-gradient(135deg, #5a6268 0%, #343a40 100%);
    }

    /* 드롭다운 메뉴 스타일 */
    .chat-room-card .dropdown-menu {
        border: none;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        border-radius: 12px;
        padding: 12px 0;
        min-width: 160px;
    }

    .chat-room-card .dropdown-item {
        padding: 10px 20px;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.2s ease;
        border: none;
    }

    .chat-room-card .dropdown-item:hover {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        color: #495057;
        transform: translateX(5px);
    }

    .chat-room-card .dropdown-item.text-danger:hover {
        background: linear-gradient(135deg, #ffe6e6 0%, #ffcccc 100%);
        color: #dc3545;
    }

    /* 텍스트 스타일 개선 */
    .chat-room-card .card-title {
        color: #2d3748;
        font-size: 1.2rem;
        line-height: 1.3;
        font-weight: 700;
    }

    .chat-room-card .text-muted {
        color: #6c757d !important;
    }

    .chat-room-card .text-dark {
        color: #2d3748 !important;
    }

    /* 카드 내부 간격 */
    .chat-room-card .card-body {
        padding: 1.5rem !important;
    }

    .chat-room-card .card-footer {
        background: transparent !important;
        border: none !important;
        padding: 0 1.5rem 1.5rem 1.5rem !important;
    }

    /* 그라데이션 배경 */
    .bg-gradient-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    }

    /* 페이지네이션 스타일 */
    .pagination .page-link {
        border-radius: 8px;
        margin: 0 3px;
        border: 1px solid #dee2e6;
        color: #495057;
        transition: all 0.2s ease;
        padding: 8px 12px;
    }

    .pagination .page-link:hover {
        background-color: #e9ecef;
        border-color: #adb5bd;
        transform: translateY(-1px);
    }

    .pagination .page-item.active .page-link {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: #667eea;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    /* 반응형 조정 */
    @media (max-width: 768px) {
        .chat-room-card .card-body {
            padding: 1rem !important;
        }

        .chat-room-card .card-footer {
            padding: 0 1rem 1rem 1rem !important;
        }
    }
</style>
@endpush

@section('content')

    <div class="container-fluid py-5">

        {{-- 헤더 --}}
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-2">
                            <i class="fas fa-comments text-primary"></i>
                            채팅 대시보드
                        </h2>
                        <p class="text-muted mb-0">참여 중인 채팅방과 최근 활동을 확인하세요</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('home.chat.rooms.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-list"></i> 모든 채팅방
                        </a>
                        <a href="{{ route('home.chat.rooms.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> 새 채팅방 만들기
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- 상태 요약 --}}
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="p-2 bg-primary bg-opacity-10 rounded-circle me-3">
                                    <i class="fas fa-comments text-primary" style="font-size: 1.5rem;"></i>
                                </div>
                                <div>
                                    <p class="text-muted mb-0 small">참여 중인 채팅방</p>
                                    <h4 class="mb-0 fw-bold">{{ $participatingRooms->total() }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="p-2 bg-danger bg-opacity-10 rounded-circle me-3">
                                    <i class="fas fa-envelope text-danger" style="font-size: 1.5rem;"></i>
                                </div>
                                <div>
                                    <p class="text-muted mb-0 small">읽지 않은 메시지</p>
                                    <h4 class="mb-0 fw-bold">{{ array_sum($unreadCounts) }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="p-2 bg-success bg-opacity-10 rounded-circle me-3">
                                    <i class="fas fa-users text-success" style="font-size: 1.5rem;"></i>
                                </div>
                                <div>
                                    <p class="text-muted mb-0 small">온라인 참가자</p>
                                    <h4 class="mb-0 fw-bold">{{ $participatingRooms->sum(function($room) { return $room->activeParticipants->count(); }) }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 최근 채팅방 목록 --}}
            @if($participatingRooms->count() > 0)
                        <div class="row g-4">
                            @foreach($participatingRooms as $room)
                                <div class="col-xl-3 col-lg-4 col-md-6">
                                    <div class="card border-0 shadow-sm h-100 chat-room-card">
                                        {{-- 대표 이미지 --}}
                                        <div class="position-relative">
                                            @if($room->image)
                                                <img src="{{ asset('storage/' . $room->image) }}"
                                                     alt="{{ $room->title }}"
                                                     class="card-img-top"
                                                     style="height: 180px; object-fit: cover;">
                                            @else
                                                <div class="d-flex align-items-center justify-content-center bg-gradient-primary text-white"
                                                     style="height: 180px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                                    <i class="fas fa-comments" style="font-size: 3rem; opacity: 0.8;"></i>
                                                </div>
                                            @endif


                                            {{-- 읽지 않은 메시지 배지 --}}
                                            @if(isset($unreadCounts[$room->id]) && $unreadCounts[$room->id] > 0)
                                                <div class="position-absolute top-0 start-0 p-3">
                                                    <span class="badge bg-danger rounded-pill">
                                                        {{ $unreadCounts[$room->id] }}
                                                    </span>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="card-body p-4">
                                            {{-- 타이틀 --}}
                                            <h5 class="card-title fw-bold mb-2 text-dark">
                                                {{ Str::limit($room->title, 30) }}
                                            </h5>

                                            {{-- 방 타입 배지 --}}
                                            <div class="mb-3">
                                                @if($room->is_public)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                        <i class="fas fa-globe me-1"></i>공개방
                                                    </span>
                                                @else
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                                        <i class="fas fa-lock me-1"></i>비공개방
                                                    </span>
                                                @endif

                                                @if($room->is_owner)
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1">
                                                        <i class="fas fa-crown me-1"></i>방장
                                                    </span>
                                                @endif
                                            </div>

                                            {{-- 설명 --}}
                                            <p class="text-muted mb-3" style="height: 45px; overflow: hidden; line-height: 1.5;">
                                                @if($room->description)
                                                    {{ Str::limit($room->description, 80) }}
                                                @elseif($room->latestMessage)
                                                    "{{ Str::limit($room->latestMessage->content, 70) }}"
                                                @else
                                                    새로운 대화를 시작해보세요!
                                                @endif
                                            </p>

                                            {{-- 참여회원수 --}}
                                            <div class="d-flex align-items-center justify-content-between mb-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="d-flex me-2" style="margin-left: -8px;">
                                                        @foreach($room->activeParticipants->take(4) as $participant)
                                                            <div class="rounded-circle border border-2 border-white bg-secondary d-flex align-items-center justify-content-center text-white fw-bold shadow-sm"
                                                                 style="width: 28px; height: 28px; margin-left: -8px; font-size: 11px; z-index: {{ 4 - $loop->index }};">
                                                                {{ substr($participant->user_name ?? 'U', 0, 1) }}
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <span class="text-dark fw-semibold">
                                                        {{ $room->activeParticipants->count() }}명 참여
                                                        @if($room->activeParticipants->count() > 4)
                                                            <span class="text-primary">+</span>
                                                        @endif
                                                    </span>
                                                </div>

                                                {{-- 최근 활동 시간 --}}
                                                <small class="text-muted">
                                                    @if($room->latestMessage)
                                                        {{ $room->latestMessage->created_at->diffForHumans() }}
                                                    @else
                                                        {{ $room->created_at->diffForHumans() }}
                                                    @endif
                                                </small>
                                            </div>
                                        </div>

                                        {{-- 액션 버튼 --}}
                                        <div class="card-footer bg-transparent border-0 p-4 pt-0">
                                            @if($room->is_owner)
                                                {{-- 방장용: 관리 + 입장 버튼 --}}
                                                <div class="d-flex gap-2">
                                                    <div class="dropdown">
                                                        <button class="btn btn-secondary dropdown-toggle"
                                                                data-bs-toggle="dropdown"
                                                                style="min-width: 80px;">
                                                            <i class="fas fa-cog me-1"></i> 관리
                                                        </button>
                                                        <ul class="dropdown-menu shadow border-0">
                                                            <li>
                                                                <a class="dropdown-item" href="{{ route('home.chat.rooms.edit', $room->id) }}">
                                                                    <i class="fas fa-edit me-2 text-primary"></i> 변경
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="#" onclick="generateInviteLink({{ $room->id }})">
                                                                    <i class="fas fa-link me-2 text-info"></i> 초대링크
                                                                </a>
                                                            </li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <a class="dropdown-item text-danger" href="#" onclick="deleteRoom({{ $room->id }})">
                                                                    <i class="fas fa-trash me-2"></i> 삭제
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                    <a href="{{ route('home.chat.room', $room->id) }}"
                                                       class="btn btn-primary flex-fill fw-semibold">
                                                        <i class="fas fa-sign-in-alt me-2"></i>입장하기
                                                    </a>
                                                </div>
                                            @else
                                                {{-- 일반 참여자용: 입장 버튼만 --}}
                                                <a href="{{ route('home.chat.room', $room->id) }}"
                                                   class="btn btn-primary w-100 fw-semibold">
                                                    <i class="fas fa-sign-in-alt me-2"></i>입장하기
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- 번호 방식 페이지네이션 --}}
                        @if($participatingRooms->hasPages())
                            <div class="mt-4">
                                <nav aria-label="채팅방 목록 페이지네이션">
                                    <ul class="pagination justify-content-center">
                                        {{-- 이전 페이지 버튼 --}}
                                        @if($participatingRooms->onFirstPage())
                                            <li class="page-item disabled">
                                                <span class="page-link">
                                                    <i class="fas fa-chevron-left"></i>
                                                </span>
                                            </li>
                                        @else
                                            <li class="page-item">
                                                <a class="page-link" href="{{ $participatingRooms->previousPageUrl() }}">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            </li>
                                        @endif

                                        {{-- 페이지 번호 --}}
                                        @php
                                            $start = max(1, $participatingRooms->currentPage() - 2);
                                            $end = min($participatingRooms->lastPage(), $participatingRooms->currentPage() + 2);
                                        @endphp

                                        {{-- 첫 페이지 --}}
                                        @if($start > 1)
                                            <li class="page-item">
                                                <a class="page-link" href="{{ $participatingRooms->url(1) }}">1</a>
                                            </li>
                                            @if($start > 2)
                                                <li class="page-item disabled">
                                                    <span class="page-link">...</span>
                                                </li>
                                            @endif
                                        @endif

                                        {{-- 현재 페이지 주변 번호들 --}}
                                        @for($i = $start; $i <= $end; $i++)
                                            @if($i == $participatingRooms->currentPage())
                                                <li class="page-item active">
                                                    <span class="page-link">{{ $i }}</span>
                                                </li>
                                            @else
                                                <li class="page-item">
                                                    <a class="page-link" href="{{ $participatingRooms->url($i) }}">{{ $i }}</a>
                                                </li>
                                            @endif
                                        @endfor

                                        {{-- 마지막 페이지 --}}
                                        @if($end < $participatingRooms->lastPage())
                                            @if($end < $participatingRooms->lastPage() - 1)
                                                <li class="page-item disabled">
                                                    <span class="page-link">...</span>
                                                </li>
                                            @endif
                                            <li class="page-item">
                                                <a class="page-link" href="{{ $participatingRooms->url($participatingRooms->lastPage()) }}">{{ $participatingRooms->lastPage() }}</a>
                                            </li>
                                        @endif

                                        {{-- 다음 페이지 버튼 --}}
                                        @if($participatingRooms->hasMorePages())
                                            <li class="page-item">
                                                <a class="page-link" href="{{ $participatingRooms->nextPageUrl() }}">
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
                                        {{ $participatingRooms->firstItem() }}~{{ $participatingRooms->lastItem() }}개 (총 {{ $participatingRooms->total() }}개)
                                    </small>
                                </div>
                            </div>
                        @endif
                    @else
                        {{-- 빈 상태 --}}
                        <div class="text-center py-5">
                            <i class="fas fa-comments text-muted mb-3" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mb-2">채팅방이 없습니다</h5>
                            <p class="text-muted mb-4">새로운 채팅방을 만들거나 기존 채팅방에 참여해보세요.</p>
                            <a href="{{ route('home.chat.rooms.create') }}"
                               class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>새 채팅방 만들기
                            </a>
                        </div>
                    @endif


    </div>


    {{-- 초대 링크 모달 --}}
    <div class="modal fade" id="inviteLinkModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-link text-primary"></i> 초대 링크
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">초대 링크</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="inviteLinkInput" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyInviteLink()">
                                <i class="fas fa-copy"></i> 복사
                            </button>
                        </div>
                        <small class="text-muted">이 링크를 공유하여 다른 사용자를 채팅방에 초대할 수 있습니다.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">확인</button>
                </div>
            </div>
        </div>
    </div>

    {{-- 스크립트 --}}
    <script>

        // 초대 링크 생성
        function generateInviteLink(roomId) {
            fetch(`/api/chat/rooms/${roomId}/invite`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('inviteLinkInput').value = data.invite_link;
                    const modal = new bootstrap.Modal(document.getElementById('inviteLinkModal'));
                    modal.show();
                } else {
                    alert('초대 링크 생성에 실패했습니다.');
                }
            })
            .catch(error => {
                console.error('Error generating invite link:', error);
                alert('초대 링크 생성 중 오류가 발생했습니다.');
            });
        }

        // 초대 링크 복사
        function copyInviteLink() {
            const linkInput = document.getElementById('inviteLinkInput');
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

        // 방 삭제
        function deleteRoom(roomId) {
            if (confirm('정말로 이 채팅방을 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.')) {
                fetch(`/home/chat/room/${roomId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('채팅방이 삭제되었습니다.', 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        alert('채팅방 삭제에 실패했습니다.');
                    }
                })
                .catch(error => {
                    console.error('Error deleting room:', error);
                    alert('채팅방 삭제 중 오류가 발생했습니다.');
                });
            }
        }

        // 토스트 알림 표시
        function showToast(message, type = 'info') {
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

        // 페이지가 로드될 때 실시간 알림 설정
        document.addEventListener('DOMContentLoaded', function() {
            // WebSocket 연결 또는 polling 설정
            // TODO: 실시간 알림 구현
        });
    </script>
@endsection
