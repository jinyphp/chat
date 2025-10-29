<!-- 파일 메시지 파셜 -->
@if($file['file_type'] === 'image')
    <!-- 이미지 파일 -->
    <div class="mb-2">
        <img src="{{ route('chat.file.preview', $file['uuid']) }}"
             alt="{{ $file['original_name'] }}"
             class="img-fluid rounded"
             style="max-width: 200px; max-height: 200px;"
             onclick="window.open(this.src, '_blank')">
    </div>
@else
    <!-- 다른 파일 타입 -->
    <div class="d-flex align-items-center mb-2">
        <i class="{{ $file['icon_class'] }} fa-2x me-3"></i>
        <div>
            <div class="fw-medium">{{ $file['original_name'] }}</div>
            <small class="text-muted">{{ $file['file_size'] }}</small>
        </div>
    </div>
@endif

<!-- 파일 액션 버튼 -->
<div class="d-flex gap-2 mt-2">
    <a href="{{ route('chat.file.download', $file['uuid']) }}"
       class="btn btn-sm btn-outline-primary" target="_blank">
        <i class="fas fa-download"></i> 다운로드
    </a>
    @if(isset($message) && $message['is_mine'])
        <button wire:click="deleteFile('{{ $file['uuid'] }}')"
                class="btn btn-sm btn-outline-danger"
                onclick="return confirm('파일을 삭제하시겠습니까?')">
            <i class="fas fa-trash"></i> 삭제
        </button>
    @endif
</div>