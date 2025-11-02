{{-- 채팅방 목록 페이지 --}}
@extends('jiny-site::layouts.home')

@push('styles')
    <style>
        .chat-room-card {
            border: 1px solid #e9ecef;
        }

        .participant-avatar {
            transition: transform 0.2s;
        }

        .participant-avatar:hover {
            transform: scale(1.1);
        }

        .pagination .page-link {
            border-radius: 6px;
            margin: 0 2px;
            border: 1px solid #dee2e6;
        }

        .pagination .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .btn-gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-gradient-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-2px);
            color: white;
        }

        .btn-gradient-secondary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-gradient-secondary:hover {
            background: linear-gradient(135deg, #ed7de9 0%, #f34560 100%);
            transform: translateY(-2px);
            color: white;
        }

        .dropdown-toggle::after {
            display: none;
        }

        .room-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: space-between;
        }

        .dropdown-menu {
            z-index: 9999 !important;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
        }

        .dropdown {
            position: relative;
            z-index: 1000;
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
                            <input type="text" name="search" id="search" value="{{ request('search') }}"
                                placeholder="채팅방 제목 또는 설명 검색..." class="form-control">
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
        <div class="card">
            <div class="card-body">
                @includeIf("jiny-chat::home.room.grid")
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
                            <input type="password" name="password" id="passwordInput" required class="form-control">
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
        document.getElementById('passwordModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('passwordInput').value = '';
        });

        // 초대 링크 복사
        function copyInviteLink(inviteCode) {
            const inviteUrl = window.location.origin + '/chat/invite/' + inviteCode;
            navigator.clipboard.writeText(inviteUrl).then(function() {
                // 성공 알림
                const toast = document.createElement('div');
                toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed';
                toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-check me-2"></i>초대 링크가 클립보드에 복사되었습니다!
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                `;
                document.body.appendChild(toast);
                const bsToast = new bootstrap.Toast(toast);
                bsToast.show();
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 3000);
            }).catch(function() {
                alert('클립보드 복사에 실패했습니다. 수동으로 복사해주세요: ' + inviteUrl);
            });
        }

        // 방 삭제 확인
        function deleteRoom(roomId) {
            if (confirm('정말로 이 채팅방을 삭제하시겠습니까?\n삭제된 방과 모든 메시지는 복구할 수 없습니다.')) {
                // 삭제 요청 전송
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/chat/rooms/' + roomId;

                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                const methodField = document.createElement('input');
                methodField.type = 'hidden';
                methodField.name = '_method';
                methodField.value = 'DELETE';

                form.appendChild(csrfToken);
                form.appendChild(methodField);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
@endsection
