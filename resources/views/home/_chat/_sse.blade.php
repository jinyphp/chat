{{-- 채팅방 메인 인터페이스 --}}
@extends('jiny-chat::layouts.chat')

@push('styles')
    <!-- FontAwesome 아이콘 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
@endpush

@section('content')

    <div class="chat-container">
        <div class="row g-0">
            <div class="col-xl-3 col-lg-12 col-md-12 col-12">
                <div class="bg-white border-end border-top">
                    <!-- chat users -->
                    {{-- @livewire('jiny-chat::chat-participants', ['roomId' => $room->id]) --}}
                    <div id="chat-participants" class="p-3">
                        <h6 class="mb-3">참여자 ({{ $room->activeParticipants->count() }}명)</h6>
                        <div id="participants-list">
                            <!-- 참여자 목록이 여기에 로드됩니다 -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-9 col-lg-12 col-md-12 col-12">
                <!-- chat list -->
                <div class="chat-body d-flex flex-column w-100" style="height: 100vh;">
                    {{-- 메시지 헤더 --}}
                    <div class="flex-shrink-0 bg-white border-bottom p-3">
                        {{-- @livewire('jiny-chat::chat-header', ['roomId' => $room->id]) --}}
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <h5 class="mb-0 fw-bold">{{ $room->title }}</h5>
                                @if($room->description)
                                    <small class="text-muted ms-3">{{ $room->description }}</small>
                                @endif
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-success me-2">
                                    <i class="fas fa-users me-1"></i>
                                    {{ $room->activeParticipants->count() }}명 참여중
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- 메시지 영역 --}}
                    <div class="flex-grow-1 overflow-auto bg-light" id="chat-messages-container" style="max-height: calc(100vh - 200px);">
                        {{-- @livewire('jiny-chat::chat-messages', ['roomId' => $room->id]) --}}
                        <div id="chat-messages" class="p-3">
                            <!-- 메시지들이 여기에 로드됩니다 -->
                            <div class="text-center text-muted">
                                <i class="fas fa-spinner fa-spin"></i> 메시지를 불러오는 중...
                            </div>
                        </div>
                    </div>

                    {{-- 메시지 작성 --}}
                    <div class="flex-shrink-0 bg-white border-top p-3">
                        {{-- @livewire('jiny-chat::chat-write', ['roomId' => $room->id]) --}}
                        <form id="message-form" class="d-flex align-items-center">
                            <div class="flex-grow-1 me-2">
                                <input type="text" id="message-input" class="form-control"
                                       placeholder="메시지를 입력하세요..." autocomplete="off">
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 페이지 제목 업데이트
            document.title = '{{ $room->title }} - 지니채팅';

            // 채팅방 변수 설정
            const roomId = {{ $room->id }};
            const messagesContainer = document.getElementById('chat-messages');
            const messageForm = document.getElementById('message-form');
            const messageInput = document.getElementById('message-input');
            let lastMessageId = 0;
            let isLoading = false;

            // JWT 토큰 가져오기 함수
            function getAuthToken() {
                // 1. LocalStorage에서 시도
                let token = localStorage.getItem('jwt_token') || localStorage.getItem('token') || localStorage.getItem('auth_token');
                if (token) return token;

                // 2. SessionStorage에서 시도
                token = sessionStorage.getItem('jwt_token') || sessionStorage.getItem('token') || sessionStorage.getItem('auth_token');
                if (token) return token;

                // 3. 쿠키에서 시도
                const cookies = document.cookie.split(';');
                for (let cookie of cookies) {
                    const [name, value] = cookie.trim().split('=');
                    if (name && (name.includes('jwt') || name.includes('token') || name.includes('auth'))) {
                        return value;
                    }
                }

                return null;
            }

            // API 요청 헤더 생성
            function getApiHeaders() {
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                };

                // CSRF 토큰 추가
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (csrfToken) {
                    headers['X-CSRF-TOKEN'] = csrfToken;
                }

                // JWT 토큰 추가
                const authToken = getAuthToken();
                if (authToken) {
                    headers['Authorization'] = `Bearer ${authToken}`;
                }

                return headers;
            }

            // 메시지 HTML 생성 함수
            function createMessageHTML(message) {
                const isMyMessage = message.is_my_message;
                const messageClass = isMyMessage ? 'justify-content-end' : 'justify-content-start';
                const bubbleClass = isMyMessage ? 'bg-primary text-white' : 'bg-white border';
                const avatarHTML = !isMyMessage && message.user_avatar ?
                    `<img src="${message.user_avatar}" alt="${message.user_name}" class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;">` :
                    (!isMyMessage ? `<div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-2 text-white" style="width: 40px; height: 40px; font-size: 1rem;">${message.user_name.charAt(0)}</div>` : '');

                let filesHTML = '';
                if (message.files && message.files.length > 0) {
                    filesHTML = message.files.map(file => {
                        if (file.is_image) {
                            return `
                                <div class="mt-2">
                                    <img src="${file.preview_url}" alt="${file.name}"
                                         class="img-fluid rounded" style="max-width: 200px; cursor: pointer;"
                                         onclick="showImageModal('${file.preview_url}', '${file.name}')">
                                    <div class="small text-muted mt-1">
                                        <a href="${file.download_url}" class="text-decoration-none" download>
                                            <i class="fas fa-download"></i> ${file.name}
                                        </a>
                                    </div>
                                </div>
                            `;
                        } else {
                            return `
                                <div class="mt-2">
                                    <div class="border rounded p-2 bg-light">
                                        <i class="fas fa-file"></i>
                                        <a href="${file.download_url}" class="text-decoration-none ms-2" download>
                                            ${file.name}
                                        </a>
                                        <small class="text-muted d-block">${formatFileSize(file.size)}</small>
                                    </div>
                                </div>
                            `;
                        }
                    }).join('');
                }

                let replyHTML = '';
                if (message.reply_to) {
                    replyHTML = `
                        <div class="small mb-2 opacity-75">
                            <i class="fas fa-reply"></i> ${message.reply_to.user_name}: ${message.reply_to.content}
                        </div>
                    `;
                }

                return `
                    <div class="d-flex ${messageClass} mb-3" data-message-id="${message.id}">
                        ${!isMyMessage ? avatarHTML : ''}
                        <div class="message-content" style="max-width: 70%;">
                            ${!isMyMessage ? `<div class="small text-muted mb-1">${message.user_name}</div>` : ''}
                            <div class="message-bubble ${bubbleClass} p-3 rounded-3 shadow-sm">
                                ${replyHTML}
                                <div class="message-text">${escapeHtml(message.content)}</div>
                                ${filesHTML}
                                <div class="small mt-2 ${isMyMessage ? 'text-white-50' : 'text-muted'}">
                                    ${message.created_at_human}
                                </div>
                            </div>
                        </div>
                        ${isMyMessage ? avatarHTML : ''}
                    </div>
                `;
            }

            // HTML 이스케이프 함수
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // 파일 크기 포맷 함수
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }

            // 이미지 모달 표시 함수
            function showImageModal(imageUrl, imageName) {
                // Bootstrap 모달 생성
                const modalHTML = `
                    <div class="modal fade" id="imageModal" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">${imageName}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body text-center">
                                    <img src="${imageUrl}" alt="${imageName}" class="img-fluid">
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // 기존 모달 제거
                const existingModal = document.getElementById('imageModal');
                if (existingModal) existingModal.remove();

                // 새 모달 추가
                document.body.insertAdjacentHTML('beforeend', modalHTML);

                // 모달 표시
                const modal = new bootstrap.Modal(document.getElementById('imageModal'));
                modal.show();
            }

            // 메시지 로드 함수
            async function loadMessages(append = false) {
                if (isLoading) return;
                isLoading = true;

                try {
                    const url = `/api/chat/server/${roomId}/messages${lastMessageId ? `?last_message_id=${lastMessageId}` : ''}`;

                    const response = await fetch(url, {
                        method: 'GET',
                        headers: getApiHeaders()
                    });

                    const data = await response.json();

                    if (data.success) {
                        const messages = data.messages;

                        if (messages.length > 0) {
                            const messagesHTML = messages.map(createMessageHTML).join('');

                            if (append) {
                                messagesContainer.insertAdjacentHTML('beforeend', messagesHTML);
                            } else {
                                messagesContainer.innerHTML = messagesHTML;
                            }

                            // 마지막 메시지 ID 업데이트
                            lastMessageId = Math.max(...messages.map(m => m.id));

                            // 스크롤을 맨 아래로
                            const container = document.getElementById('chat-messages-container');
                            container.scrollTop = container.scrollHeight;
                        } else if (!append) {
                            messagesContainer.innerHTML = '<div class="text-center text-muted py-4">메시지가 없습니다.</div>';
                        }
                    } else {
                        console.error('메시지 로드 실패:', data.message);
                        if (!append) {
                            messagesContainer.innerHTML = `<div class="text-center text-danger py-4">메시지를 불러올 수 없습니다: ${data.message}</div>`;
                        }
                    }
                } catch (error) {
                    console.error('메시지 로드 오류:', error);
                    if (!append) {
                        messagesContainer.innerHTML = '<div class="text-center text-danger py-4">메시지를 불러올 수 없습니다.</div>';
                    }
                } finally {
                    isLoading = false;
                }
            }

            // 메시지 전송 함수
            async function sendMessage(content) {
                if (!content.trim()) return;

                try {
                    const response = await fetch(`/api/chat/server/${roomId}/message`, {
                        method: 'POST',
                        headers: getApiHeaders(),
                        body: JSON.stringify({
                            content: content.trim(),
                            type: 'text'
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        // 전송된 메시지를 즉시 표시
                        const messageHTML = createMessageHTML(data.message);
                        messagesContainer.insertAdjacentHTML('beforeend', messageHTML);

                        // 마지막 메시지 ID 업데이트
                        lastMessageId = Math.max(lastMessageId, data.message.id);

                        // 스크롤을 맨 아래로
                        const container = document.getElementById('chat-messages-container');
                        container.scrollTop = container.scrollHeight;

                        // 입력창 초기화
                        messageInput.value = '';
                    } else {
                        alert('메시지 전송에 실패했습니다: ' + data.message);
                    }
                } catch (error) {
                    console.error('메시지 전송 오류:', error);
                    alert('메시지 전송 중 오류가 발생했습니다.');
                }
            }

            // 메시지 폼 이벤트 리스너
            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const content = messageInput.value.trim();
                if (content) {
                    sendMessage(content);
                }
            });

            // Enter 키로 메시지 전송
            messageInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    messageForm.dispatchEvent(new Event('submit'));
                }
            });

            // 전역 함수로 등록 (이미지 모달용)
            window.showImageModal = showImageModal;

            // 초기 메시지 로드
            loadMessages();

            // 주기적으로 새 메시지 확인 (5초마다)
            setInterval(() => {
                loadMessages(true);
            }, 5000);

            console.log('✅ 서버형 채팅 시스템 초기화 완료');
            console.log('채팅방 ID:', roomId);
        });
    </script>
@endpush
