<div>
    <style>
        .blink {
            animation: blink 1s infinite;
        }

        @keyframes blink {
            0%,
            50% {
                opacity: 1;
            }

            51%,
            100% {
                opacity: 0.3;
            }
        }
    </style>

    <!-- 에러 메시지 표시 -->
    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="bg-white p-2 rounded-3 shadow-sm">
        <!-- 파일 업로드 영역 -->
        @if ($showFileUpload)
            <div class="mb-3 p-3 bg-light rounded">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">파일 업로드</h6>
                    <button wire:click="toggleFileUpload" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form wire:submit.prevent="uploadFiles">
                    <div class="mb-3">
                        <input type="file" wire:model="uploadedFiles" multiple class="form-control"
                            accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">
                        @error('uploadedFiles.*')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled"
                            wire:target="uploadFiles">
                            <div wire:loading.remove wire:target="uploadFiles">
                                <i class="fas fa-upload"></i> 업로드
                            </div>
                            <div wire:loading wire:target="uploadFiles">
                                <i class="fas fa-spinner fa-spin"></i> 업로드 중...
                            </div>
                        </button>
                        <button type="button" wire:click="toggleFileUpload" class="btn btn-secondary btn-sm">
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
        @if ($replyingTo)
            <div class="mb-3">
                <div class="bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-3 p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-reply text-primary me-2"></i>
                            <span class="fw-bold text-primary">답장하기</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-circle p-1"
                                wire:click="cancelReply"
                                style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-times" style="font-size: 12px;"></i>
                        </button>
                    </div>

                    <div class="bg-white rounded-2 p-2 border border-primary border-opacity-20">
                        <div class="d-flex align-items-center mb-1">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2"
                                 style="width: 24px; height: 24px; font-size: 12px;">
                                {{ mb_substr($replyMessage['sender_name'] ?? 'U', 0, 1) }}
                            </div>
                            <span class="fw-medium text-dark" style="font-size: 14px;">
                                {{ $replyMessage['sender_name'] ?? 'Unknown' }}
                            </span>
                            <span class="text-muted ms-auto" style="font-size: 12px;">원본 메시지</span>
                        </div>

                        <div class="text-dark" style="font-size: 14px; line-height: 1.4;">
                            @if($replyMessage['type'] ?? 'text' === 'text')
                                {{ $replyMessage['content'] ?? '' }}
                            @else
                                <i class="fas fa-file me-1"></i>
                                <span class="text-muted">{{ $replyMessage['type'] ?? 'file' }} 파일</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- 메시지 입력 폼 -->
        <form wire:submit.prevent="sendMessage" class="d-flex gap-2 align-items-end">
            <button type="button" wire:click="toggleFileUpload"
                class="btn btn-outline-secondary d-flex align-items-center justify-content-center"
                style="border-radius: 50%; width: 45px; height: 45px;">
                <i class="fas fa-paperclip"></i>
            </button>
            <div class="flex-grow-1">
                <input type="text" wire:model.defer="newMessage" wire:keydown.enter="sendMessage"
                    wire:keydown="startTyping" wire:keyup="stopTyping" class="form-control" placeholder="메시지를 입력하세요..."
                    style="border-radius: 25px;" maxlength="1000">
            </div>
            <button type="submit" class="btn btn-primary d-flex align-items-center justify-content-center"
                style="border-radius: 50%; width: 45px; height: 45px;" wire:loading.attr="disabled"
                wire:target="sendMessage">
                <div wire:loading.remove wire:target="sendMessage">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div wire:loading wire:target="sendMessage">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
            </button>
        </form>

        <!-- 타이핑 표시 -->
        @if (!empty($typingUsers))
            <div class="text-muted small mt-2">
                <i class="fas fa-circle text-success blink" style="font-size: 8px;"></i>
                {{ implode(', ', $typingUsers) }}님이 입력 중...
            </div>
        @endif
    </div>

</div>
