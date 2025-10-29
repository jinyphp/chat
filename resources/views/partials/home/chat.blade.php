<div class="d-flex flex-column gap-1">
    <span class="navbar-header">Chatting</span>
    <ul class="list-unstyled mb-0">

        <!-- 채팅 대시보드 -->
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('home.chat.index') ? 'active' : '' }}"
               href="{{ route('home.chat.index') }}">
                <i class="fas fa-tachometer-alt nav-icon"></i>
                대시보드
            </a>
        </li>

        <!-- 채팅방 관리 -->
        <li class="nav-item nav-collapse">
            <a class="nav-sub-link" data-bs-toggle="collapse" href="#collapseRooms"
               aria-expanded="{{ request()->routeIs('home.chat.rooms.*') ? 'true' : 'false' }}">
                <i class="fas fa-comments nav-icon"></i>
                채팅방
            </a>
            <div class="collapse {{ request()->routeIs('home.chat.rooms.*') ? 'show' : '' }}" id="collapseRooms">
                <ul class="list-unstyled py-2 px-4">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('home.chat.rooms.index') ? 'active' : '' }}"
                           href="{{ route('home.chat.rooms.index') }}">
                            <i class="fas fa-list nav-icon"></i>
                            채팅방 목록
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('home.chat.rooms.create') ? 'active' : '' }}"
                           href="{{ route('home.chat.rooms.create') }}">
                            <i class="fas fa-plus nav-icon"></i>
                            새 채팅방
                        </a>
                    </li>
                </ul>
            </div>
        </li>

        <!-- 내 참여 채팅방 -->
        <li class="nav-item">
            <a class="nav-link" href="#" onclick="loadMyRooms()">
                <i class="fas fa-user-friends nav-icon"></i>
                내 채팅방
                <span class="badge bg-primary ms-auto" id="myRoomsCount">0</span>
            </a>
        </li>

        <!-- 내가 만든 채팅방 (방장) -->
        <li class="nav-item">
            <a class="nav-link" href="#" onclick="loadMyOwnedRooms()">
                <i class="fas fa-crown nav-icon text-warning"></i>
                내가 만든 방
                <span class="badge bg-warning ms-auto" id="myOwnedRoomsCount">0</span>
            </a>
        </li>

        <!-- 즐겨찾기 메시지 -->
        <li class="nav-item">
            <a class="nav-link" href="#" onclick="loadFavoriteMessages()">
                <i class="fas fa-star nav-icon"></i>
                즐겨찾기
            </a>
        </li>

        <!-- 초대 링크 관리 -->
        <li class="nav-item">
            <a class="nav-link" href="#" onclick="showInviteManager()">
                <i class="fas fa-link nav-icon"></i>
                초대 링크
            </a>
        </li>

        <!-- 구분선 -->
        <li class="nav-divider"></li>

        <!-- 채팅 설정 -->
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('home.chat.settings') ? 'active' : '' }}"
               href="{{ route('home.chat.settings') }}">
                <i class="fas fa-cog nav-icon"></i>
                설정
            </a>
        </li>

        <!-- 온라인 사용자 -->
        <li class="nav-item">
            <a class="nav-link" href="#" onclick="showOnlineUsers()">
                <i class="fas fa-circle text-success nav-icon"></i>
                온라인 사용자
                <span class="badge bg-success ms-auto" id="onlineUsersCount">0</span>
            </a>
        </li>

        <!-- 도움말 -->
        <li class="nav-item nav-collapse">
            <a class="nav-sub-link" data-bs-toggle="collapse" href="#collapseHelp">
                <i class="fas fa-question-circle nav-icon"></i>
                도움말
            </a>
            <div class="collapse" id="collapseHelp">
                <ul class="list-unstyled py-2 px-4">
                    <li class="nav-item">
                        <a class="nav-link" href="/chat/guide">
                            <i class="fas fa-book nav-icon"></i>
                            사용 가이드
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/chat/features">
                            <i class="fas fa-list-alt nav-icon"></i>
                            기능 소개
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/chat/api-docs">
                            <i class="fas fa-code nav-icon"></i>
                            API 문서
                        </a>
                    </li>
                </ul>
            </div>
        </li>

    </ul>
</div>

<!-- 사이드바 JavaScript 기능 -->
<script>
// 내 참여 채팅방 로드
function loadMyRooms() {
    fetch('/api/chat/rooms?type=participant')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('myRoomsCount').textContent = data.rooms.length;
                // 모달로 채팅방 목록 표시
                showMyRoomsModal(data.rooms, '내 참여 채팅방');
            }
        })
        .catch(error => console.error('Error loading my rooms:', error));
}

// 내가 만든 채팅방 로드 (방장)
function loadMyOwnedRooms() {
    fetch('/api/chat/rooms?type=owner')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('myOwnedRoomsCount').textContent = data.rooms.length;
                // 모달로 내가 만든 채팅방 목록 표시 (관리 기능 포함)
                showMyOwnedRoomsModal(data.rooms);
            }
        })
        .catch(error => console.error('Error loading my owned rooms:', error));
}

// 즐겨찾기 메시지 로드
function loadFavoriteMessages() {
    // 즐겨찾기 메시지 페이지로 이동하거나 모달 표시
    window.location.href = '/home/chat?tab=favorites';
}

// 초대 링크 관리자 표시
function showInviteManager() {
    // 초대 링크 관리 모달 표시
    alert('초대 링크 관리 기능 (구현 예정)');
}

// 온라인 사용자 표시
function showOnlineUsers() {
    // 온라인 사용자 목록 모달 표시
    alert('온라인 사용자 목록 (구현 예정)');
}

