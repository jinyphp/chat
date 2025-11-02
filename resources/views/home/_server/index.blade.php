{{-- 서버형 채팅방 메인 인터페이스 --}}
@extends('jiny-chat::layouts.chat')

@push('styles')
    <!-- FontAwesome 아이콘 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        /* 서버형 채팅방 전용 스타일 */
        .server-chat-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: relative;
        }

        .server-chat-overlay {
            background: rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

        .server-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .server-sidebar {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
        }

        .server-messages {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }

        .server-badge {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .chat-participants-area {
            height: 100vh;
        }

        .chat-messages-area {
            height: 100vh;
        }
    </style>
@endpush

@section('content')
    <div class="server-chat-container p-3">
        <div class="server-chat-overlay h-100">
            <div class="row g-0 h-100">
                <div class="col-xl-3 col-lg-12 col-md-12 col-12">
                    <div class="server-sidebar border-end chat-participants-area">
                        <!-- 서버 헤더 -->
                        <div class="server-header p-3 d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    @if($room->image)
                                        <img src="{{ asset('storage/' . $room->image) }}"
                                             alt="{{ $room->title }}"
                                             class="rounded-circle"
                                             style="width: 40px; height: 40px; object-fit: cover;">
                                    @else
                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center text-white"
                                             style="width: 40px; height: 40px; font-size: 1.2rem;">
                                            <i class="fas fa-server"></i>
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark">{{ $room->title }}</h6>
                                    <small class="text-muted">
                                        <span class="server-badge me-1">SERVER</span>
                                        온라인
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- chat users -->
                        {{-- @livewire('jiny-chat::chat-participants', ['roomId' => $room->id]) --}}
                        <div id="chat-participants" class="p-3">
                            <h6 class="mb-3 text-dark">참여자 ({{ count($participants) }}명)</h6>
                            <div id="participants-list">
                                @forelse($participants as $participant)
                                    <div class="participant-item d-flex align-items-center mb-2 p-2 rounded"
                                         data-user-uuid="{{ $participant->user_uuid }}">
                                        <div class="participant-avatar me-2">
                                            @if($participant->avatar)
                                                <img src="{{ asset('storage/' . $participant->avatar) }}"
                                                     alt="{{ $participant->user_name }}"
                                                     class="rounded-circle"
                                                     style="width: 32px; height: 32px; object-fit: cover;">
                                            @else
                                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center text-white"
                                                     style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                    {{ substr($participant->user_name, 0, 1) }}
                                                </div>
                                            @endif

                                            {{-- 온라인 상태 표시 --}}
                                            <div class="position-absolute" style="margin-left: 20px; margin-top: -8px;">
                                                <span class="badge badge-sm {{ $participant->is_online ? 'bg-success' : 'bg-secondary' }}"
                                                      style="width: 8px; height: 8px; border-radius: 50%; padding: 0;"></span>
                                            </div>
                                        </div>

                                        <div class="participant-info flex-grow-1">
                                            <div class="d-flex align-items-center">
                                                <span class="fw-semibold text-dark">{{ $participant->user_name }}</span>

                                                {{-- 역할 배지 --}}
                                                @if($participant->role !== 'member')
                                                    <span class="badge bg-warning ms-1" style="font-size: 0.7rem;">
                                                        @switch($participant->role)
                                                            @case('owner')
                                                                <i class="fas fa-crown"></i> 방장
                                                                @break
                                                            @case('admin')
                                                                <i class="fas fa-shield-alt"></i> 관리자
                                                                @break
                                                            @case('moderator')
                                                                <i class="fas fa-gavel"></i> 운영자
                                                                @break
                                                        @endswitch
                                                    </span>
                                                @endif

                                                {{-- 본인 표시 --}}
                                                @if($participant->user_uuid === $user->uuid)
                                                    <span class="badge bg-info ms-1" style="font-size: 0.7rem;">나</span>
                                                @endif
                                            </div>

                                            <small class="text-muted">
                                                {{-- 샤드 정보 표시 --}}
                                                @if(isset($participant->shard_id))
                                                    <span class="me-2">user_{{ str_pad($participant->shard_id, 2, '0', STR_PAD_LEFT) }}</span>
                                                @endif

                                                {{-- 온라인 상태 --}}
                                                @if($participant->is_online)
                                                    <span class="text-success">
                                                        <i class="fas fa-circle" style="font-size: 0.5rem;"></i> 온라인
                                                    </span>
                                                @else
                                                    <span class="text-muted">
                                                        <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                                        @if($participant->last_seen_at)
                                                            {{ \Carbon\Carbon::parse($participant->last_seen_at)->diffForHumans() }}
                                                        @else
                                                            오프라인
                                                        @endif
                                                    </span>
                                                @endif
                                            </small>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-users fa-2x mb-2"></i>
                                        <p class="mb-0">참여자가 없습니다.</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-9 col-lg-12 col-md-12 col-12">

                    <!-- chat list -->
                    <div class="server-messages d-flex flex-column w-100 chat-messages-area">
                        {{-- 메시지 헤더 --}}
                        <div class="flex-shrink-0 server-header">
                            <div class="p-3 d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <h5 class="mb-0 fw-bold text-dark">
                                        <i class="fas fa-hashtag me-1 text-primary"></i>
                                        일반 채팅
                                    </h5>
                                    @if($room->description)
                                        <small class="text-muted ms-3">{{ $room->description }}</small>
                                    @endif
                                </div>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-success me-2">
                                        <i class="fas fa-users me-1"></i>
                                        {{ count($participants) }}명 참여중
                                    </span>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="{{ route('home.chat.rooms.index') }}">
                                                <i class="fas fa-list me-2"></i>채팅방 목록
                                            </a></li>
                                            <li><a class="dropdown-item" href="{{ route('home.chat.invites') }}">
                                                <i class="fas fa-share-alt me-2"></i>초대 링크 관리
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="leaveRoom()">
                                                <i class="fas fa-sign-out-alt me-2"></i>채팅방 나가기
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 메시지 영역 --}}
                        <div class="flex-grow-1 overflow-auto" id="chat-messages-container" style="max-height: calc(100vh - 200px);">
                            <div id="chat-messages" class="p-3">
                                @forelse($messages as $message)
                                    <div class="message-item d-flex {{ $message->user_uuid === $user->uuid ? 'justify-content-end' : 'justify-content-start' }} mb-3"
                                         data-message-id="{{ $message->id }}">
                                        @if($message->user_uuid !== $user->uuid)
                                            <div class="me-2">
                                                @if($message->user_avatar)
                                                    <img src="{{ asset('storage/' . $message->user_avatar) }}"
                                                         alt="{{ $message->user_name }}"
                                                         class="rounded-circle"
                                                         style="width: 32px; height: 32px; object-fit: cover;">
                                                @else
                                                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center text-white"
                                                         style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                        {{ substr($message->user_name, 0, 1) }}
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                        <div class="message-content" style="max-width: 70%;">
                                            @if($message->user_uuid !== $user->uuid)
                                                <div class="small text-muted mb-1">{{ $message->user_name }}</div>
                                            @endif

                                            <div class="message-bubble {{ $message->user_uuid === $user->uuid ? 'bg-primary text-white' : 'bg-light text-dark' }} p-3 rounded-3">
                                                @if($message->type === 'text')
                                                    <div class="message-text">{{ $message->content }}</div>
                                                @elseif($message->type === 'file')
                                                    <div class="message-file">
                                                        <i class="fas fa-file me-2"></i>
                                                        <a href="{{ route('chat.file.download', $message->file_uuid ?? '') }}"
                                                           class="{{ $message->user_uuid === $user->uuid ? 'text-white' : 'text-primary' }}">
                                                            {{ $message->file_name ?? 'Unknown File' }}
                                                        </a>
                                                        @if($message->file_size)
                                                            <small class="d-block {{ $message->user_uuid === $user->uuid ? 'text-white-50' : 'text-muted' }}">
                                                                {{ number_format($message->file_size / 1024, 1) }} KB
                                                            </small>
                                                        @endif
                                                    </div>
                                                @endif

                                                <div class="small mt-2 {{ $message->user_uuid === $user->uuid ? 'text-white-50' : 'text-muted' }}">
                                                    {{ \Carbon\Carbon::parse($message->created_at)->format('H:i') }}
                                                </div>
                                            </div>
                                        </div>

                                        @if($message->user_uuid === $user->uuid)
                                            <div class="ms-2">
                                                @if($message->user_avatar)
                                                    <img src="{{ asset('storage/' . $message->user_avatar) }}"
                                                         alt="{{ $message->user_name }}"
                                                         class="rounded-circle"
                                                         style="width: 32px; height: 32px; object-fit: cover;">
                                                @else
                                                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center text-white"
                                                         style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                        {{ substr($message->user_name, 0, 1) }}
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-comments fa-3x mb-3"></i>
                                        <h5>아직 메시지가 없습니다</h5>
                                        <p class="mb-0">첫 번째 메시지를 보내보세요!</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        {{-- 메시지 작성 --}}
                        <div class="flex-shrink-0 server-header border-top p-3">
                            <form id="message-form" class="d-flex align-items-center" enctype="multipart/form-data">
                                <div class="me-2">
                                    <input type="file" id="file-input" class="d-none" accept="image/*,.pdf,.doc,.docx,.txt">
                                    <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('file-input').click()">
                                        <i class="fas fa-paperclip"></i>
                                    </button>
                                </div>
                                <div class="flex-grow-1 me-2">
                                    <input type="text" id="message-input" class="form-control"
                                           placeholder="메시지를 입력하세요..." autocomplete="off">
                                    <div id="file-preview" class="mt-2 d-none">
                                        <div class="alert alert-info d-flex align-items-center">
                                            <i class="fas fa-file me-2"></i>
                                            <span id="file-name"></span>
                                            <button type="button" class="btn-close ms-auto" onclick="clearFileSelection()"></button>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary" id="send-button">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 페이지 제목 업데이트 (서버 표시)
            document.title = '[SERVER] {{ $room->title }} - 지니채팅';

            // 채팅방 변수 설정
            const roomId = {{ $room->id }};
            const messagesContainer = document.getElementById('chat-messages');
            const messageForm = document.getElementById('message-form');
            const messageInput = document.getElementById('message-input');
            const fileInput = document.getElementById('file-input');
            const filePreview = document.getElementById('file-preview');
            const fileName = document.getElementById('file-name');
            const sendButton = document.getElementById('send-button');
            let lastMessageId = 0;
            let isLoading = false;
            let selectedFile = null;

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

            // API 요청 헤더 생성 (세션 우선)
            function getApiHeaders() {
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                };

                // CSRF 토큰 추가 (세션 기반 인증에 필요)
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                                '{{ csrf_token() }}';
                if (csrfToken) {
                    headers['X-CSRF-TOKEN'] = csrfToken;
                }

                // JWT 토큰 추가 (보조적)
                const authToken = getAuthToken();
                if (authToken) {
                    headers['Authorization'] = `Bearer ${authToken}`;
                }

                return headers;
            }

            // 메시지 HTML 생성 함수 (서버 테마)
            function createMessageHTML(message) {
                const isMyMessage = message.is_my_message;
                const messageClass = isMyMessage ? 'justify-content-end' : 'justify-content-start';
                const bubbleClass = isMyMessage ? 'bg-primary text-white' : 'bg-white border shadow-sm';
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
                            <div class="message-bubble ${bubbleClass} p-3 rounded-3">
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

                const existingModal = document.getElementById('imageModal');
                if (existingModal) existingModal.remove();

                document.body.insertAdjacentHTML('beforeend', modalHTML);
                const modal = new bootstrap.Modal(document.getElementById('imageModal'));
                modal.show();
            }

            // 메시지 로드 함수
            async function loadMessages(append = false) {
                if (isLoading) return;
                isLoading = true;

                try {
                    const url = `/home/chat/api/server/${roomId}/messages${lastMessageId ? `?last_message_id=${lastMessageId}` : ''}`;

                    const response = await fetch(url, {
                        method: 'GET',
                        headers: getApiHeaders(),
                        credentials: 'same-origin'
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

                            lastMessageId = Math.max(...messages.map(m => m.id));

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

            // 파일 선택 이벤트 핸들러
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    selectedFile = file;
                    fileName.textContent = file.name;
                    filePreview.classList.remove('d-none');
                } else {
                    clearFileSelection();
                }
            });

            // 파일 선택 해제 함수
            function clearFileSelection() {
                selectedFile = null;
                fileInput.value = '';
                fileName.textContent = '';
                filePreview.classList.add('d-none');
            }

            // 메시지 전송 함수 (파일 업로드 지원)
            async function sendMessage(content) {
                console.log('🚀 [STEP 1] 메시지 전송 시작');
                console.log('- 전송할 내용:', content);
                console.log('- 선택된 파일:', selectedFile);
                console.log('- 채팅방 ID:', roomId);

                if (!content.trim() && !selectedFile) {
                    console.log('❌ [STEP 1.1] 전송할 내용이 없음 - 함수 종료');
                    return;
                }

                const isFileMessage = selectedFile !== null;
                console.log('📝 [STEP 2] 메시지 타입 결정:', isFileMessage ? '파일 메시지' : '텍스트 메시지');

                // UI 상태 업데이트
                sendButton.disabled = true;
                sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                console.log('🔄 [STEP 3] 전송 버튼 로딩 상태로 변경');

                try {
                    let response;
                    const apiUrl = `/home/chat/api/server/sse/${roomId}/message`;
                    console.log('🌐 [STEP 4] API 엔드포인트 설정:', apiUrl);

                    if (isFileMessage) {
                        console.log('📁 [STEP 5A] 파일 메시지 처리 시작');

                        // 파일이 있는 경우 FormData 사용
                        const formData = new FormData();
                        formData.append('content', content.trim() || '파일 첨부');
                        formData.append('type', 'file');
                        formData.append('file', selectedFile);

                        console.log('📋 [STEP 5A.1] FormData 생성 완료');
                        console.log('- content:', content.trim() || '파일 첨부');
                        console.log('- type: file');
                        console.log('- file:', selectedFile.name, selectedFile.size + ' bytes');

                        // 파일 업로드용 헤더 (Content-Type 제외)
                        const headers = {};
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
                        if (csrfToken) {
                            headers['X-CSRF-TOKEN'] = csrfToken;
                            console.log('🛡️ [STEP 5A.2] CSRF 토큰 추가:', csrfToken.substring(0, 8) + '...');
                        }

                        const authToken = getAuthToken();
                        if (authToken) {
                            headers['Authorization'] = `Bearer ${authToken}`;
                            console.log('🔑 [STEP 5A.3] JWT 토큰 추가:', authToken.substring(0, 8) + '...');
                        }

                        console.log('📤 [STEP 5A.4] 파일 업로드 요청 전송 시작...');

                        response = await fetch(apiUrl, {
                            method: 'POST',
                            headers: headers,
                            credentials: 'same-origin',
                            body: formData
                        });

                        console.log('📨 [STEP 5A.5] 파일 업로드 응답 수신:', response.status, response.statusText);

                    } else {
                        console.log('✏️ [STEP 5B] 텍스트 메시지 처리 시작');

                        const headers = getApiHeaders();
                        const requestBody = {
                            content: content.trim(),
                            type: 'text'
                        };

                        console.log('📋 [STEP 5B.1] 요청 데이터 준비');
                        console.log('- 헤더:', headers);
                        console.log('- 요청 본문:', requestBody);

                        console.log('📤 [STEP 5B.2] 텍스트 메시지 요청 전송 시작...');

                        // 텍스트만 있는 경우 JSON 사용
                        response = await fetch(apiUrl, {
                            method: 'POST',
                            headers: headers,
                            credentials: 'same-origin',
                            body: JSON.stringify(requestBody)
                        });

                        console.log('📨 [STEP 5B.3] 텍스트 메시지 응답 수신:', response.status, response.statusText);
                    }

                    console.log('📊 [STEP 6] 응답 상태 확인');
                    console.log('- HTTP 상태:', response.status);
                    console.log('- 상태 텍스트:', response.statusText);
                    console.log('- OK 여부:', response.ok);

                    if (!response.ok) {
                        console.error('❌ [STEP 6.1] HTTP 응답 오류');
                        console.error('- 상태 코드:', response.status);
                        console.error('- 응답 텍스트:', await response.text());
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }

                    console.log('📋 [STEP 7] 응답 JSON 파싱 시작...');
                    const data = await response.json();
                    console.log('📄 [STEP 7.1] 응답 데이터:', data);

                    if (data.success) {
                        console.log('✅ [STEP 8] 메시지 전송 성공!');
                        console.log('- 메시지 ID:', data.message?.id);
                        console.log('- 메시지 내용:', data.message?.content);
                        console.log('- 브로드캐스트 여부:', data.broadcast);

                        console.log('🏗️ [STEP 9] 메시지 HTML 생성 및 추가');
                        const messageHTML = createMessageHTML(data.message);
                        messagesContainer.insertAdjacentHTML('beforeend', messageHTML);

                        lastMessageId = Math.max(lastMessageId, data.message.id);
                        console.log('📍 [STEP 9.1] 마지막 메시지 ID 업데이트:', lastMessageId);

                        console.log('🔽 [STEP 10] 스크롤을 맨 아래로 이동');
                        const container = document.getElementById('chat-messages-container');
                        container.scrollTop = container.scrollHeight;

                        console.log('🧹 [STEP 11] 입력 폼 초기화');
                        messageInput.value = '';
                        clearFileSelection();

                        console.log('🎉 [메시지 전송 완료] 모든 단계 성공!');

                    } else {
                        console.error('❌ [STEP 8] 서버에서 실패 응답');
                        console.error('- 오류 메시지:', data.message);
                        console.error('- 전체 응답:', data);
                        alert('메시지 전송에 실패했습니다: ' + data.message);
                    }
                } catch (error) {
                    console.error('💥 [STEP ERROR] 메시지 전송 중 예외 발생');
                    console.error('- 오류 타입:', error.constructor.name);
                    console.error('- 오류 메시지:', error.message);
                    console.error('- 스택 트레이스:', error.stack);
                    console.error('- 전체 오류 객체:', error);
                    alert('메시지 전송 중 오류가 발생했습니다: ' + error.message);
                } finally {
                    console.log('🔄 [STEP FINAL] 전송 버튼 상태 복원');
                    sendButton.disabled = false;
                    sendButton.innerHTML = '<i class="fas fa-paper-plane"></i>';
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

            // 전역 함수로 등록
            window.showImageModal = showImageModal;
            window.clearFileSelection = clearFileSelection;

            // SSE 이벤트 스트림 설정
            function setupSSE() {
                const eventSource = new EventSource(`/home/chat/api/server/sse/${roomId}/stream`);

                eventSource.onopen = function(event) {
                    console.log('✅ SSE 연결 성공');
                };

                eventSource.onmessage = function(event) {
                    try {
                        const data = JSON.parse(event.data);

                        if (data.type === 'message') {
                            // 새 메시지 수신
                            const message = data.message;
                            if (message.id > lastMessageId) {
                                const messageHTML = createMessageHTML(message);
                                messagesContainer.insertAdjacentHTML('beforeend', messageHTML);
                                lastMessageId = message.id;

                                const container = document.getElementById('chat-messages-container');
                                container.scrollTop = container.scrollHeight;
                            }
                        } else if (data.type === 'participants') {
                            // 참여자 목록 업데이트
                            updateParticipantsList(data.participants);
                        }
                    } catch (error) {
                        console.error('SSE 데이터 파싱 오류:', error);
                    }
                };

                eventSource.onerror = function(event) {
                    console.error('❌ SSE 연결 오류:', event);

                    // 연결이 끊어지면 5초 후 재연결 시도
                    setTimeout(() => {
                        console.log('🔄 SSE 재연결 시도...');
                        eventSource.close();
                        setupSSE();
                    }, 5000);
                };

                // 페이지 종료 시 SSE 연결 정리
                window.addEventListener('beforeunload', function() {
                    eventSource.close();
                });

                return eventSource;
            }

            // 참여자 목록 로드 함수
            async function loadParticipants() {
                try {
                    const response = await fetch(`/home/chat/api/server/sse/${roomId}/participants`, {
                        method: 'GET',
                        headers: getApiHeaders(),
                        credentials: 'same-origin'
                    });

                    const data = await response.json();

                    if (data.success) {
                        updateParticipantsList(data.participants);
                    } else {
                        console.error('참여자 목록 로드 실패:', data.message);
                    }
                } catch (error) {
                    console.error('참여자 목록 로드 오류:', error);
                }
            }

            // 참여자 목록 업데이트 함수
            function updateParticipantsList(participants) {
                const participantsList = document.getElementById('participants-list');

                const participantsHTML = participants.map(participant => `
                    <div class="d-flex align-items-center mb-2 p-2 rounded" style="background: rgba(0,0,0,0.05);">
                        ${participant.user_avatar ?
                            `<img src="${participant.user_avatar}" alt="${participant.user_name}" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">` :
                            `<div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2 text-white" style="width: 32px; height: 32px; font-size: 0.8rem;">${participant.user_name.charAt(0)}</div>`
                        }
                        <div class="flex-grow-1">
                            <div class="fw-medium small">${participant.user_name}</div>
                            <div class="text-muted" style="font-size: 0.7rem;">
                                ${participant.status === 'active' ? '온라인' : '오프라인'}
                            </div>
                        </div>
                        ${participant.status === 'active' ? '<div class="badge bg-success" style="width: 8px; height: 8px; border-radius: 50%;"></div>' : ''}
                    </div>
                `).join('');

                participantsList.innerHTML = participantsHTML;
            }

            // 스크롤을 맨 아래로 이동하는 함수
            function scrollToBottom() {
                const container = document.getElementById('chat-messages-container');
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            }

            // 페이지 로드 시 메시지 영역을 맨 아래로 스크롤
            scrollToBottom();

            // 메시지가 이미 서버에서 로드되었으므로 추가 로드 불필요

            // 초기 참여자 목록 로드
            loadParticipants();

            // SSE 스트림 시작
            const sseConnection = setupSSE();

            // 백업용 폴링 (SSE가 지원되지 않는 환경을 위해)
            setInterval(() => {
                if (sseConnection.readyState === EventSource.CLOSED) {
                    console.log('🔄 SSE 연결이 끊어짐, 폴링으로 메시지 확인');
                    loadMessages(true);
                }
            }, 10000);

            console.log('🖥️ 서버형 채팅 시스템 초기화 완료');
            console.log('채팅방 ID:', roomId);
        });

        // 채팅방 나가기 함수
        function leaveRoom() {
            if (confirm('정말로 이 서버 채팅방을 나가시겠습니까?\n나간 후에는 다시 초대받아야 입장할 수 있습니다.')) {
                // 채팅방 나가기 처리
                fetch(`/api/chat/rooms/{{ $room->id }}/leave`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('채팅방을 나갔습니다.');
                        window.location.href = '{{ route("home.chat.rooms.index") }}';
                    } else {
                        alert('채팅방 나가기에 실패했습니다: ' + (data.message || '알 수 없는 오류'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('채팅방 나가기 중 오류가 발생했습니다.');
                });
            }
        }
    </script>
@endpush
