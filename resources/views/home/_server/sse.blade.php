{{-- SSE 방식 실시간 채팅방 --}}
@extends('jiny-chat::layouts.chat')

@push('styles')
    <!-- FontAwesome 아이콘 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        /* SSE 채팅방 전용 스타일 */
        .sse-chat-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: relative;
        }

        .sse-chat-overlay {
            background: rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

        .sse-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sse-sidebar {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
        }

        .sse-messages {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }

        .sse-badge {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .connection-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .connection-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .connection-indicator.connected {
            background-color: #28a745;
        }

        .connection-indicator.disconnected {
            background-color: #dc3545;
        }

        .connection-indicator.connecting {
            background-color: #ffc107;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .participant-item {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: background-color 0.2s;
        }

        .participant-item:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        .participant-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            margin-right: 10px;
        }

        .participant-status {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-left: auto;
        }

        .participant-status.online {
            background-color: #28a745;
        }

        .participant-status.offline {
            background-color: #6c757d;
        }

        .message-item {
            margin-bottom: 15px;
        }

        .message-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            position: relative;
        }

        .message-bubble.my-message {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            margin-left: auto;
        }

        .message-bubble.other-message {
            background: white;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .chat-participants-area {
            height: 100vh;
            overflow-y: auto;
        }

        .chat-messages-area {
            height: 100vh;
        }

        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-radius: 50%;
            border-top: 2px solid #667eea;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
@endpush

@section('content')
    <div class="sse-chat-container p-3">
        <div class="sse-chat-overlay h-100">
            <div class="row g-0 h-100">
                {{-- 참여자 영역 --}}
                <div class="col-xl-3 col-lg-12 col-md-12 col-12">
                    <div class="sse-sidebar border-end chat-participants-area">
                        {{-- 채팅방 헤더 --}}
                        <div class="sse-header p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="mb-1 fw-bold text-dark">{{ $room->title }}</h6>
                                <div class="d-flex align-items-center">
                                    <span class="sse-badge me-2">SSE</span>
                                    <div class="connection-status">
                                        <div class="connection-indicator connecting" id="connection-indicator"></div>
                                        <small class="text-muted" id="connection-status">연결 중...</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 참여자 목록 --}}
                        <div class="p-3">
                            <h6 class="mb-3 text-dark d-flex align-items-center">
                                <i class="fas fa-users me-2"></i>
                                참여자
                                <span class="badge bg-primary ms-2" id="participants-count">{{ count($participants) }}</span>
                            </h6>
                            <div id="participants-list">
                                {{-- 참여자 목록이 여기에 로드됩니다 --}}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 메시지 영역 --}}
                <div class="col-xl-9 col-lg-12 col-md-12 col-12">
                    <div class="sse-messages d-flex flex-column w-100 chat-messages-area">
                        {{-- 메시지 헤더 --}}
                        <div class="flex-shrink-0 sse-header">
                            <div class="p-3 d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <h5 class="mb-0 fw-bold text-dark">
                                        <i class="fas fa-hashtag me-1 text-primary"></i>
                                        실시간 채팅
                                    </h5>
                                    @if($room->description)
                                        <small class="text-muted ms-3">{{ $room->description }}</small>
                                    @endif
                                </div>
                                <div class="d-flex align-items-center">
                                    <small class="text-muted me-2">방 번호: {{ $roomId }}</small>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="#" onclick="refreshConnection()">
                                                <i class="fas fa-sync me-2"></i>연결 새로고침
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="{{ route('home.chat.rooms.index') }}">
                                                <i class="fas fa-arrow-left me-2"></i>채팅방 목록
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 메시지 영역 --}}
                        <div class="flex-grow-1 overflow-auto" id="chat-messages-container" style="max-height: calc(100vh - 200px);">
                            <div id="chat-messages" class="p-3">
                                {{-- 기존 메시지들 --}}
                                @foreach($messages as $message)
                                    <div class="message-item d-flex {{ $message->user_uuid === $user->uuid ? 'justify-content-end' : 'justify-content-start' }}" data-message-id="{{ $message->id }}">
                                        @if($message->user_uuid !== $user->uuid)
                                            <div class="participant-avatar me-2">
                                                {{ substr($message->user_name, 0, 1) }}
                                            </div>
                                        @endif
                                        <div class="message-content">
                                            @if($message->user_uuid !== $user->uuid)
                                                <div class="small text-muted mb-1">{{ $message->user_name }}</div>
                                            @endif
                                            <div class="message-bubble {{ $message->user_uuid === $user->uuid ? 'my-message' : 'other-message' }}">
                                                <div class="message-text">{{ $message->content }}</div>
                                                <div class="small mt-2 {{ $message->user_uuid === $user->uuid ? 'text-white-50' : 'text-muted' }}">
                                                    {{ \Carbon\Carbon::parse($message->created_at)->format('H:i') }}
                                                </div>
                                            </div>
                                        </div>
                                        @if($message->user_uuid === $user->uuid)
                                            <div class="participant-avatar ms-2">
                                                {{ substr($message->user_name, 0, 1) }}
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- 메시지 작성 --}}
                        <div class="flex-shrink-0 sse-header border-top p-3">
                            <form id="message-form" class="d-flex align-items-center">
                                <div class="flex-grow-1 me-2">
                                    <input type="text" id="message-input" class="form-control"
                                           placeholder="메시지를 입력하세요..." autocomplete="off">
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
            // 페이지 제목 업데이트
            document.title = '[SSE] {{ $room->title }} - 지니채팅';

            // 전역 변수
            const roomId = {{ $roomId }};
            const currentUser = @json($user);
            const messagesContainer = document.getElementById('chat-messages');
            const messageForm = document.getElementById('message-form');
            const messageInput = document.getElementById('message-input');
            const sendButton = document.getElementById('send-button');
            const connectionIndicator = document.getElementById('connection-indicator');
            const connectionStatus = document.getElementById('connection-status');
            const participantsList = document.getElementById('participants-list');
            const participantsCount = document.getElementById('participants-count');

            let eventSource = null;
            let isConnected = false;
            let reconnectAttempts = 0;
            const maxReconnectAttempts = 5;
            let heartbeatInterval = null;

            // SSE 연결 설정
            function setupSSEConnection() {
                try {
                    updateConnectionStatus('connecting', '연결 중...');

                    eventSource = new EventSource(`/home/chat/api/server/sse/${roomId}/stream`);

                    // 연결 성공
                    eventSource.onopen = function(event) {
                        console.log('✅ SSE 연결 성공');
                        updateConnectionStatus('connected', '연결됨');
                        isConnected = true;
                        reconnectAttempts = 0;
                        startHeartbeat();
                    };

                    // 메시지 수신
                    eventSource.onmessage = function(event) {
                        try {
                            const data = JSON.parse(event.data);
                            handleSSEEvent(data);
                        } catch (e) {
                            console.error('SSE 메시지 파싱 오류:', e);
                        }
                    };

                    // 새 메시지 이벤트
                    eventSource.addEventListener('new_message', function(event) {
                        try {
                            const data = JSON.parse(event.data);
                            addNewMessage(data.data);
                        } catch (e) {
                            console.error('새 메시지 처리 오류:', e);
                        }
                    });

                    // 연결 확인 이벤트
                    eventSource.addEventListener('connected', function(event) {
                        console.log('SSE 연결 확인됨');
                    });

                    // 하트비트 이벤트
                    eventSource.addEventListener('heartbeat', function(event) {
                        console.log('💓 SSE 하트비트');
                    });

                    // 연결 오류
                    eventSource.onerror = function(event) {
                        console.error('❌ SSE 연결 오류:', event);
                        updateConnectionStatus('disconnected', '연결 끊김');
                        isConnected = false;
                        stopHeartbeat();

                        // 자동 재연결 시도
                        if (reconnectAttempts < maxReconnectAttempts) {
                            reconnectAttempts++;
                            console.log(`🔄 SSE 재연결 시도 ${reconnectAttempts}/${maxReconnectAttempts}`);
                            setTimeout(() => {
                                eventSource.close();
                                setupSSEConnection();
                            }, 3000 * reconnectAttempts);
                        } else {
                            updateConnectionStatus('disconnected', '연결 실패');
                        }
                    };

                } catch (error) {
                    console.error('SSE 연결 설정 오류:', error);
                    updateConnectionStatus('disconnected', '연결 실패');
                }
            }

            // SSE 이벤트 처리
            function handleSSEEvent(data) {
                switch (data.type) {
                    case 'connected':
                        console.log('SSE 연결 설정됨:', data.data);
                        break;
                    case 'new_message':
                        addNewMessage(data.data);
                        break;
                    case 'heartbeat':
                        // 하트비트는 조용히 처리
                        break;
                    default:
                        console.log('알 수 없는 SSE 이벤트:', data);
                }
            }

            // 연결 상태 업데이트
            function updateConnectionStatus(status, message) {
                connectionIndicator.className = `connection-indicator ${status}`;
                connectionStatus.textContent = message;
            }

            // 새 메시지 추가
            function addNewMessage(message) {
                const messageHTML = createMessageHTML(message);
                messagesContainer.insertAdjacentHTML('beforeend', messageHTML);
                scrollToBottom();
            }

            // 메시지 HTML 생성
            function createMessageHTML(message) {
                const isMyMessage = message.user_uuid === currentUser.uuid;
                const messageClass = isMyMessage ? 'justify-content-end' : 'justify-content-start';
                const bubbleClass = isMyMessage ? 'my-message' : 'other-message';

                let avatarHTML = '';
                if (!isMyMessage) {
                    avatarHTML = `
                        <div class="participant-avatar me-2">
                            ${message.user_name.charAt(0)}
                        </div>
                    `;
                }

                let myAvatarHTML = '';
                if (isMyMessage) {
                    myAvatarHTML = `
                        <div class="participant-avatar ms-2">
                            ${message.user_name.charAt(0)}
                        </div>
                    `;
                }

                return `
                    <div class="message-item d-flex ${messageClass}" data-message-id="${message.id}">
                        ${avatarHTML}
                        <div class="message-content">
                            ${!isMyMessage ? `<div class="small text-muted mb-1">${message.user_name}</div>` : ''}
                            <div class="message-bubble ${bubbleClass}">
                                <div class="message-text">${escapeHtml(message.content)}</div>
                                <div class="small mt-2 ${isMyMessage ? 'text-white-50' : 'text-muted'}">
                                    ${message.created_at_human || new Date().toLocaleTimeString('ko-KR', {hour: '2-digit', minute: '2-digit'})}
                                </div>
                            </div>
                        </div>
                        ${myAvatarHTML}
                    </div>
                `;
            }

            // HTML 이스케이프
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // 스크롤을 맨 아래로
            function scrollToBottom() {
                const container = document.getElementById('chat-messages-container');
                container.scrollTop = container.scrollHeight;
            }

            // 하트비트 시작
            function startHeartbeat() {
                stopHeartbeat(); // 기존 하트비트 정리
                heartbeatInterval = setInterval(async () => {
                    try {
                        const response = await fetch(`/home/chat/api/server/sse/${roomId}/heartbeat`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin'
                        });

                        const data = await response.json();
                        if (data.success) {
                            console.log('💓 하트비트 전송 성공:', data.data);
                            // 참여자 수 업데이트
                            if (participantsCount && data.data.active_participants !== undefined) {
                                participantsCount.textContent = data.data.active_participants;
                            }
                        } else {
                            console.warn('⚠️ 하트비트 전송 실패:', data.message);
                        }
                    } catch (error) {
                        console.error('❌ 하트비트 오류:', error);
                    }
                }, 30000); // 30초마다 하트비트
            }

            // 하트비트 중지
            function stopHeartbeat() {
                if (heartbeatInterval) {
                    clearInterval(heartbeatInterval);
                    heartbeatInterval = null;
                }
            }

            // 메시지 전송
            async function sendMessage(content) {
                if (!content.trim() || !isConnected) return;

                const originalText = sendButton.innerHTML;
                sendButton.innerHTML = '<div class="loading-spinner"></div>';
                sendButton.disabled = true;

                try {
                    const response = await fetch(`/home/chat/api/server/sse/${roomId}/message`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            content: content.trim(),
                            type: 'text'
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        // 성공 시 내 메시지를 즉시 표시
                        addNewMessage(data.message);
                        messageInput.value = '';
                    } else {
                        alert('메시지 전송에 실패했습니다: ' + data.message);
                    }

                } catch (error) {
                    console.error('메시지 전송 오류:', error);
                    alert('메시지 전송 중 오류가 발생했습니다.');
                } finally {
                    sendButton.innerHTML = originalText;
                    sendButton.disabled = false;
                }
            }

            // 참여자 목록 로드
            async function loadParticipants() {
                try {
                    const response = await fetch(`/home/chat/api/server/sse/${roomId}/participants`, {
                        credentials: 'same-origin'
                    });

                    const data = await response.json();

                    if (data.success) {
                        updateParticipantsList(data.participants);
                        participantsCount.textContent = data.total_count;
                    }

                } catch (error) {
                    console.error('참여자 목록 로드 오류:', error);
                }
            }

            // 참여자 목록 업데이트
            function updateParticipantsList(participants) {
                const html = participants.map(participant => `
                    <div class="participant-item">
                        <div class="participant-avatar">
                            ${participant.name.charAt(0)}
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-medium">${participant.name}</div>
                            <small class="text-muted">${participant.shard_info.formatted}</small>
                        </div>
                        <div class="participant-status ${participant.is_online ? 'online' : 'offline'}"></div>
                    </div>
                `).join('');

                participantsList.innerHTML = html;
            }

            // 연결 새로고침
            window.refreshConnection = function() {
                if (eventSource) {
                    eventSource.close();
                }
                reconnectAttempts = 0;
                setupSSEConnection();
            };

            // 이벤트 리스너
            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const content = messageInput.value.trim();
                if (content) {
                    sendMessage(content);
                }
            });

            messageInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    messageForm.dispatchEvent(new Event('submit'));
                }
            });

            // 초기화
            setupSSEConnection();
            loadParticipants();
            scrollToBottom();

            // 주기적으로 참여자 목록 업데이트 (30초마다)
            setInterval(loadParticipants, 30000);

            // 페이지 언로드 시 SSE 연결 종료
            window.addEventListener('beforeunload', function() {
                if (eventSource) {
                    eventSource.close();
                }
            });

            console.log('🚀 SSE 채팅 시스템 초기화 완료');
            console.log('채팅방 ID:', roomId);
            console.log('현재 사용자:', currentUser);
        });
    </script>
@endpush