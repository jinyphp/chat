<div class="chat-messages-wrapper" wire:poll.1s="refreshMessages">
<div class="card h-100 border-0 d-flex flex-column">
    <!-- 채팅 메시지 컴포넌트 (Polling: 1초 간격 자동 새로고침) -->

    <!-- Card Header - 채팅방 정보 및 설정 -->
    <div class="card-header bg-white border-bottom px-3 py-3 flex-shrink-0">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                    <i class="fas fa-comments text-primary me-2"></i>
                    채팅
                    <!-- SSE 연결 상태 표시 -->
                    <span id="sse-status-indicator" class="badge bg-secondary ms-2 d-flex align-items-center">
                        <i class="fas fa-circle-notch fa-spin me-1"></i>
                        <span id="sse-status-text">연결 중</span>
                    </span>
                </h6>
                <small class="text-muted">
                    총 {{ count($messages) }}개의 메시지
                    <span id="sse-connection-details" class="ms-2"></span>
                </small>
            </div>

            <!-- 채팅방 설정 드롭다운 -->
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-cog"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" wire:click="toggleTranslations">
                        <i class="fas fa-language me-2"></i>
                        {{ $showTranslations ? '번역 숨기기' : '번역 표시' }}
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" wire:click="showBackgroundSettings">
                        <i class="fas fa-palette me-2"></i> 배경색 변경
                    </a></li>
                    <li><a class="dropdown-item" href="#" wire:click="loadMoreMessages">
                        <i class="fas fa-history me-2"></i> 이전 메시지 불러오기
                    </a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Card Body - 메시지 목록 -->
    <div class="card-body p-0 overflow-auto flex-grow-1"
         style="background-color: {{ $backgroundColor ?? '#ffffff' }}; scroll-behavior: smooth;"
         id="messages-container">
        <div class="p-3">
            @if(!empty($messages))
                @foreach($messages as $message)
                    <div class="mb-3">
                        @if($message['is_mine'])
                            <!-- 내 메시지 (오른쪽 정렬) -->
                            <div class="d-flex justify-content-end align-items-start">
                                <!-- 드롭다운 메뉴 -->
                                <div class="dropdown me-2 mt-1">
                                    <button class="btn btn-sm btn-link text-muted p-1" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="#" onclick="copyMessageContent({{ $message['id'] }})">
                                            <i class="fas fa-copy me-2"></i> Copy
                                        </a></li>
                                        <li><a class="dropdown-item" href="#" wire:click="replyToMessage({{ $message['id'] }})">
                                            <i class="fas fa-reply me-2"></i> Reply
                                        </a></li>
                                        <li><a class="dropdown-item" href="#" wire:click="forwardMessage({{ $message['id'] }})">
                                            <i class="fas fa-share me-2"></i> Forward
                                        </a></li>
                                        <li><a class="dropdown-item" href="#" wire:click="toggleFavourite({{ $message['id'] }})">
                                            @if(in_array($message['id'], $favouriteMessages))
                                                <i class="fas fa-star me-2 text-warning"></i> 즐겨찾기 해제
                                            @else
                                                <i class="far fa-star me-2"></i> 즐겨찾기
                                            @endif
                                        </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" wire:click="deleteMessage({{ $message['id'] }})"
                                               onclick="return confirm('메시지를 삭제하시겠습니까?')">
                                            <i class="fas fa-trash me-2"></i> Delete
                                        </a></li>
                                    </ul>
                                </div>

                                <!-- 메시지 컨텐츠 -->
                                <div class="text-end d-flex flex-column align-items-end" style="max-width: 70%;">
                                    <div class="px-3 py-2 rounded-3 shadow-sm d-inline-block"
                                         style="background-color: #fff3cd; color: #664d03; border: 1px solid #ffe69c;"
                                         id="message-{{ $message['id'] }}">
                                        @if(isset($message['reply_to']))
                                            <!-- 답장 원본 메시지 미리보기 -->
                                            <div class="bg-white bg-opacity-50 border-start border-primary border-2 ps-2 py-1 mb-2 rounded-end cursor-pointer"
                                                 style="font-size: 0.85rem;"
                                                 onclick="scrollToMessage({{ $message['reply_to']['id'] }})"
                                                 title="원본 메시지로 이동">
                                                <div class="text-primary fw-bold" style="font-size: 0.75rem;">{{ $message['reply_to']['sender_name'] }}님에게 답장</div>
                                                <div class="text-muted">{{ strlen($message['reply_to']['content']) > 50 ? substr($message['reply_to']['content'], 0, 50) . '...' : $message['reply_to']['content'] }}</div>
                                            </div>
                                        @endif
                                        @if($message['type'] === 'text')
                                            {{ $message['content'] }}
                                        @elseif(isset($message['file']))
                                            @include('jiny-chat::livewire.partials.file-message', ['file' => $message['file']])
                                        @endif
                                    </div>
                                    <div class="mt-1">
                                        <small class="text-muted" style="font-size: 0.75rem;">{{ $message['created_at'] }}</small>
                                        @if(in_array($message['id'], $favouriteMessages))
                                            <i class="fas fa-star text-warning ms-1" style="font-size: 0.7rem;" title="즐겨찾기"></i>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @else
                            <!-- 상대방 메시지 (왼쪽 정렬) -->
                            <div class="d-flex align-items-start">
                                <!-- 아바타 -->
                                <div class="me-2 flex-shrink-0">
                                    @if($message['sender_avatar'])
                                        <img src="{{ $message['sender_avatar'] }}" alt="{{ $message['sender_name'] }}"
                                             class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">
                                    @else
                                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                             style="width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                            {{ mb_substr($message['sender_name'], 0, 1) }}
                                        </div>
                                    @endif
                                </div>

                                <!-- 메시지 컨텐츠 -->
                                <div class="d-flex flex-column" style="max-width: 70%;">
                                    <div class="d-flex align-items-center mb-1">
                                        <span class="fw-medium text-primary me-2">{{ $message['sender_name'] }}</span>
                                        <small class="text-muted" style="font-size: 0.75rem;">{{ $message['created_at'] }}</small>
                                        @if(in_array($message['id'], $favouriteMessages))
                                            <i class="fas fa-star text-warning ms-1" style="font-size: 0.7rem;" title="즐겨찾기"></i>
                                        @endif
                                    </div>
                                    <div class="bg-light border px-3 py-2 rounded-3 shadow-sm d-inline-block"
                                         style="width: fit-content;"
                                         id="message-{{ $message['id'] }}">
                                        @if(isset($message['reply_to']))
                                            <!-- 답장 원본 메시지 미리보기 -->
                                            <div class="bg-white border-start border-primary border-2 ps-2 py-1 mb-2 rounded-end cursor-pointer"
                                                 style="font-size: 0.85rem;"
                                                 onclick="scrollToMessage({{ $message['reply_to']['id'] }})"
                                                 title="원본 메시지로 이동">
                                                <div class="text-primary fw-bold" style="font-size: 0.75rem;">{{ $message['reply_to']['sender_name'] }}님에게 답장</div>
                                                <div class="text-muted">{{ strlen($message['reply_to']['content']) > 50 ? substr($message['reply_to']['content'], 0, 50) . '...' : $message['reply_to']['content'] }}</div>
                                            </div>
                                        @endif
                                        @if($message['type'] === 'text')
                                            <!-- 원본 메시지 -->
                                            <div class="message-original">
                                                {{ $message['content'] }}
                                            </div>

                                            <!-- 번역된 메시지 (다른 사람 메시지만) -->
                                            @if(!$message['is_mine'] && $showTranslations && isset($translatedMessages[$message['id']]))
                                                @php
                                                    $translation = $translatedMessages[$message['id']];
                                                    $isNewTranslation = !isset($translation['translated_at']) ||
                                                                      (isset($translation['translated_at']) &&
                                                                       \Carbon\Carbon::parse($translation['translated_at'])->diffInMinutes(now()) < 5);
                                                    $translationBgColor = $isNewTranslation ? '#e8f5e8' : '#f0f8ff'; // 연한 초록 vs 연한 파랑
                                                    $translationBorderColor = $isNewTranslation ? '#28a745' : '#007bff';
                                                @endphp
                                                @if($translation['success'] && $translation['needs_translation'])
                                                    <div class="message-translation mt-2 pt-2 border-top"
                                                         style="background-color: {{ $translationBgColor }};
                                                                border-top-color: {{ $translationBorderColor }} !important;
                                                                border-radius: 4px;
                                                                padding: 8px;">
                                                        <div class="d-flex align-items-center mb-1">
                                                            <i class="fas fa-language me-1"
                                                               style="font-size: 0.7rem; color: {{ $translationBorderColor }};"></i>
                                                            <small class="fw-medium"
                                                                   style="font-size: 0.7rem; color: {{ $translationBorderColor }};">
                                                                번역 ({{ strtoupper($translation['source_language']) }} → {{ strtoupper($translation['target_language']) }})
                                                            </small>
                                                            @if($isNewTranslation)
                                                                <span class="badge bg-success ms-1" style="font-size: 0.6rem;">NEW</span>
                                                            @else
                                                                <span class="badge bg-secondary ms-1" style="font-size: 0.6rem;">CACHED</span>
                                                            @endif
                                                        </div>
                                                        <div class="text-dark">
                                                            {{ $translation['translated'] }}
                                                        </div>
                                                        @if(config('app.debug'))
                                                            <div class="mt-1">
                                                                <small class="text-muted" style="font-size: 0.6rem;">
                                                                    @if(isset($translation['translated_at']))
                                                                        번역 시간: {{ \Carbon\Carbon::parse($translation['translated_at'])->format('H:i:s') }}
                                                                    @endif
                                                                    | 제공자: {{ $translation['provider'] ?? 'google' }}
                                                                </small>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif
                                            @endif
                                        @elseif(isset($message['file']))
                                            @include('jiny-chat::livewire.partials.file-message', ['file' => $message['file']])
                                        @endif
                                    </div>
                                </div>

                                <!-- 드롭다운 메뉴 -->
                                <div class="dropdown ms-2 mt-1 flex-shrink-0">
                                    <button class="btn btn-sm btn-link text-muted p-1" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#" onclick="copyMessageContent({{ $message['id'] }})">
                                            <i class="fas fa-copy me-2"></i> Copy
                                        </a></li>
                                        <li><a class="dropdown-item" href="#" wire:click="replyToMessage({{ $message['id'] }})">
                                            <i class="fas fa-reply me-2"></i> Reply
                                        </a></li>
                                        <li><a class="dropdown-item" href="#" wire:click="forwardMessage({{ $message['id'] }})">
                                            <i class="fas fa-share me-2"></i> Forward
                                        </a></li>
                                        @if(!$message['is_mine'])
                                            <li><a class="dropdown-item" href="#" wire:click="toggleMessageTranslation({{ $message['id'] }})">
                                                @if(isset($translatedMessages[$message['id']]))
                                                    <i class="fas fa-language me-2 text-info"></i> 번역 숨기기
                                                @else
                                                    <i class="fas fa-language me-2"></i> 번역 보기
                                                @endif
                                            </a></li>
                                        @endif
                                        <li><a class="dropdown-item" href="#" wire:click="toggleFavourite({{ $message['id'] }})">
                                            @if(in_array($message['id'], $favouriteMessages))
                                                <i class="fas fa-star me-2 text-warning"></i> 즐겨찾기 해제
                                            @else
                                                <i class="far fa-star me-2"></i> 즐겨찾기
                                            @endif
                                        </a></li>
                                    </ul>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            @else
                <div class="text-center text-muted my-5">
                    <i class="fas fa-comments fa-3x mb-3 opacity-50"></i>
                    <p>아직 메시지가 없습니다.</p>
                    <p>첫 번째 메시지를 보내보세요!</p>
                </div>
            @endif
        </div>

        <!-- 타이핑 상태 표시 -->
        @if(!empty($typingUsers))
            <div class="px-3 py-2 bg-light">
                <small class="text-muted">
                    <i class="fas fa-circle-notch fa-spin me-1"></i>
                    {{ implode(', ', $typingUsers) }}님이 입력 중...
                </small>
            </div>
        @endif
    </div>

    <!-- Card Footer - 메시지 입력 영역 -->
    <div class="card-footer border-top bg-white p-3 flex-shrink-0">
        <!-- 에러 메시지 표시 -->
        @if(session()->has('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- 파일 업로드 영역 -->
        @if($showFileUpload)
            <div class="mb-3 p-3 bg-light rounded">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">파일 업로드</h6>
                    <button wire:click="toggleFileUpload" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form wire:submit.prevent="uploadFiles">
                    <div class="mb-3">
                        <input type="file"
                               wire:model="uploadedFiles"
                               multiple
                               class="form-control"
                               accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">
                        @error('uploadedFiles.*')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit"
                                class="btn btn-primary btn-sm"
                                wire:loading.attr="disabled"
                                wire:target="uploadFiles">
                            <div wire:loading.remove wire:target="uploadFiles">
                                <i class="fas fa-upload"></i> 업로드
                            </div>
                            <div wire:loading wire:target="uploadFiles">
                                <i class="fas fa-spinner fa-spin"></i> 업로드 중...
                            </div>
                        </button>
                        <button type="button"
                                wire:click="toggleFileUpload"
                                class="btn btn-secondary btn-sm">
                            취소
                        </button>
                    </div>
                </form>
                <small class="text-muted d-block mt-2">
                    <i class="fas fa-info-circle"></i>
                    최대 10MB까지 업로드 가능합니다. (이미지, 문서, 동영상, 음성 파일)
                </small>
            </div>
        @endif

        <!-- 답장 미리보기 -->
        @if($replyingTo)
            <div class="bg-light border-start border-primary border-3 p-2 mb-2 rounded-end">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <small class="text-primary fw-bold">{{ $replyMessage['sender_name'] ?? 'Unknown' }}님에게 답장</small>
                        <div class="text-muted small">
                            {{ strlen($replyMessage['content'] ?? '') > 50 ? substr($replyMessage['content'] ?? '', 0, 50) . '...' : ($replyMessage['content'] ?? '') }}
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-sm" wire:click="cancelReply"></button>
                </div>
            </div>
        @endif

        <!-- 메시지 입력 폼 -->
        <form wire:submit.prevent="sendMessage" class="d-flex gap-2 align-items-end">
            <button type="button"
                    wire:click="toggleFileUpload"
                    class="btn btn-outline-secondary d-flex align-items-center justify-content-center"
                    style="border-radius: 50%; width: 45px; height: 45px;">
                <i class="fas fa-paperclip"></i>
            </button>
            <div class="flex-grow-1">
                <input type="text"
                       wire:model.defer="newMessage"
                       wire:keydown.enter="sendMessage"
                       wire:keydown="startTyping"
                       wire:keyup="stopTyping"
                       class="form-control"
                       placeholder="메시지를 입력하세요..."
                       style="border-radius: 25px;"
                       maxlength="1000">
            </div>
            <button type="submit"
                    class="btn btn-primary d-flex align-items-center justify-content-center"
                    style="border-radius: 50%; width: 45px; height: 45px;"
                    wire:loading.attr="disabled"
                    wire:target="sendMessage">
                <div wire:loading.remove wire:target="sendMessage">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div wire:loading wire:target="sendMessage">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
            </button>
        </form>

        <!-- 디버그 정보 -->
        <div class="mt-2">
            <small class="text-muted">
                Room ID: {{ $roomId }} | User: {{ $user->name ?? 'Unknown' }} ({{ $user->uuid ?? 'No UUID' }})
            </small>
        </div>
    </div>

    <!-- 배경색 설정 모달 -->
    @if($showBackgroundModal)
        <div class="modal d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-palette text-primary"></i> 채팅방 배경색 변경
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeBackgroundSettings"></button>
                    </div>
                    <div class="modal-body">
                        <form wire:submit.prevent="updateBackgroundColor">
                            <div class="mb-3">
                                <label class="form-label">배경색 선택</label>
                                <div class="d-flex gap-2 align-items-center mb-3">
                                    <input type="color" class="form-control form-control-color" wire:model="backgroundColor">
                                    <input type="text" class="form-control" wire:model="backgroundColor" placeholder="#ffffff">
                                </div>

                                <!-- 미리 정의된 색상 팔레트 -->
                                <div class="row g-2">
                                    <div class="col-2">
                                        <div class="bg-white border rounded p-2 text-center" style="cursor: pointer;" wire:click="setBackgroundColor('#ffffff')">
                                            <small>기본</small>
                                        </div>
                                    </div>
                                    <div class="col-2">
                                        <div class="rounded p-2 text-center text-white" style="background: #e3f2fd; color: #333 !important; cursor: pointer;" wire:click="setBackgroundColor('#e3f2fd')">
                                            <small>하늘</small>
                                        </div>
                                    </div>
                                    <div class="col-2">
                                        <div class="rounded p-2 text-center text-white" style="background: #f3e5f5; color: #333 !important; cursor: pointer;" wire:click="setBackgroundColor('#f3e5f5')">
                                            <small>라벤더</small>
                                        </div>
                                    </div>
                                    <div class="col-2">
                                        <div class="rounded p-2 text-center text-white" style="background: #e8f5e8; color: #333 !important; cursor: pointer;" wire:click="setBackgroundColor('#e8f5e8')">
                                            <small>민트</small>
                                        </div>
                                    </div>
                                    <div class="col-2">
                                        <div class="rounded p-2 text-center text-white" style="background: #fff3e0; color: #333 !important; cursor: pointer;" wire:click="setBackgroundColor('#fff3e0')">
                                            <small>복숭아</small>
                                        </div>
                                    </div>
                                    <div class="col-2">
                                        <div class="rounded p-2 text-center text-white" style="background: #fce4ec; color: #333 !important; cursor: pointer;" wire:click="setBackgroundColor('#fce4ec')">
                                            <small>핑크</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-secondary" wire:click="closeBackgroundSettings">취소</button>
                                <button type="submit" class="btn btn-primary">적용</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- JavaScript and CSS -->
    <script>
        // SSE 관련 변수
        let eventSource = null;
        let reconnectTimeout = null;
        let isConnected = false;
        let connectionAttempts = 0;
        const maxReconnectAttempts = 5;

        // Livewire 컴포넌트 데이터 접근
        const roomId = @json($roomId);
        const lastMessageId = @json($this->getLastMessageId());

        // 스크롤을 하단으로 이동하는 함수
        function scrollToBottom(smooth = false) {
            const messagesContainer = document.getElementById('messages-container') || document.querySelector('.overflow-auto');
            if (messagesContainer) {
                if (smooth) {
                    messagesContainer.scrollTo({
                        top: messagesContainer.scrollHeight,
                        behavior: 'smooth'
                    });
                } else {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
            }
        }

        // SSE 연결 초기화
        function initSSE() {
            if (eventSource) {
                eventSource.close();
            }

            updateSSEConnectionStatus('connecting', '서버에 연결 중...');
            const sseUrl = `/chat/sse/${roomId}?last_message_id=${lastMessageId}`;
            console.log('SSE 연결 시도:', sseUrl);

            eventSource = new EventSource(sseUrl);

            eventSource.onopen = function(event) {
                console.log('SSE 연결 성공');
                isConnected = true;
                connectionAttempts = 0;
                updateSSEConnectionStatus('connected', '실시간 연결됨');
            };

            eventSource.onmessage = function(event) {
                try {
                    // 빈 데이터나 undefined 체크
                    if (!event.data || event.data.trim() === '' || event.data === 'undefined') {
                        console.warn('SSE 빈 데이터 수신, 무시:', event.data);
                        return;
                    }

                    const data = JSON.parse(event.data);
                    console.log('SSE 기본 메시지 수신:', data);

                    if (data.type === 'connected') {
                        showToast('실시간 채팅에 연결되었습니다.', 'success');
                    }
                } catch (e) {
                    console.error('SSE 메시지 파싱 오류:', e, 'Raw data:', event.data);
                }
            };

            // 새 메시지 이벤트
            eventSource.addEventListener('new_message', function(event) {
                try {
                    const data = JSON.parse(event.data);
                    console.log('새 메시지 수신:', data);

                    if (data.type === 'new_message') {
                        // Livewire 컴포넌트에 새 메시지 전달
                        @this.call('handleSseMessage', data.message);
                    }
                } catch (e) {
                    console.error('새 메시지 처리 오류:', e);
                }
            });

            // 타이핑 상태 업데이트
            eventSource.addEventListener('typing_update', function(event) {
                try {
                    const data = JSON.parse(event.data);
                    console.log('타이핑 상태 업데이트:', data);

                    if (data.type === 'typing_update') {
                        @this.call('updateTypingUsers', data.typing_users);
                    }
                } catch (e) {
                    console.error('타이핑 상태 처리 오류:', e);
                }
            });

            // Heartbeat 이벤트
            eventSource.addEventListener('heartbeat', function(event) {
                try {
                    const data = JSON.parse(event.data);
                    console.log('Heartbeat 수신:', data);
                    // 연결 상태 UI 업데이트 (선택적)
                } catch (e) {
                    console.error('Heartbeat 처리 오류:', e);
                }
            });

            // 오류 이벤트
            eventSource.addEventListener('error', function(event) {
                try {
                    const data = JSON.parse(event.data);
                    console.error('SSE 서버 오류:', data);
                    showToast('채팅 서버에서 오류가 발생했습니다.', 'error');
                } catch (e) {
                    console.error('SSE 오류 이벤트 처리 오류:', e);
                }
            });

            eventSource.onerror = function(event) {
                console.error('SSE 연결 오류:', event);
                isConnected = false;
                updateSSEConnectionStatus('error', '서버 연결 오류');

                if (connectionAttempts < maxReconnectAttempts) {
                    connectionAttempts++;
                    const delay = Math.min(1000 * Math.pow(2, connectionAttempts), 30000); // 최대 30초
                    console.log(`${delay}ms 후 재연결 시도 (${connectionAttempts}/${maxReconnectAttempts})`);
                    updateSSEConnectionStatus('reconnecting', `재연결 시도 ${connectionAttempts}/${maxReconnectAttempts}`);

                    reconnectTimeout = setTimeout(() => {
                        initSSE();
                    }, delay);
                } else {
                    console.error('최대 재연결 시도 횟수에 도달했습니다.');
                    updateSSEConnectionStatus('failed', '연결 실패 - 새로고침 필요');
                    showToast('채팅 서버 연결에 실패했습니다. 페이지를 새로고침하세요.', 'error');
                }
            };
        }

        // SSE 연결 상태 표시 (채팅 헤더)
        function updateSSEConnectionStatus(status, details = '') {
            const statusIndicator = document.getElementById('sse-status-indicator');
            const statusText = document.getElementById('sse-status-text');
            const connectionDetails = document.getElementById('sse-connection-details');

            if (!statusIndicator || !statusText) return;

            switch(status) {
                case 'connecting':
                    statusIndicator.className = 'badge bg-warning ms-2 d-flex align-items-center';
                    statusIndicator.innerHTML = '<i class="fas fa-circle-notch fa-spin me-1"></i><span>연결 중</span>';
                    break;
                case 'connected':
                    statusIndicator.className = 'badge bg-success ms-2 d-flex align-items-center';
                    statusIndicator.innerHTML = '<i class="fas fa-circle me-1"></i><span>연결됨</span>';
                    break;
                case 'disconnected':
                    statusIndicator.className = 'badge bg-danger ms-2 d-flex align-items-center';
                    statusIndicator.innerHTML = '<i class="fas fa-times-circle me-1"></i><span>연결 끊김</span>';
                    break;
                case 'error':
                    statusIndicator.className = 'badge bg-danger ms-2 d-flex align-items-center';
                    statusIndicator.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i><span>오류</span>';
                    break;
                case 'reconnecting':
                    statusIndicator.className = 'badge bg-warning ms-2 d-flex align-items-center';
                    statusIndicator.innerHTML = '<i class="fas fa-redo fa-spin me-1"></i><span>재연결 중</span>';
                    break;
                case 'failed':
                    statusIndicator.className = 'badge bg-danger ms-2 d-flex align-items-center';
                    statusIndicator.innerHTML = '<i class="fas fa-times me-1"></i><span>연결 실패</span>';
                    break;
            }

            if (connectionDetails) {
                connectionDetails.textContent = details;
            }

            console.log(`SSE 상태 업데이트: ${status} - ${details}`);
        }

        // 타이핑 상태 전송
        function sendTypingStatus(isTyping) {
            fetch(`/chat/sse/${roomId}/typing`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({ is_typing: isTyping })
            }).catch(error => {
                console.error('타이핑 상태 전송 오류:', error);
            });
        }

        // 페이지 언로드 시 SSE 연결 정리
        window.addEventListener('beforeunload', function() {
            updateSSEConnectionStatus('disconnected', '페이지 종료 중...');
            if (eventSource) {
                eventSource.close();
            }
            if (reconnectTimeout) {
                clearTimeout(reconnectTimeout);
            }
        });

        // 페이지 가시성 변경 시 연결 관리
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible' && !isConnected) {
                console.log('페이지가 활성화되어 SSE 재연결 시도');
                initSSE();
            } else if (document.visibilityState === 'hidden' && eventSource) {
                console.log('페이지가 비활성화되어 SSE 연결 종료');
                updateSSEConnectionStatus('disconnected', '페이지 비활성화');
                eventSource.close();
                isConnected = false;
            }
        });

        // 사용자가 스크롤 하단 근처에 있는지 확인
        function isNearBottom() {
            const messagesContainer = document.getElementById('messages-container') || document.querySelector('.overflow-auto');
            if (!messagesContainer) return true;

            const scrollTop = messagesContainer.scrollTop;
            const scrollHeight = messagesContainer.scrollHeight;
            const clientHeight = messagesContainer.clientHeight;

            // 하단에서 100px 이내이면 true
            return (scrollHeight - scrollTop - clientHeight) < 100;
        }

        // 새 메시지가 올 때 스크롤 처리
        document.addEventListener('livewire:updated', function () {
            // 사용자가 하단 근처에 있을 때만 자동 스크롤
            if (isNearBottom()) {
                setTimeout(() => scrollToBottom(), 50);
            }
        });

        // 컴포넌트 로드 시 스크롤을 맨 아래로 및 SSE 연결 시작
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => scrollToBottom(), 100);
            setTimeout(() => scrollToBottom(), 500); // 이미지 로드 등을 고려한 추가 시도

            // SSE 연결 시작
            initSSE();
        });

        // Livewire 컴포넌트 초기화 완료 시
        document.addEventListener('livewire:init', function() {
            setTimeout(() => scrollToBottom(), 100);
        });

        // Livewire 이벤트 리스너 - 타이핑 상태 전송
        document.addEventListener('send-typing-status', function(event) {
            sendTypingStatus(event.detail.is_typing);
        });

        // Livewire 이벤트 리스너 - 스크롤 하단 이동
        document.addEventListener('scroll-to-bottom', function() {
            setTimeout(() => scrollToBottom(), 50);
        });

        // 메시지 복사 버튼 클릭 함수 (onclick용)
        function copyMessageContent(messageId) {
            const messageElement = document.getElementById('message-' + messageId);
            if (messageElement) {
                const text = messageElement.innerText || messageElement.textContent;
                copyToClipboard(text);
            }
        }

        // 클립보드에 텍스트 복사
        function copyToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function() {
                    showToast('메시지가 복사되었습니다.', 'success');
                }).catch(function(err) {
                    console.error('복사 실패:', err);
                    fallbackCopyTextToClipboard(text);
                });
            } else {
                fallbackCopyTextToClipboard(text);
            }
        }

        // 구형 브라우저용 복사 함수
        function fallbackCopyTextToClipboard(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showToast('메시지가 복사되었습니다.', 'success');
                } else {
                    showToast('복사에 실패했습니다.', 'error');
                }
            } catch (err) {
                console.error('Fallback 복사 실패:', err);
                showToast('복사를 지원하지 않는 브라우저입니다.', 'error');
            }
            document.body.removeChild(textArea);
        }

        // 개선된 토스트 알림
        function showToast(message, type = 'info') {
            let bgClass = 'bg-dark';
            let iconClass = 'fas fa-info-circle';
            switch(type) {
                case 'success':
                    bgClass = 'bg-success';
                    iconClass = 'fas fa-check-circle';
                    break;
                case 'error':
                    bgClass = 'bg-danger';
                    iconClass = 'fas fa-exclamation-triangle';
                    break;
                case 'warning':
                    bgClass = 'bg-warning text-dark';
                    iconClass = 'fas fa-exclamation-circle';
                    break;
            }

            const toast = document.createElement('div');
            toast.className = `position-fixed top-0 start-50 translate-middle-x ${bgClass} text-white px-3 py-2 rounded shadow-lg d-flex align-items-center`;
            toast.style.zIndex = '9999';
            toast.style.marginTop = '20px';
            toast.style.transition = 'all 0.3s ease';
            toast.innerHTML = `
                <i class="${iconClass} me-2"></i>
                <span>${message}</span>
            `;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.transform = 'translateX(-50%) translateY(10px)';
            }, 10);

            setTimeout(function() {
                if (toast.parentNode) {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(-50%) translateY(-20px)';
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    }, 300);
                }
            }, 3000);
        }

        // 특정 메시지로 스크롤하는 함수
        function scrollToMessage(messageId) {
            const messageElement = document.getElementById('message-' + messageId);
            if (messageElement) {
                messageElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                messageElement.style.transition = 'all 0.3s ease';
                messageElement.style.boxShadow = '0 0 10px rgba(0, 123, 255, 0.5)';
                messageElement.style.transform = 'scale(1.02)';

                setTimeout(() => {
                    messageElement.style.boxShadow = '';
                    messageElement.style.transform = '';
                }, 3000);
            }
        }
    </script>

    <style>
        .cursor-pointer {
            cursor: pointer;
        }
        .cursor-pointer:hover {
            opacity: 0.8;
        }
    </style>
</div>
</div>