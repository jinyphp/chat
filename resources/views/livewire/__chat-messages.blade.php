<div class="chat-messages-wrapper">
    <!-- 메시지 컨테이너 -->
    <div class="chat-messages-container">
        <!-- 더 보기 버튼 -->
        @if($hasMoreMessages)
            <div class="load-more-section">
                <button wire:click="loadMoreMessages"
                        class="load-more-btn"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="loadMoreMessages">
                        이전 메시지 보기
                    </span>
                    <span wire:loading wire:target="loadMoreMessages">
                        로딩중...
                    </span>
                </button>
            </div>
        @endif

        <!-- 메시지 리스트 -->
        <div class="messages-list" id="messages-container">
            @forelse($messages as $message)
                <div class="message-item {{ $message['is_mine'] ? 'my-message' : 'other-message' }}">

                    @if(!$message['is_mine'])
                        <!-- 상대방 메시지 -->
                        <div class="message-left">
                            <div class="avatar">
                                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23007bff'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3E"
                                     alt="avatar" />
                            </div>
                            <div class="message-content">
                                <div class="message-info">
                                    <span class="sender-name">{{ $message['sender_name'] }}</span>
                                    <span class="message-time">{{ $message['created_at'] }}</span>
                                </div>
                                <div class="message-bubble bubble-left">
                                    @if($message['type'] === 'text')
                                        {!! nl2br(e($message['content'])) !!}
                                    @elseif(in_array($message['type'], ['image', 'document', 'video', 'audio', 'file']) && isset($message['file']))
                                        @include('jiny-chat::livewire.partials.file-message-simple', [
                                            'message' => $message,
                                            'isMine' => false
                                        ])
                                    @endif
                                </div>
                                <!-- 액션 버튼들 -->
                                <div class="message-actions">
                                    <button class="action-btn"
                                            wire:click="likeMessage({{ $message['id'] }})"
                                            title="좋아요">
                                        ❤️ {{ $message['likes_count'] ?? 0 }}
                                    </button>
                                    <button class="action-btn"
                                            wire:click="replyToMessage({{ $message['id'] }})"
                                            title="답글">
                                        💬
                                    </button>
                                </div>
                            </div>
                        </div>
                    @else
                        <!-- 내 메시지 -->
                        <div class="message-right">
                            <div class="message-content">
                                <div class="message-bubble bubble-right">
                                    @if($message['type'] === 'text')
                                        {!! nl2br(e($message['content'])) !!}
                                    @elseif(in_array($message['type'], ['image', 'document', 'video', 'audio', 'file']) && isset($message['file']))
                                        @include('jiny-chat::livewire.partials.file-message-simple', [
                                            'message' => $message,
                                            'isMine' => true
                                        ])
                                    @endif
                                </div>
                                <div class="message-time-right">
                                    {{ $message['created_at'] }}
                                    <button class="delete-btn"
                                            wire:click="deleteMessage({{ $message['id'] }})"
                                            wire:confirm="메시지를 삭제하시겠습니까?"
                                            title="삭제">
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="no-messages">
                    <div class="no-messages-content">
                        💬
                        <p>대화를 시작해보세요</p>
                    </div>
                </div>
            @endforelse
        </div>
    </div>

    <!-- 이미지 모달 -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">이미지 보기</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="" class="img-fluid rounded">
                </div>
                <div class="modal-footer">
                    <a id="modalDownload" href="#" class="btn btn-primary" download>
                        다운로드
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                </div>
            </div>
        </div>
    </div>

    <style>
    .chat-messages-wrapper {
        height: 100%;
        display: flex;
        flex-direction: column;
        background: #f5f7fb;
    }

    .chat-messages-container {
        flex: 1;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .load-more-section {
        padding: 15px;
        text-align: center;
        border-bottom: 1px solid #e9ecef;
    }

    .load-more-btn {
        background: #007bff;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 20px;
        font-size: 14px;
        cursor: pointer;
    }

    .load-more-btn:hover {
        background: #0056b3;
    }

    .messages-list {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    /* 스크롤바 스타일 */
    .messages-list::-webkit-scrollbar {
        width: 6px;
    }

    .messages-list::-webkit-scrollbar-track {
        background: transparent;
    }

    .messages-list::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 3px;
    }

    .message-item {
        width: 100%;
    }

    /* 상대방 메시지 */
    .message-left {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        max-width: 70%;
    }

    .avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
    }

    .avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .message-content {
        flex: 1;
    }

    .message-info {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 5px;
    }

    .sender-name {
        font-weight: 600;
        font-size: 14px;
        color: #333;
    }

    .message-time {
        font-size: 12px;
        color: #999;
    }

    .bubble-left {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 0 18px 18px 18px;
        padding: 12px 16px;
        color: #333;
        font-size: 14px;
        line-height: 1.4;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }

    /* 내 메시지 */
    .message-right {
        display: flex;
        justify-content: flex-end;
        max-width: 70%;
        margin-left: auto;
    }

    .bubble-right {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: white;
        border-radius: 18px 18px 0 18px;
        padding: 12px 16px;
        font-size: 14px;
        line-height: 1.4;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }

    .message-time-right {
        text-align: right;
        margin-top: 5px;
        font-size: 12px;
        color: #999;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
    }

    /* 액션 버튼들 */
    .message-actions {
        margin-top: 8px;
        display: flex;
        gap: 8px;
        opacity: 0;
        transition: opacity 0.2s;
    }

    .message-item:hover .message-actions {
        opacity: 1;
    }

    .action-btn, .delete-btn {
        background: none;
        border: none;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        cursor: pointer;
        background: rgba(0,0,0,0.05);
        transition: background 0.2s;
    }

    .action-btn:hover, .delete-btn:hover {
        background: rgba(0,0,0,0.1);
    }

    /* 빈 메시지 */
    .no-messages {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .no-messages-content {
        text-align: center;
        color: #999;
        font-size: 48px;
    }

    .no-messages-content p {
        font-size: 16px;
        margin-top: 10px;
    }

    /* 파일 메시지 */
    .file-message {
        max-width: 250px;
    }

    .image-preview {
        border-radius: 8px;
        overflow: hidden;
        cursor: pointer;
        max-width: 100%;
    }

    .image-preview img {
        width: 100%;
        height: auto;
        display: block;
    }

    .file-card {
        background: rgba(255,255,255,0.9);
        padding: 12px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .bubble-right .file-card {
        background: rgba(255,255,255,0.2);
        color: white;
    }

    .file-icon {
        font-size: 24px;
    }

    .file-info {
        flex: 1;
        min-width: 0;
    }

    .file-name {
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 2px;
        word-break: break-word;
    }

    .file-size {
        font-size: 12px;
        opacity: 0.7;
    }

    .file-download {
        background: none;
        border: none;
        color: inherit;
        cursor: pointer;
        padding: 4px;
        border-radius: 4px;
    }

    .file-download:hover {
        background: rgba(0,0,0,0.1);
    }

    /* 반응형 */
    @media (max-width: 768px) {
        .message-left, .message-right {
            max-width: 85%;
        }

        .messages-list {
            padding: 15px;
            gap: 12px;
        }

        .avatar {
            width: 32px;
            height: 32px;
        }

        .bubble-left, .bubble-right {
            padding: 10px 14px;
            font-size: 13px;
        }
    }

    /* 애니메이션 */
    .message-item {
        animation: fadeInUp 0.3s ease-out;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 자동 스크롤
        function scrollToBottom() {
            const container = document.getElementById('messages-container');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }

        // 초기 스크롤
        scrollToBottom();

        // Livewire 이벤트 리스너
        document.addEventListener('livewire:init', () => {
            Livewire.on('scroll-to-bottom', () => {
                setTimeout(scrollToBottom, 100);
            });
        });

        // 새 메시지 감지하여 자동 스크롤
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    scrollToBottom();
                }
            });
        });

        const messagesList = document.getElementById('messages-container');
        if (messagesList) {
            observer.observe(messagesList, {
                childList: true,
                subtree: true
            });
        }
    });

    // 이미지 모달
    function showImageModal(imageUrl, fileName, downloadUrl) {
        const modal = new bootstrap.Modal(document.getElementById('imageModal'));
        document.querySelector('#imageModal .modal-title').textContent = fileName;
        document.getElementById('modalImage').src = imageUrl;
        document.getElementById('modalDownload').href = downloadUrl;
        modal.show();
    }
    </script>
</div>