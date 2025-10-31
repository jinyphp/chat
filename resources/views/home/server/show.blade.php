{{-- SSE 방식 채팅방 인터페이스 --}}
@extends('jiny-site::layouts.home')

@push('styles')
    <!-- FontAwesome 아이콘 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        /* SSE 채팅 레이아웃 스타일 */
        .sse-chat-container {
            height: calc(100vh - 250px);
            min-height: 600px;
            display: flex;
            flex-direction: column;
        }

        .sse-chat-header {
            flex-shrink: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            border-radius: 10px 10px 0 0;
        }

        .sse-chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background-color: #f8f9fa;
            border-left: 1px solid #dee2e6;
            border-right: 1px solid #dee2e6;
        }

        .sse-chat-input {
            flex-shrink: 0;
            padding: 1rem;
            background-color: white;
            border: 1px solid #dee2e6;
            border-radius: 0 0 10px 10px;
        }

        .sse-message {
            margin-bottom: 1rem;
            animation: fadeInUp 0.3s ease-out;
        }

        .sse-message.mine {
            text-align: right;
        }

        .sse-message.mine .message-bubble {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            margin-left: auto;
        }

        .sse-message.others .message-bubble {
            background-color: white;
            border: 1px solid #e9ecef;
        }

        .message-bubble {
            display: inline-block;
            max-width: 70%;
            padding: 0.75rem 1rem;
            border-radius: 18px;
            word-wrap: break-word;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .message-sender {
            font-size: 0.75rem;
            margin-bottom: 0.25rem;
            opacity: 0.8;
        }

        .message-time {
            font-size: 0.7rem;
            margin-top: 0.25rem;
            opacity: 0.6;
        }

        .sse-status {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            min-width: 200px;
        }

        .participants-panel {
            position: fixed;
            top: 80px;
            left: 20px;
            width: 250px;
            max-height: 400px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 999;
        }

        .participant-item {
            padding: 0.5rem;
            border-bottom: 1px solid #f1f3f4;
            display: flex;
            align-items: center;
        }

        .participant-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 0.5rem;
        }

        .online-indicator {
            width: 8px;
            height: 8px;
            background-color: #28a745;
            border-radius: 50%;
            margin-left: auto;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .connecting {
            animation: pulse 1.5s infinite;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid px-4">
        <!-- 페이지 헤더 -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center"
                            style="width: 50px; height: 50px;">
                            <i class="fas fa-satellite-dish text-white fs-4"></i>
                        </div>
                    </div>
                    <div>
                        <h1 class="h3 mb-1 fw-bold text-dark">
                            {{ $room->title }} <span class="badge bg-success">SSE</span>
                        </h1>
                        <p class="text-muted mb-0 small">
                            Server-Sent Events 실시간 채팅
                            · 참여자 {{ $room->participant_count }}명
                        </p>
                    </div>
                </div>
            </div>

            <!-- 네비게이션 버튼들 -->
            <div>
                <div class="d-flex gap-2">
                    <a href="{{ route('home.chat.room.show', $room->id) }}" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-sync"></i> Polling 방식
                    </a>
                    <a href="{{ route('home.chat.index') }}" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-list"></i> 참여채팅방
                    </a>
                    <button class="btn btn-outline-secondary btn-sm" onclick="toggleParticipants()">
                        <i class="fas fa-users"></i> 참여자
                    </button>
                </div>
            </div>
        </div>

        <!-- SSE 연결 상태 표시 -->
        <div id="sse-status" class="sse-status">
            <div class="alert alert-info connecting">
                <i class="fas fa-circle-notch fa-spin me-2"></i>
                <span id="status-text">SSE 연결 중...</span>
            </div>
        </div>

        <!-- 참여자 패널 -->
        <div id="participants-panel" class="participants-panel" style="display: none;">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        참여자 (<span id="participant-count">0</span>)
                    </h6>
                </div>
                <div class="card-body p-0" id="participants-list">
                    <!-- 참여자 목록이 여기에 동적으로 추가됩니다 -->
                </div>
            </div>
        </div>

        <!-- SSE 채팅 메인 영역 -->
        <div class="card sse-chat-container">
            <!-- 채팅 헤더 -->
            <div class="sse-chat-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">{{ $room->title }}</h5>
                        <small class="opacity-75">{{ $room->description }}</small>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark">
                            <i class="fas fa-bolt me-1"></i>
                            실시간
                        </span>
                    </div>
                </div>
            </div>

            <!-- 메시지 영역 -->
            <div class="sse-chat-messages" id="messages-container">
                <div class="text-center text-muted py-4" id="no-messages">
                    <i class="fas fa-comments fa-3x mb-3 opacity-50"></i>
                    <p>아직 메시지가 없습니다.</p>
                    <p>첫 번째 메시지를 보내보세요!</p>
                </div>
            </div>

            <!-- 메시지 입력 영역 -->
            <div class="sse-chat-input">
                <form id="message-form" class="d-flex gap-2">
                    <input type="text"
                           id="message-input"
                           class="form-control"
                           placeholder="메시지를 입력하세요..."
                           maxlength="1000"
                           disabled>
                    <button type="submit"
                            id="send-button"
                            class="btn btn-primary"
                            disabled>
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
                <div class="mt-2">
                    <small class="text-muted">
                        Room ID: {{ $room->id }} | User: {{ $user->name }} ({{ $user->uuid }})
                        | 연결 상태: <span id="connection-status">연결 중...</span>
                    </small>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // SSE 채팅 클래스
        class SSEChat {
            constructor(roomId, userUuid, userName) {
                this.roomId = roomId;
                this.userUuid = userUuid;
                this.userName = userName;
                this.eventSource = null;
                this.isConnected = false;
                this.reconnectAttempts = 0;
                this.maxReconnectAttempts = 5;
                this.messagesContainer = document.getElementById('messages-container');
                this.messageInput = document.getElementById('message-input');
                this.sendButton = document.getElementById('send-button');
                this.messageForm = document.getElementById('message-form');
                this.noMessagesElement = document.getElementById('no-messages');

                this.init();
            }

            init() {
                this.setupEventListeners();
                this.connect();
            }

            setupEventListeners() {
                // 메시지 전송 폼
                this.messageForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.sendMessage();
                });

                // Enter 키로 메시지 전송
                this.messageInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.sendMessage();
                    }
                });

                // 페이지 언로드 시 연결 정리
                window.addEventListener('beforeunload', () => {
                    this.disconnect();
                });
            }

            connect() {
                this.updateStatus('연결 중...', 'connecting');

                try {
                    const sseUrl = `/home/chat/server/${this.roomId}/stream`;
                    console.log('SSE 연결 시도:', sseUrl);

                    this.eventSource = new EventSource(sseUrl);

                    this.eventSource.onopen = () => {
                        console.log('✅ SSE 연결 성공');
                        this.isConnected = true;
                        this.reconnectAttempts = 0;
                        this.updateStatus('연결됨', 'connected');
                        this.enableInput();
                    };

                    this.eventSource.onmessage = (event) => {
                        this.handleMessage(event);
                    };

                    this.eventSource.addEventListener('new_message', (event) => {
                        this.handleNewMessage(event);
                    });

                    this.eventSource.addEventListener('participants_update', (event) => {
                        this.handleParticipantsUpdate(event);
                    });

                    this.eventSource.addEventListener('heartbeat', (event) => {
                        this.handleHeartbeat(event);
                    });

                    this.eventSource.onerror = (event) => {
                        console.error('❌ SSE 오류:', event);
                        this.handleError();
                    };

                } catch (error) {
                    console.error('SSE 연결 실패:', error);
                    this.handleError();
                }
            }

            handleMessage(event) {
                try {
                    const data = JSON.parse(event.data);
                    console.log('📨 SSE 메시지:', data);

                    if (data.type === 'connected') {
                        console.log('🔗 SSE 연결 확인됨');
                    }
                } catch (e) {
                    console.error('❌ JSON 파싱 오류:', e, 'Raw data:', event.data);
                }
            }

            handleNewMessage(event) {
                try {
                    const data = JSON.parse(event.data);
                    console.log('🆕 새 메시지:', data);
                    this.displayMessage(data.message);
                } catch (e) {
                    console.error('새 메시지 파싱 오류:', e);
                }
            }

            handleParticipantsUpdate(event) {
                try {
                    const data = JSON.parse(event.data);
                    console.log('👥 참여자 업데이트:', data);
                    this.updateParticipantsList(data.participants);
                } catch (e) {
                    console.error('참여자 업데이트 파싱 오류:', e);
                }
            }

            handleHeartbeat(event) {
                try {
                    const data = JSON.parse(event.data);
                    console.log('💓 하트비트:', data.timestamp);
                    document.getElementById('connection-status').textContent = '연결됨 (' + new Date(data.timestamp).toLocaleTimeString() + ')';
                } catch (e) {
                    console.error('하트비트 파싱 오류:', e);
                }
            }

            handleError() {
                this.isConnected = false;
                this.updateStatus('연결 실패', 'error');
                this.disableInput();

                if (this.eventSource) {
                    this.eventSource.close();
                }

                // 재연결 시도
                if (this.reconnectAttempts < this.maxReconnectAttempts) {
                    this.reconnectAttempts++;
                    console.log(`🔄 재연결 시도 ${this.reconnectAttempts}/${this.maxReconnectAttempts}`);
                    setTimeout(() => {
                        this.connect();
                    }, 3000 * this.reconnectAttempts);
                } else {
                    console.log('❌ 최대 재연결 시도 횟수 초과');
                    this.updateStatus('연결 실패 - 페이지를 새로고침하세요', 'error');
                }
            }

            updateStatus(status, type) {
                const statusElement = document.getElementById('sse-status');
                const statusText = document.getElementById('status-text');

                statusText.textContent = status;
                statusElement.className = 'sse-status';

                const alertElement = statusElement.querySelector('.alert');
                alertElement.className = 'alert';

                switch (type) {
                    case 'connecting':
                        alertElement.classList.add('alert-info', 'connecting');
                        break;
                    case 'connected':
                        alertElement.classList.add('alert-success');
                        setTimeout(() => {
                            statusElement.style.display = 'none';
                        }, 3000);
                        break;
                    case 'error':
                        alertElement.classList.add('alert-danger');
                        break;
                }

                document.getElementById('connection-status').textContent = status;
            }

            enableInput() {
                this.messageInput.disabled = false;
                this.sendButton.disabled = false;
                this.messageInput.focus();
            }

            disableInput() {
                this.messageInput.disabled = true;
                this.sendButton.disabled = true;
            }

            async sendMessage() {
                const content = this.messageInput.value.trim();
                if (!content || !this.isConnected) {
                    return;
                }

                try {
                    this.sendButton.disabled = true;
                    this.sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                    const response = await fetch(`/home/chat/server/${this.roomId}/send`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({ content: content })
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.messageInput.value = '';
                        console.log('✅ 메시지 전송 성공:', result);
                    } else {
                        console.error('❌ 메시지 전송 실패:', result.message);
                        this.showError('메시지 전송 실패: ' + result.message);
                    }

                } catch (error) {
                    console.error('메시지 전송 오류:', error);
                    this.showError('메시지 전송 중 오류가 발생했습니다.');
                } finally {
                    this.sendButton.disabled = false;
                    this.sendButton.innerHTML = '<i class="fas fa-paper-plane"></i>';
                    this.messageInput.focus();
                }
            }

            displayMessage(message) {
                // "메시지 없음" 텍스트 숨기기
                if (this.noMessagesElement) {
                    this.noMessagesElement.style.display = 'none';
                }

                const messageElement = document.createElement('div');
                messageElement.className = `sse-message ${message.is_mine ? 'mine' : 'others'}`;

                const bubbleClass = message.is_mine ? 'message-bubble' : 'message-bubble';

                messageElement.innerHTML = `
                    ${!message.is_mine ? `<div class="message-sender">${message.sender_name}</div>` : ''}
                    <div class="${bubbleClass}">
                        ${this.escapeHtml(message.content)}
                        <div class="message-time">${message.created_at}</div>
                    </div>
                `;

                this.messagesContainer.appendChild(messageElement);
                this.scrollToBottom();
            }

            updateParticipantsList(participants) {
                const participantsList = document.getElementById('participants-list');
                const participantCount = document.getElementById('participant-count');

                participantCount.textContent = participants.length;

                participantsList.innerHTML = participants.map(p => `
                    <div class="participant-item">
                        <div class="participant-avatar">
                            ${p.name.charAt(0).toUpperCase()}
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-medium">${this.escapeHtml(p.name)}</div>
                            <small class="text-muted">${p.role}</small>
                        </div>
                        <div class="online-indicator"></div>
                    </div>
                `).join('');
            }

            scrollToBottom() {
                this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
            }

            showError(message) {
                const errorElement = document.createElement('div');
                errorElement.className = 'alert alert-danger alert-dismissible fade show position-fixed';
                errorElement.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                errorElement.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
                `;
                document.body.appendChild(errorElement);

                setTimeout(() => {
                    if (errorElement.parentElement) {
                        errorElement.remove();
                    }
                }, 5000);
            }

            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            disconnect() {
                if (this.eventSource) {
                    this.eventSource.close();
                    this.eventSource = null;
                }
                this.isConnected = false;
            }
        }

        // 전역 함수들
        function toggleParticipants() {
            const panel = document.getElementById('participants-panel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }

        // 페이지 로드 시 SSE 채팅 초기화
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 SSE 채팅 시스템 초기화');

            const roomId = {{ $room->id }};
            const userUuid = '{{ $user->uuid }}';
            const userName = '{{ $user->name }}';

            // 전역 변수로 SSE 채팅 인스턴스 생성
            window.sseChat = new SSEChat(roomId, userUuid, userName);

            // 페이지 제목 업데이트
            document.title = '{{ $room->title }} - SSE 채팅';

            // 키보드 단축키
            document.addEventListener('keydown', function(e) {
                // Esc 키로 참여자 패널 닫기
                if (e.key === 'Escape') {
                    document.getElementById('participants-panel').style.display = 'none';
                }

                // Ctrl+/ 로 참여자 패널 토글
                if (e.ctrlKey && e.key === '/') {
                    e.preventDefault();
                    toggleParticipants();
                }
            });
        });
    </script>
@endpush