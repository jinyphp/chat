@php
    $file = $message['file'];
    $fileName = $file['original_name'] ?? 'Unknown File';
    $fileSize = $file['file_size'] ?? 0;
    $fileType = $file['file_type'] ?? '';
    $fileId = $file['id'] ?? null;

    // 이미지 파일 확인
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $isImage = ($fileType === 'image') ||
              in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']) ||
              ($message['type'] === 'image');

    // URL 생성
    $showUrl = $fileId ? route('home.chat.files.show', $fileId) : '#';
    $downloadUrl = $fileId ? route('home.chat.files.download', $fileId) : '#';
    $thumbnailUrl = $fileId && $isImage ? route('home.chat.files.thumbnail', $fileId) : $showUrl;

    // 파일 아이콘
    $fileIcon = match($extension) {
        'pdf' => '📄',
        'doc', 'docx' => '📝',
        'xls', 'xlsx' => '📊',
        'ppt', 'pptx' => '📽️',
        'zip', 'rar', '7z' => '🗜️',
        'mp4', 'avi', 'mov' => '🎬',
        'mp3', 'wav', 'ogg' => '🎵',
        default => '📎'
    };
@endphp

<div class="file-message">
    @if($isImage)
        <!-- 이미지 파일 -->
        <div class="image-preview" onclick="showImageModal('{{ $showUrl }}', '{{ $fileName }}', '{{ $downloadUrl }}')">
            <img src="{{ $thumbnailUrl }}?w=250&h=250"
                 alt="{{ $fileName }}"
                 loading="lazy"
                 style="max-width: 250px; max-height: 200px; object-fit: cover;">
        </div>
        @if($fileSize > 0)
            <div style="font-size: 11px; margin-top: 5px; opacity: 0.7;">
                {{ number_format($fileSize / 1024, 1) }} KB
            </div>
        @endif
    @else
        <!-- 일반 파일 -->
        <div class="file-card">
            <div class="file-icon">{{ $fileIcon }}</div>
            <div class="file-info">
                <div class="file-name">{{ $fileName }}</div>
                @if($fileSize > 0)
                    <div class="file-size">{{ number_format($fileSize / 1024, 1) }} KB</div>
                @endif
            </div>
            <a href="{{ $downloadUrl }}" class="file-download" download title="다운로드">
                ⬇️
            </a>
        </div>
    @endif
</div>