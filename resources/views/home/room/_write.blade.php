<form id="messageForm" class="d-flex align-items-end gap-2 p-3 bg-white border-top">
    @csrf
    <input type="hidden" name="room_id" value="{{ $room->id }}">

    <div class="flex-grow-1">
        <textarea id="messageInput" name="message" class="form-control resize-none" placeholder="메시지를 입력하세요..." rows="1"
            style="min-height: 38px; max-height: 120px;" required></textarea>
    </div>

    <div class="d-flex gap-2">
        {{-- 파일 첨부 버튼 (향후 구현) --}}
        <button type="button" class="btn btn-outline-secondary" title="파일 첨부" disabled>
            <i class="fas fa-paperclip"></i>
            <span class="d-none">📎</span>
        </button>

        {{-- 전송 버튼 --}}
        <button type="submit" id="sendButton" class="btn btn-primary" title="메시지 전송">
            <i class="fas fa-paper-plane"></i>
            <span class="d-none">전송</span>
        </button>
    </div>
</form>
