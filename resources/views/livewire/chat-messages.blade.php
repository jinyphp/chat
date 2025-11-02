<div class="chat-messages-wrapper">
<div class="card h-100 border-0 d-flex flex-column">

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
                                        @if(isset($message['is_reply']) && $message['is_reply'] && isset($message['reply_to']))
                                            <!-- 답장 원본 메시지 미리보기 -->
                                            <div class="bg-white bg-opacity-50 border-start border-primary border-2 ps-2 py-1 mb-2 rounded-end cursor-pointer"
                                                 style="font-size: 0.85rem;"
                                                 onclick="scrollToMessage({{ $message['reply_to']['id'] }})"
                                                 title="원본 메시지로 이동">
                                                <div class="text-primary fw-bold" style="font-size: 0.75rem;">
                                                    <i class="fas fa-reply me-1"></i>{{ $message['reply_to']['sender_name'] }}님에게 답장
                                                </div>
                                                <div class="text-muted">{{ strlen($message['reply_to']['content']) > 50 ? substr($message['reply_to']['content'], 0, 50) . '...' : $message['reply_to']['content'] }}</div>
                                            </div>
                                        @endif
                                        @if($message['type'] === 'text')
                                            {{ $message['content'] }}
                                        @elseif(isset($message['file']))
                                            @include('jiny-chat::livewire.partials.file-message', [
                                                'message' => $message,
                                                'file' => $message['file'],
                                                'isMine' => $message['is_mine']
                                            ])
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
                                        @if(isset($message['is_reply']) && $message['is_reply'] && isset($message['reply_to']))
                                            <!-- 답장 원본 메시지 미리보기 -->
                                            <div class="bg-white border-start border-primary border-2 ps-2 py-1 mb-2 rounded-end cursor-pointer"
                                                 style="font-size: 0.85rem;"
                                                 onclick="scrollToMessage({{ $message['reply_to']['id'] }})"
                                                 title="원본 메시지로 이동">
                                                <div class="text-primary fw-bold" style="font-size: 0.75rem;">
                                                    <i class="fas fa-reply me-1"></i>{{ $message['reply_to']['sender_name'] }}님에게 답장
                                                </div>
                                                <div class="text-muted">{{ strlen($message['reply_to']['content']) > 50 ? substr($message['reply_to']['content'], 0, 50) . '...' : $message['reply_to']['content'] }}</div>
                                            </div>
                                        @endif
                                        @if($message['type'] === 'text')
                                            <!-- 원본 메시지 -->
                                            <div class="message-original">
                                                {{ $message['content'] }}
                                            </div>

                                            <!-- 번역된 메시지 (개별 메시지별 번역 표시) -->
                                            @if($showTranslations && isset($translatedMessages[$message['id']]))
                                                @php
                                                    $translation = $translatedMessages[$message['id']];
                                                    $isNewTranslation = !isset($translation['translated_at']) ||
                                                                      (isset($translation['translated_at']) &&
                                                                       \Carbon\Carbon::parse($translation['translated_at'])->diffInMinutes(now()) < 5);
                                                    $translationBgColor = $isNewTranslation ? '#e8f5e8' : '#f0f8ff'; // 연한 초록 vs 연한 파랑
                                                    $translationBorderColor = $isNewTranslation ? '#28a745' : '#007bff';
                                                    $badgeClass = $isNewTranslation ? 'bg-success' : 'bg-primary';
                                                    $badgeText = $isNewTranslation ? '새 번역' : '저장됨';
                                                @endphp
                                                @if($translation['success'])
                                                    <div class="message-translation mt-2 pt-2 border-top"
                                                         style="background-color: {{ $translationBgColor }};
                                                                border-top-color: {{ $translationBorderColor }} !important;
                                                                border-radius: 6px;
                                                                padding: 10px;
                                                                margin-top: 8px;">
                                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                                            <div class="d-flex align-items-center">
                                                                <i class="fas fa-language me-2"
                                                                   style="font-size: 0.8rem; color: {{ $translationBorderColor }};"></i>
                                                                <small class="fw-medium"
                                                                       style="font-size: 0.75rem; color: {{ $translationBorderColor }};">
                                                                    번역 ({{ strtoupper($translation['source_language']) }} → {{ strtoupper($translation['target_language']) }})
                                                                </small>
                                                                <span class="badge {{ $badgeClass }} ms-2" style="font-size: 0.6rem;">
                                                                    {{ $badgeText }}
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <!-- 원문과 번역문을 구분하여 표시 -->
                                                        {{-- <div class="original-message mb-2 pb-2 border-bottom" style="border-color: {{ $translationBorderColor }}33 !important;">
                                                            <small class="text-muted d-block mb-1" style="font-size: 0.7rem;">
                                                                <i class="fas fa-file-text me-1"></i>원문 ({{ strtoupper($translation['source_language']) }})
                                                            </small>
                                                            <div class="text-dark" style="font-size: 0.9rem; line-height: 1.4;">
                                                                {{ $translation['original'] }}
                                                            </div>
                                                        </div> --}}

                                                        <div class="translated-message">
                                                            <small class="text-muted d-block mb-1" style="font-size: 0.7rem;">
                                                                <i class="fas fa-language me-1"></i>번역문 ({{ strtoupper($translation['target_language']) }})
                                                            </small>
                                                            <div class="text-dark fw-medium" style="font-size: 0.95rem; line-height: 1.4;">
                                                                {{ $translation['translated'] }}
                                                            </div>
                                                        </div>

                                                        @if(config('app.debug'))
                                                            <div class="mt-2 pt-2 border-top" style="border-color: {{ $translationBorderColor }}33 !important;">
                                                                <small class="text-muted" style="font-size: 0.65rem;">
                                                                    @if(isset($translation['translated_at']))
                                                                        <i class="fas fa-clock me-1"></i>번역 시간: {{ \Carbon\Carbon::parse($translation['translated_at'])->format('H:i:s') }}
                                                                    @endif
                                                                    @if(isset($translation['provider']))
                                                                        | <i class="fas fa-cog me-1"></i>제공자: {{ $translation['provider'] }}
                                                                    @endif
                                                                </small>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif
                                            @endif
                                        @elseif(isset($message['file']))
                                            @include('jiny-chat::livewire.partials.file-message', [
                                                'message' => $message,
                                                'file' => $message['file'],
                                                'isMine' => $message['is_mine']
                                            ])
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
                                        <li><a class="dropdown-item" href="#" wire:click="toggleMessageTranslation({{ $message['id'] }})">
                                            @if(isset($translatedMessages[$message['id']]))
                                                <i class="fas fa-language me-2 text-info"></i> 이 메시지 번역 숨기기
                                            @else
                                                <i class="fas fa-language me-2"></i> 이 메시지 번역하기
                                            @endif
                                        </a></li>
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

