<div class="px-4 py-4 h-100 d-flex flex-column" style="background-color: {{ $backgroundColor ?? '#ffffff' }};">

        <!-- Card Body - 메시지 목록 -->
        <div id="messages-list" class="flex-grow-1 overflow-auto">
            @if (!empty($messages))
                @foreach ($messages as $message)

                    @if ($message['is_mine'])
                        <!-- 내 메시지 (오른쪽 정렬) -->
                        <div class="d-flex justify-content-end mb-4">
                            <div class="d-flex" style="max-width: 70%;">
                                <!-- media body -->
                                <div class="me-3 text-end">
                                    <small>{{ $message['sender_name'] }}, {{ $message['created_at'] }}</small>
                                    <div class="d-flex">
                                        <div class="me-2 mt-2">
                                            <!-- dropdown -->
                                            <div class="dropdown dropstart">
                                                <a class="text-link" href="#" role="button"
                                                    data-bs-toggle="dropdown" aria-haspopup="true"
                                                    aria-expanded="false">
                                                    <i class="fe fe-more-vertical"></i>
                                                </a>
                                                <!-- dropdown menu -->
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item" href="#"
                                                        onclick="copyMessageContent({{ $message['id'] }})">
                                                        <i class="fe fe-copy dropdown-item-icon"></i>
                                                        Copy
                                                    </a>
                                                    <a class="dropdown-item" href="#"
                                                        wire:click="replyToMessage({{ $message['id'] }})">
                                                        <i class="fe fe-corner-up-right dropdown-item-icon"></i>
                                                        Reply
                                                    </a>
                                                    <a class="dropdown-item" href="#"
                                                        wire:click="forwardMessage({{ $message['id'] }})">
                                                        <i class="fe fe-corner-up-left dropdown-item-icon"></i>
                                                        Forward
                                                    </a>
                                                    <a class="dropdown-item" href="#"
                                                        wire:click="toggleFavourite({{ $message['id'] }})">
                                                        <i class="fe fe-star dropdown-item-icon"></i>
                                                        @if (in_array($message['id'], $favouriteMessages))
                                                            즐겨찾기 해제
                                                        @else
                                                            Favourite
                                                        @endif
                                                    </a>
                                                    <a class="dropdown-item text-danger" href="#"
                                                        wire:click="deleteMessage({{ $message['id'] }})"
                                                        onclick="return confirm('메시지를 삭제하시겠습니까?')">
                                                        <i class="fe fe-trash dropdown-item-icon"></i>
                                                        Delete
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- card -->
                                        <div class="card mt-2 rounded-top-md-end-0 bg-primary text-white">
                                            <!-- card body -->
                                            <div class="card-body text-start p-3" id="message-{{ $message['id'] }}">
                                                @if (isset($message['reply_to']))
                                                    <!-- 답장 원본 메시지 미리보기 -->
                                                    <div class="bg-white bg-opacity-20 border-start border-light border-2 ps-2 py-1 mb-2 rounded-end cursor-pointer"
                                                        style="font-size: 0.85rem;"
                                                        onclick="scrollToMessage({{ $message['reply_to']['id'] }})"
                                                        title="원본 메시지로 이동">
                                                        <div class="text-light fw-bold" style="font-size: 0.75rem;">
                                                            {{ $message['reply_to']['sender_name'] }}님에게 답장</div>
                                                        <div class="text-light opacity-75">
                                                            {{ strlen($message['reply_to']['content']) > 50 ? substr($message['reply_to']['content'], 0, 50) . '...' : $message['reply_to']['content'] }}
                                                        </div>
                                                    </div>
                                                @endif
                                                @if ($message['type'] === 'text')
                                                    <p class="mb-0">{{ $message['content'] }}</p>
                                                @elseif(isset($message['file']))
                                                    <!-- 첨부 파일 표시 -->
                                                    <div class="file-attachment mt-2">
                                                        @php
                                                            $filePath = $message['file']['path'] ?? $message['file']['file_path'] ?? '';
                                                            $fileName = $message['file']['original_name'] ?? $message['file']['name'] ?? $message['file']['filename'] ?? 'Unknown File';
                                                            $fileSize = $message['file']['size'] ?? 0;
                                                            $mimeType = $message['file']['mime_type'] ?? '';
                                                            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                                                            $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                                            $fullPath = $filePath ? asset('storage/' . $filePath) : '#';
                                                        @endphp

                                                        @if($filePath)
                                                            @if($isImage)
                                                                <!-- 이미지 파일 -->
                                                                <div class="image-attachment">
                                                                    <img src="{{ $fullPath }}"
                                                                         alt="{{ $fileName }}"
                                                                         class="img-fluid rounded"
                                                                         style="max-width: 300px; max-height: 300px; cursor: pointer;"
                                                                         onclick="showImageModal('{{ $fullPath }}', '{{ $fileName }}')">
                                                                    <div class="mt-2">
                                                                        <small class="text-light d-flex align-items-center opacity-75">
                                                                            <i class="fas fa-image me-1"></i>
                                                                            {{ $fileName }}
                                                                            @if($fileSize > 0)
                                                                                <span class="ms-2">({{ number_format($fileSize / 1024, 1) }} KB)</span>
                                                                            @endif
                                                                        </small>
                                                                        <div class="mt-1">
                                                                            <a href="{{ $fullPath }}"
                                                                               download="{{ $fileName }}"
                                                                               class="btn btn-sm btn-outline-light me-2">
                                                                                <i class="fas fa-download me-1"></i>다운로드
                                                                            </a>
                                                                            <button type="button"
                                                                                    class="btn btn-sm btn-outline-light"
                                                                                    onclick="showImageModal('{{ $fullPath }}', '{{ $fileName }}')">
                                                                                <i class="fas fa-expand me-1"></i>확대보기
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @else
                                                                <!-- 일반 파일 -->
                                                                <div class="file-attachment-general p-3 border border-light rounded bg-white bg-opacity-20">
                                                                    <div class="d-flex align-items-center">
                                                                        <div class="file-icon me-3">
                                                                            @php
                                                                                $iconClass = match($extension) {
                                                                                    'pdf' => 'fas fa-file-pdf text-danger',
                                                                                    'doc', 'docx' => 'fas fa-file-word text-primary',
                                                                                    'xls', 'xlsx' => 'fas fa-file-excel text-success',
                                                                                    'ppt', 'pptx' => 'fas fa-file-powerpoint text-warning',
                                                                                    'zip', 'rar', '7z' => 'fas fa-file-archive text-secondary',
                                                                                    'mp4', 'avi', 'mov' => 'fas fa-file-video text-info',
                                                                                    'mp3', 'wav', 'ogg' => 'fas fa-file-audio text-purple',
                                                                                    default => 'fas fa-file text-light'
                                                                                };
                                                                            @endphp
                                                                            <i class="{{ $iconClass }}" style="font-size: 2rem;"></i>
                                                                        </div>
                                                                        <div class="flex-grow-1">
                                                                            <h6 class="mb-1 text-light">{{ $fileName }}</h6>
                                                                            <small class="text-light opacity-75">
                                                                                @if($fileSize > 0)
                                                                                    크기: {{ number_format($fileSize / 1024, 1) }} KB
                                                                                @endif
                                                                                @if($mimeType)
                                                                                    @if($fileSize > 0) · @endif{{ $mimeType }}
                                                                                @endif
                                                                            </small>
                                                                        </div>
                                                                        <div class="file-actions">
                                                                            <a href="{{ $fullPath }}"
                                                                               download="{{ $fileName }}"
                                                                               class="btn btn-sm btn-outline-light">
                                                                                <i class="fas fa-download me-1"></i>다운로드
                                                                            </a>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        @else
                                                            <!-- 파일 경로가 없는 경우 -->
                                                            <div class="file-attachment-error p-3 border border-light rounded bg-warning bg-opacity-20">
                                                                <div class="d-flex align-items-center">
                                                                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                                                    <div>
                                                                        <h6 class="mb-1 text-warning">{{ $fileName }}</h6>
                                                                        <small class="text-light opacity-75">파일을 찾을 수 없습니다.</small>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif
                                                @if (in_array($message['id'], $favouriteMessages))
                                                    <i class="fas fa-star text-warning mt-2" style="font-size: 0.8rem;"
                                                        title="즐겨찾기"></i>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- 내 메시지에는 아바타 표시하지 않음 -->
                            </div>
                        </div>
                    @else
                        <!-- 상대방 메시지 (왼쪽 정렬) -->
                        <div class="d-flex mb-4" style="max-width: 70%;">
                            @if ($message['sender_avatar'])
                                <img src="{{ $message['sender_avatar'] }}" alt="{{ $message['sender_name'] }}"
                                    class="rounded-circle avatar-md">
                            @else
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold avatar-md"
                                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    {{ mb_substr($message['sender_name'], 0, 1) }}
                                </div>
                            @endif

                            <!-- media body -->
                            <div class="ms-3">
                                <small>{{ $message['sender_name'] }}, {{ $message['created_at'] }}</small>
                                <div class="d-flex">
                                    <div class="card mt-2 rounded-top-md-left-0">
                                        <div class="card-body p-3" id="message-{{ $message['id'] }}">
                                            @if (isset($message['reply_to']))
                                                <!-- 답장 원본 메시지 미리보기 -->
                                                <div class="bg-white border-start border-primary border-2 ps-2 py-1 mb-2 rounded-end cursor-pointer"
                                                    style="font-size: 0.85rem;"
                                                    onclick="scrollToMessage({{ $message['reply_to']['id'] }})"
                                                    title="원본 메시지로 이동">
                                                    <div class="text-primary fw-bold" style="font-size: 0.75rem;">
                                                        {{ $message['reply_to']['sender_name'] }}님에게 답장</div>
                                                    <div class="text-muted">
                                                        {{ strlen($message['reply_to']['content']) > 50 ? substr($message['reply_to']['content'], 0, 50) . '...' : $message['reply_to']['content'] }}
                                                    </div>
                                                </div>
                                            @endif
                                            @if ($message['type'] === 'text')
                                                <!-- 원본 메시지 -->
                                                <p class="mb-0 text-dark">{{ $message['content'] }}</p>

                                                <!-- 번역된 메시지 (다른 사람 메시지만) -->
                                                @if (!$message['is_mine'] && $showTranslations && isset($translatedMessages[$message['id']]))
                                                    @php
                                                        $translation = $translatedMessages[$message['id']];
                                                        $isNewTranslation =
                                                            !isset($translation['translated_at']) ||
                                                            (isset($translation['translated_at']) &&
                                                                \Carbon\Carbon::parse(
                                                                    $translation['translated_at'],
                                                                )->diffInMinutes(now()) < 5);
                                                        $translationBgColor = $isNewTranslation ? '#e8f5e8' : '#f0f8ff'; // 연한 초록 vs 연한 파랑
                                                        $translationBorderColor = $isNewTranslation
                                                            ? '#28a745'
                                                            : '#007bff';
                                                    @endphp
                                                    @if ($translation['success'] && $translation['needs_translation'])
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
                                                                    번역
                                                                    ({{ strtoupper($translation['source_language']) }}
                                                                    →
                                                                    {{ strtoupper($translation['target_language']) }})
                                                                </small>
                                                                @if ($isNewTranslation)
                                                                    <span class="badge bg-success ms-1"
                                                                        style="font-size: 0.6rem;">NEW</span>
                                                                @else
                                                                    <span class="badge bg-secondary ms-1"
                                                                        style="font-size: 0.6rem;">CACHED</span>
                                                                @endif
                                                            </div>
                                                            <div class="text-dark">
                                                                {{ $translation['translated'] }}
                                                            </div>
                                                            @if (config('app.debug'))
                                                                <div class="mt-1">
                                                                    <small class="text-muted"
                                                                        style="font-size: 0.6rem;">
                                                                        @if (isset($translation['translated_at']))
                                                                            번역 시간:
                                                                            {{ \Carbon\Carbon::parse($translation['translated_at'])->format('H:i:s') }}
                                                                        @endif
                                                                        | 제공자:
                                                                        {{ $translation['provider'] ?? 'google' }}
                                                                    </small>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endif
                                                @endif
                                            @elseif(isset($message['file']))
                                                <!-- 첨부 파일 표시 -->
                                                <div class="file-attachment mt-2">
                                                    @php
                                                        $filePath = $message['file']['path'] ?? $message['file']['file_path'] ?? '';
                                                        $fileName = $message['file']['original_name'] ?? $message['file']['name'] ?? $message['file']['filename'] ?? 'Unknown File';
                                                        $fileSize = $message['file']['size'] ?? 0;
                                                        $mimeType = $message['file']['mime_type'] ?? '';
                                                        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                                                        $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                                        $fullPath = $filePath ? asset('storage/' . $filePath) : '#';
                                                    @endphp

                                                    @if($filePath)
                                                        @if($isImage)
                                                            <!-- 이미지 파일 -->
                                                            <div class="image-attachment">
                                                                <img src="{{ $fullPath }}"
                                                                     alt="{{ $fileName }}"
                                                                     class="img-fluid rounded"
                                                                     style="max-width: 300px; max-height: 300px; cursor: pointer;"
                                                                     onclick="showImageModal('{{ $fullPath }}', '{{ $fileName }}')">
                                                                <div class="mt-2">
                                                                    <small class="text-muted d-flex align-items-center">
                                                                        <i class="fas fa-image me-1"></i>
                                                                        {{ $fileName }}
                                                                        @if($fileSize > 0)
                                                                            <span class="ms-2">({{ number_format($fileSize / 1024, 1) }} KB)</span>
                                                                        @endif
                                                                    </small>
                                                                    <div class="mt-1">
                                                                        <a href="{{ $fullPath }}"
                                                                           download="{{ $fileName }}"
                                                                           class="btn btn-sm btn-outline-primary me-2">
                                                                            <i class="fas fa-download me-1"></i>다운로드
                                                                        </a>
                                                                        <button type="button"
                                                                                class="btn btn-sm btn-outline-secondary"
                                                                                onclick="showImageModal('{{ $fullPath }}', '{{ $fileName }}')">
                                                                            <i class="fas fa-expand me-1"></i>확대보기
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <!-- 일반 파일 -->
                                                            <div class="file-attachment-general p-3 border rounded bg-light">
                                                                <div class="d-flex align-items-center">
                                                                    <div class="file-icon me-3">
                                                                        @php
                                                                            $iconClass = match($extension) {
                                                                                'pdf' => 'fas fa-file-pdf text-danger',
                                                                                'doc', 'docx' => 'fas fa-file-word text-primary',
                                                                                'xls', 'xlsx' => 'fas fa-file-excel text-success',
                                                                                'ppt', 'pptx' => 'fas fa-file-powerpoint text-warning',
                                                                                'zip', 'rar', '7z' => 'fas fa-file-archive text-secondary',
                                                                                'mp4', 'avi', 'mov' => 'fas fa-file-video text-info',
                                                                                'mp3', 'wav', 'ogg' => 'fas fa-file-audio text-purple',
                                                                                default => 'fas fa-file text-muted'
                                                                            };
                                                                        @endphp
                                                                        <i class="{{ $iconClass }}" style="font-size: 2rem;"></i>
                                                                    </div>
                                                                    <div class="flex-grow-1">
                                                                        <h6 class="mb-1">{{ $fileName }}</h6>
                                                                        <small class="text-muted">
                                                                            @if($fileSize > 0)
                                                                                크기: {{ number_format($fileSize / 1024, 1) }} KB
                                                                            @endif
                                                                            @if($mimeType)
                                                                                @if($fileSize > 0) · @endif{{ $mimeType }}
                                                                            @endif
                                                                        </small>
                                                                    </div>
                                                                    <div class="file-actions">
                                                                        <a href="{{ $fullPath }}"
                                                                           download="{{ $fileName }}"
                                                                           class="btn btn-sm btn-primary">
                                                                            <i class="fas fa-download me-1"></i>다운로드
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    @else
                                                        <!-- 파일 경로가 없는 경우 -->
                                                        <div class="file-attachment-error p-3 border rounded bg-warning bg-opacity-10">
                                                            <div class="d-flex align-items-center">
                                                                <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                                                <div>
                                                                    <h6 class="mb-1 text-warning">{{ $fileName }}</h6>
                                                                    <small class="text-muted">파일을 찾을 수 없습니다.</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                            @if (in_array($message['id'], $favouriteMessages))
                                                <i class="fas fa-star text-warning mt-2" style="font-size: 0.8rem;"
                                                    title="즐겨찾기"></i>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="ms-2 mt-2">
                                        <!-- dropdown -->
                                        <div class="dropdown dropend">
                                            <a class="text-link" href="#" role="button" data-bs-toggle="dropdown"
                                                aria-haspopup="true" aria-expanded="false">
                                                <i class="fe fe-more-vertical"></i>
                                            </a>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#"
                                                    onclick="copyMessageContent({{ $message['id'] }})">
                                                    <i class="fe fe-copy dropdown-item-icon"></i>
                                                    Copy
                                                </a>
                                                <a class="dropdown-item" href="#"
                                                    wire:click="replyToMessage({{ $message['id'] }})">
                                                    <i class="fe fe-corner-up-right dropdown-item-icon"></i>
                                                    Reply
                                                </a>
                                                <a class="dropdown-item" href="#"
                                                    wire:click="forwardMessage({{ $message['id'] }})">
                                                    <i class="fe fe-corner-up-left dropdown-item-icon"></i>
                                                    Forward
                                                </a>
                                                @if (!$message['is_mine'])
                                                    <a class="dropdown-item" href="#"
                                                        wire:click="toggleMessageTranslation({{ $message['id'] }})">
                                                        <i class="fe fe-globe dropdown-item-icon"></i>
                                                        @if (isset($translatedMessages[$message['id']]))
                                                            번역 숨기기
                                                        @else
                                                            번역 보기
                                                        @endif
                                                    </a>
                                                @endif
                                                <a class="dropdown-item" href="#"
                                                    wire:click="toggleFavourite({{ $message['id'] }})">
                                                    <i class="fe fe-star dropdown-item-icon"></i>
                                                    @if (in_array($message['id'], $favouriteMessages))
                                                        즐겨찾기 해제
                                                    @else
                                                        Favourite
                                                    @endif
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                @endforeach
            @else
            <div class="text-center text-muted my-5" id="empty-messages">
                <i class="fas fa-comments fa-3x mb-3 opacity-50"></i>
                <p>아직 메시지가 없습니다.</p>
                <p>첫 번째 메시지를 보내보세요!</p>
            </div>
            @endif
        </div>


    <!-- 스크롤 하단 표시자 (숨김) -->
    <div id="messages-bottom" style="height: 1px;"></div>

    <!-- 새 메시지 알림 버튼 -->
    <div id="new-messages-alert" class="position-absolute bottom-0 start-50 translate-middle-x mb-3 d-none"
        style="z-index: 1000;">
        <button class="btn btn-primary btn-sm shadow-lg rounded-pill px-3 py-2" onclick="scrollToBottom(true, true)">
            <i class="fas fa-arrow-down me-1"></i>
            새 메시지가 있습니다
        </button>
    </div>

    <!-- 이미지 확대보기 모달 -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-dark">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-white" id="imageModalLabel">이미지 확대보기</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-0">
                    <img id="modalImage" src="" alt="" class="img-fluid" style="max-height: 70vh;">
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <a id="modalDownloadBtn" href="" download="" class="btn btn-primary">
                        <i class="fas fa-download me-1"></i>다운로드
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>닫기
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 타이핑 상태 표시 -->
    @if (!empty($typingUsers))
        <div class="px-3 py-2 bg-light">
            <small class="text-muted">
                <i class="fas fa-circle-notch fa-spin me-1"></i>
                {{ implode(', ', $typingUsers) }}님이 입력 중...
            </small>
        </div>
    @endif

    <!-- JavaScript and CSS -->
    <script>
        // Livewire 컴포넌트 데이터 접근
        const roomId = @json($roomId);

        let userScrolledUp = false; // 사용자가 수동으로 스크롤을 올렸는지 추적
        let isScrolling = false; // 프로그래밍 방식 스크롤 중인지 추적
        let customPollingInterval = null; // 커스텀 폴링 인터벌
        let currentPollingInterval = {{ $pollingInterval }}; // 현재 폴링 간격

        // 스크롤을 하단으로 이동하는 함수
        function scrollToBottom(smooth = true, force = false) {
            const messagesContainer = document.getElementById('messages-list');
            if (!messagesContainer) return;

            // 강제 스크롤이거나 사용자가 위로 스크롤하지 않은 경우에만 자동 스크롤
            if (force || !userScrolledUp) {
                isScrolling = true;

                if (smooth) {
                    messagesContainer.scrollTo({
                        top: messagesContainer.scrollHeight,
                        behavior: 'smooth'
                    });

                    // 부드러운 스크롤 완료 후 플래그 리셋
                    setTimeout(() => {
                        isScrolling = false;
                        userScrolledUp = false; // 자동 스크롤 후에는 플래그 리셋
                    }, 300);
                } else {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    isScrolling = false;
                    userScrolledUp = false;
                }
            }
        }

        // 사용자가 스크롤 하단 근처에 있는지 확인
        function isNearBottom() {
            const messagesContainer = document.getElementById('messages-list');
            if (!messagesContainer) return true;

            const scrollTop = messagesContainer.scrollTop;
            const scrollHeight = messagesContainer.scrollHeight;
            const clientHeight = messagesContainer.clientHeight;

            // 하단에서 50px 이내이면 true
            return (scrollHeight - scrollTop - clientHeight) < 50;
        }

        // 새 메시지 알림 버튼 표시/숨김
        function toggleNewMessageAlert(show) {
            const alertElement = document.getElementById('new-messages-alert');
            if (alertElement) {
                if (show) {
                    alertElement.classList.remove('d-none');
                    alertElement.classList.add('animate__animated', 'animate__fadeInUp');
                } else {
                    alertElement.classList.add('d-none');
                    alertElement.classList.remove('animate__animated', 'animate__fadeInUp');
                }
            }
        }

        // 스크롤 이벤트 리스너 - 사용자 스크롤 감지
        function setupScrollListener() {
            const messagesContainer = document.getElementById('messages-list');
            if (!messagesContainer) return;

            messagesContainer.addEventListener('scroll', function() {
                // 프로그래밍 방식 스크롤인 경우 무시
                if (isScrolling) return;

                // 사용자가 수동으로 위로 스크롤했는지 확인
                const wasNearBottom = !userScrolledUp;

                if (!isNearBottom()) {
                    userScrolledUp = true;
                    // 새 메시지 알림이 필요한 상황에서만 표시
                    if (wasNearBottom) {
                        // 약간의 지연 후에 알림 표시 (새 메시지가 있을 때만)
                        setTimeout(() => {
                            if (userScrolledUp && !isNearBottom()) {
                                toggleNewMessageAlert(true);
                            }
                        }, 1000);
                    }
                } else {
                    userScrolledUp = false;
                    toggleNewMessageAlert(false);
                }
            }, {
                passive: true
            });
        }

        // 커스텀 폴링 시작
        function startCustomPolling() {
            if (customPollingInterval) {
                clearInterval(customPollingInterval);
            }

            customPollingInterval = setInterval(() => {
                // Livewire 메서드 호출 - 메시지만 업데이트
                @this.call('refreshMessages').then(() => {
                    // 폴링 간격 업데이트 체크
                    const newInterval = @this.get('pollingInterval');
                    if (newInterval !== currentPollingInterval) {
                        currentPollingInterval = newInterval;
                        // 간격이 변경되면 폴링 재시작
                        startCustomPolling();
                        console.log('폴링 간격 변경:', newInterval + '초');
                    }
                });
            }, currentPollingInterval * 1000);
        }

        // 폴링 중지
        function stopCustomPolling() {
            if (customPollingInterval) {
                clearInterval(customPollingInterval);
                customPollingInterval = null;
            }
        }

        // Livewire 업데이트 시 스크롤 처리
        document.addEventListener('livewire:updated', function() {
            // 사용자가 위로 스크롤했고 새 메시지가 있는 경우
            if (userScrolledUp && !isNearBottom()) {
                // 새 메시지 알림 표시
                toggleNewMessageAlert(true);
            } else {
                // 새 메시지가 추가된 경우 하단으로 스크롤
                setTimeout(() => scrollToBottom(true), 50);
            }
        });

        // 컴포넌트 로드 시 초기화
        document.addEventListener('DOMContentLoaded', function() {
            // 초기 로드 시 스크롤을 맨 아래로 (강제)
            setTimeout(() => {
                setupScrollListener();
                scrollToBottom(false, true); // 즉시, 강제 스크롤
                startCustomPolling(); // 커스텀 폴링 시작
            }, 100);

            // 이미지 로드 완료 후 추가 스크롤
            setTimeout(() => scrollToBottom(false, true), 500);
            setTimeout(() => scrollToBottom(false, true), 1000);
        });

        // Livewire 컴포넌트 초기화 완료 시
        document.addEventListener('livewire:init', function() {
            setTimeout(() => {
                setupScrollListener();
                scrollToBottom(false, true);
                startCustomPolling(); // 커스텀 폴링 시작
            }, 100);
        });

        // 페이지 종료 시 폴링 정리
        window.addEventListener('beforeunload', function() {
            stopCustomPolling();
        });

        // 페이지 포커스/블러 시 폴링 제어
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                console.log('페이지 활성화 - 폴링 재시작');
                startCustomPolling();
            } else {
                console.log('페이지 비활성화 - 폴링 중지');
                stopCustomPolling();
            }
        });

        // Livewire 이벤트 리스너 - 스크롤 하단 이동
        document.addEventListener('scroll-to-bottom', function() {
            scrollToBottom(true, true); // 부드럽게, 강제 스크롤
        });

        // 새 메시지 전송 시 즉시 스크롤
        window.addEventListener('message-sent', function() {
            userScrolledUp = false; // 메시지 전송 시 플래그 리셋
            scrollToBottom(true, true);
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
            switch (type) {
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
            toast.className =
                `position-fixed top-0 start-50 translate-middle-x ${bgClass} text-white px-3 py-2 rounded shadow-lg d-flex align-items-center`;
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

        // 이미지 모달 표시 함수
        function showImageModal(imageSrc, imageName) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            const modalTitle = document.getElementById('imageModalLabel');
            const downloadBtn = document.getElementById('modalDownloadBtn');

            if (modal && modalImage && modalTitle && downloadBtn) {
                modalImage.src = imageSrc;
                modalImage.alt = imageName;
                modalTitle.textContent = imageName;
                downloadBtn.href = imageSrc;
                downloadBtn.download = imageName;

                // Bootstrap 모달 열기
                const bootstrapModal = new bootstrap.Modal(modal);
                bootstrapModal.show();
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

        /* 스크롤 스타일 개선 */
        #messages-list {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e0 #f7fafc;
        }

        #messages-list::-webkit-scrollbar {
            width: 6px;
        }

        #messages-list::-webkit-scrollbar-track {
            background: #f7fafc;
            border-radius: 3px;
        }

        #messages-list::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 3px;
        }

        #messages-list::-webkit-scrollbar-thumb:hover {
            background: #a0aec0;
        }

        /* 새 메시지 알림 버튼 애니메이션 */
        #new-messages-alert {
            transition: all 0.3s ease;
        }

        /* 메시지 목록 부드러운 전환 */
        .mb-3 {
            transition: all 0.2s ease;
        }

        /* 파일 첨부 스타일 */
        .file-attachment img {
            transition: transform 0.2s ease;
            border: 2px solid transparent;
        }

        .file-attachment img:hover {
            transform: scale(1.02);
            border-color: #007bff;
        }

        .file-attachment-general {
            transition: background-color 0.2s ease, transform 0.2s ease;
        }

        .file-attachment-general:hover {
            background-color: rgba(0, 123, 255, 0.1) !important;
            transform: translateY(-1px);
        }

        /* 이미지 모달 스타일 */
        #imageModal .modal-content {
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        #imageModal .modal-body img {
            transition: opacity 0.3s ease;
        }

        /* 파일 아이콘 애니메이션 */
        .file-icon i {
            transition: transform 0.2s ease;
        }

        .file-attachment-general:hover .file-icon i {
            transform: scale(1.1);
        }
    </style>


</div>
