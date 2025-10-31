<div>

    <!-- chart footer -->
    <div class="bg-light px-4 py-3 chat-footer mt-auto">
        <!-- 에러 메시지 표시 -->
        @if (session()->has('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="bg-white p-2 rounded-3 shadow-sm">
            {{-- <form>
                <div class="position-relative">
                    <textarea class="form-control border-0 form-control-simple no-resize" placeholder="Type a New Message"
                        rows="1"></textarea>
                </div>
                <div class="position-absolute end-0 mt-n7 me-4">
                    <button type="submit" class="fs-3 btn text-primary btn-focus-none">
                        <i class="fe fe-send"></i>
                    </button>
                </div>
            </form> --}}
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
            <div class="bg-light border-start border-primary border-3 p-2 mb-2 rounded-end">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <small class="text-primary fw-bold">{{ $replyMessage['sender_name'] ?? 'Unknown' }}님에게
                            답장</small>
                        <div class="text-muted small">
                            {{ strlen($replyMessage['content'] ?? '') > 50 ? substr($replyMessage['content'] ?? '', 0, 50) . '...' : $replyMessage['content'] ?? '' }}
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-sm" wire:click="cancelReply"></button>
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

        {{--
        <div class="mt-3 d-flex">
            <div>
                <a href="#" class="text-link me-2 fs-4"><i class="bi-emoji-smile"></i></a>
                <a href="#" class="text-link me-2 fs-4"><i class="bi-paperclip"></i></a>
                <a href="#" class="text-link me-3 fs-4"><i class="bi-mic"></i></a>
            </div>
            <div class="dropdown">
                <a href="#" class="text-link fs-4" id="moreAction" data-bs-toggle="dropdown"
                    aria-haspopup="true" aria-expanded="false">
                    <i class="fe fe-more-horizontal"></i>
                </a>
                <div class="dropdown-menu" aria-labelledby="moreAction">
                    <a class="dropdown-item" href="#">Action</a>
                    <a class="dropdown-item" href="#">Another action</a>
                    <a class="dropdown-item" href="#">Something else here</a>
                </div>
            </div>
        </div> --}}





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
    </div>


    {{-- <div class="chat-write-wrapper">

    </div> --}}
</div>