</div>
</div>

<script>
/**
 * 원본 메시지로 스크롤하는 함수
 */
function scrollToMessage(messageId) {
    const messageElement = document.getElementById('message-' + messageId);
    if (messageElement) {
        // 부드러운 스크롤로 해당 메시지로 이동
        messageElement.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });

        // 강조 효과 (1초간 배경색 변경)
        const originalBg = messageElement.style.backgroundColor;
        messageElement.style.backgroundColor = '#fff3cd';
        messageElement.style.transition = 'background-color 0.3s ease';

        setTimeout(() => {
            messageElement.style.backgroundColor = originalBg;
        }, 1000);
    } else {
        // 메시지를 찾을 수 없는 경우
        alert('원본 메시지를 찾을 수 없습니다. 메시지가 삭제되었거나 로드되지 않았을 수 있습니다.');
    }
}

/**
 * 메시지 내용을 클립보드로 복사하는 함수
 */
function copyMessageContent(messageId) {
    const messageElement = document.getElementById('message-' + messageId);
    if (!messageElement) {
        alert('메시지를 찾을 수 없습니다.');
        return;
    }

    let textToCopy = '';

    try {
        // 메시지 타입에 따라 다르게 처리
        const messageOriginal = messageElement.querySelector('.message-original');
        if (messageOriginal) {
            // 텍스트 메시지인 경우 원본 메시지 내용만 가져오기
            textToCopy = messageOriginal.textContent.trim();
        } else {
            // 파일 메시지인 경우 파일명 가져오기
            const fileName = messageElement.querySelector('.file-name');
            if (fileName) {
                textToCopy = fileName.textContent.trim();
            } else {
                // 일반적인 경우: 답글 미리보기와 기타 UI 요소 제외하고 메시지 내용만 추출
                const messageClone = messageElement.cloneNode(true);

                // 답글 미리보기 제거
                const replyPreview = messageClone.querySelector('.bg-white.bg-opacity-50');
                if (replyPreview) {
                    replyPreview.remove();
                }

                // 파일 정보 제거
                const fileInfo = messageClone.querySelector('.file-info');
                if (fileInfo) {
                    fileInfo.remove();
                }

                // 메시지 시간 제거
                const messageTime = messageClone.querySelector('.message-time');
                if (messageTime) {
                    messageTime.remove();
                }

                textToCopy = messageClone.textContent.trim();
            }
        }

        if (!textToCopy) {
            alert('복사할 텍스트가 없습니다.');
            return;
        }

        // 클립보드 API 사용 (모던 브라우저)
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(textToCopy).then(() => {
                showCopySuccessMessage();
            }).catch(err => {
                console.error('클립보드 복사 실패:', err);
                fallbackCopyToClipboard(textToCopy);
            });
        } else {
            // 레거시 브라우저 호환성
            fallbackCopyToClipboard(textToCopy);
        }

    } catch (error) {
        console.error('메시지 복사 중 오류:', error);
        alert('메시지 복사 중 오류가 발생했습니다.');
    }
}

/**
 * 레거시 브라우저용 클립보드 복사
 */
function fallbackCopyToClipboard(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.top = '-9999px';
    textArea.style.left = '-9999px';
    textArea.setAttribute('readonly', '');

    document.body.appendChild(textArea);
    textArea.select();
    textArea.setSelectionRange(0, 99999); // 모바일 호환성

    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showCopySuccessMessage();
        } else {
            alert('클립보드 복사가 지원되지 않는 브라우저입니다.');
        }
    } catch (err) {
        console.error('클립보드 복사 실패:', err);
        alert('클립보드 복사 중 오류가 발생했습니다.');
    } finally {
        document.body.removeChild(textArea);
    }
}

/**
 * 복사 성공 메시지 표시
 */
function showCopySuccessMessage() {
    // 기존 토스트가 있다면 제거
    const existingToast = document.getElementById('copySuccessToast');
    if (existingToast) {
        existingToast.remove();
    }

    // 토스트 메시지 생성
    const toast = document.createElement('div');
    toast.id = 'copySuccessToast';
    toast.className = 'position-fixed top-0 start-50 translate-middle-x mt-3';
    toast.style.zIndex = '9999';
    toast.innerHTML = `
        <div class="toast show" role="alert">
            <div class="toast-body bg-success text-white d-flex align-items-center rounded">
                <i class="fas fa-check-circle me-2"></i>
                메시지가 클립보드에 복사되었습니다.
            </div>
        </div>
    `;

    document.body.appendChild(toast);

    // 3초 후 자동 제거
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s ease';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, 3000);
}
</script>