// 내 참여 채팅방 모달 표시
function showMyRoomsModal(rooms, title = '내 참여 채팅방') {
    let roomsList = '';

    if (rooms.length === 0) {
        roomsList = '<div class="text-center text-muted py-4">참여 중인 채팅방이 없습니다.</div>';
    } else {
        roomsList = rooms.map(room => {
            const lastMessage = room.last_message ?
                `<small class="text-muted d-block">${room.last_message.substring(0, 30)}${room.last_message.length > 30 ? '...' : ''}</small>` :
                '<small class="text-muted d-block">메시지가 없습니다</small>';

            return `<div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-1">
                            <h6 class="mb-0 me-2">${room.title}</h6>
                            ${room.role === 'owner' ? '<span class="badge bg-warning text-dark"><i class="fas fa-crown"></i></span>' : ''}
                            ${room.role === 'admin' ? '<span class="badge bg-info">관리자</span>' : ''}
                        </div>
                        <small class="text-muted">${room.participants_count}명 참여</small>
                        ${lastMessage}
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <a href="/home/chat/room/${room.id}" class="btn btn-sm btn-primary">입장</a>
                        ${room.unread_count > 0 ? `<span class="badge bg-danger">${room.unread_count}</span>` : ''}
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    showModal('myRoomsModal', title, roomsList);
}

// 내가 만든 채팅방 모달 표시 (방장 전용 관리 기능)
function showMyOwnedRoomsModal(rooms) {
    let roomsList = '';

    if (rooms.length === 0) {
        roomsList = `
            <div class="text-center text-muted py-4">
                <i class="fas fa-comments fa-3x mb-3"></i>
                <div>아직 만든 채팅방이 없습니다.</div>
                <a href="/home/chat/rooms/create" class="btn btn-primary mt-2">
                    <i class="fas fa-plus me-1"></i> 새 채팅방 만들기
                </a>
            </div>`;
    } else {
        roomsList = rooms.map(room => {
            const statusBadge = room.status === 'active' ?
                '<span class="badge bg-success">활성</span>' :
                '<span class="badge bg-secondary">비활성</span>';

            return `<div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-1">
                            <h6 class="mb-0 me-2">${room.title}</h6>
                            <span class="badge bg-warning text-dark me-1">
                                <i class="fas fa-crown"></i> 방장
                            </span>
                            ${statusBadge}
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-users me-1"></i>${room.participants_count}명 참여
                            <i class="fas fa-calendar ms-2 me-1"></i>${new Date(room.created_at).toLocaleDateString()}
                        </small>
                        <div class="mt-1">
                            <small class="text-info">
                                <i class="fas fa-comment me-1"></i>${room.messages_count || 0}개 메시지
                            </small>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <a href="/home/chat/room/${room.id}" class="btn btn-sm btn-primary">입장</a>
                        <button class="btn btn-sm btn-outline-secondary" onclick="manageRoom(${room.id})">
                            <i class="fas fa-cog"></i> 관리
                        </button>
                        <button class="btn btn-sm btn-outline-info" onclick="generateInviteLink(${room.id})">
                            <i class="fas fa-link"></i> 초대
                        </button>
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    showModal('myOwnedRoomsModal', '내가 만든 채팅방', roomsList);
}

// 공통 모달 표시 함수
function showModal(modalId, title, content) {
    const modalHtml = `
        <div class="modal fade" id="${modalId}" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${title}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="list-group">
                            ${content}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    // 기존 모달 제거 후 새로 추가
    const existingModal = document.getElementById(modalId);
    if (existingModal) {
        existingModal.remove();
    }

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById(modalId));
    modal.show();
}

// 채팅방 관리 (방장 전용)
function manageRoom(roomId) {
    window.location.href = `/home/chat/room/${roomId}?tab=settings`;
}

// 초대 링크 생성 (방장 전용)
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
            // 초대 링크 복사 기능
            navigator.clipboard.writeText(data.invite_link);
            alert(`초대 링크가 클립보드에 복사되었습니다!\n${data.invite_link}`);
        } else {
            alert('초대 링크 생성에 실패했습니다.');
        }
    })
    .catch(error => {
        console.error('Error generating invite link:', error);
        alert('초대 링크 생성 중 오류가 발생했습니다.');
    });
}

// 페이지 로드 시 초기화
document.addEventListener('DOMContentLoaded', function() {
    // 초기 로드
    updateRoomCounts();

    // 내 채팅방 수 업데이트 (주기적)
    setInterval(updateRoomCounts, 30000); // 30초마다 업데이트

    // 온라인 사용자 수 업데이트 (시뮬레이션)
    setInterval(function() {
        const count = Math.floor(Math.random() * 10) + 1;
        document.getElementById('onlineUsersCount').textContent = count;
    }, 10000); // 10초마다 업데이트
});

// 채팅방 수 업데이트 함수
function updateRoomCounts() {
    // 내 참여 채팅방 수 업데이트
    fetch('/api/chat/rooms?type=participant&count_only=true')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('myRoomsCount').textContent = data.count || 0;
            }
        })
        .catch(error => console.error('Error updating participant rooms count:', error));

    // 내가 만든 채팅방 수 업데이트
    fetch('/api/chat/rooms?type=owner&count_only=true')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('myOwnedRoomsCount').textContent = data.count || 0;
            }
        })
        .catch(error => console.error('Error updating owned rooms count:', error));
}
</script>

<!-- 추가 CSS -->
<style>
.nav-divider {
    height: 1px;
    margin: 0.5rem 0;
    background-color: rgba(0, 0, 0, 0.1);
}

.nav-link.active {
    background-color: rgba(13, 110, 253, 0.1);
    color: #0d6efd !important;
    font-weight: 600;
}

.nav-link:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.badge {
    font-size: 0.7rem;
}

.navbar-header {
    font-size: 0.9rem;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}
</style>
