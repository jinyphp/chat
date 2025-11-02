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
    $showUrl = $fileId ? route('home.chat.files.show', $fileId) : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjE1MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICA8cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjhmOWZhIi8+CiAgPHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzZjNzU3ZCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkltYWdlIE5vdCBGb3VuZDwvdGV4dD4KICA8L3N2Zz4=';
    $downloadUrl = $fileId ? route('home.chat.files.download', $fileId) : '#';
    $thumbnailUrl = $fileId && $isImage ? route('home.chat.files.thumbnail', $fileId) : $showUrl;

    // 파일 아이콘 결정
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

<div class="message-bubble file-message {{ $isMine ? 'my-message' : 'other-message' }}
    {{ ($isFirstInGroup ?? false) ? 'first-in-group' : '' }}
    {{ ($isLastInGroup ?? false) ? 'last-in-group' : '' }}">
    @if($isImage)
        <!-- 이미지 파일 -->
        <div class="image-preview" onclick="showImageModal('{{ $showUrl }}', '{{ $fileName }}', '{{ $downloadUrl }}', {{ $fileId ? 'true' : 'false' }})" style="cursor: pointer;">
            <img src="{{ $thumbnailUrl }}{{ $fileId ? '?w=300&h=300' : '' }}"
                 alt="{{ $fileName }}"
                 class="img-fluid rounded"
                 loading="lazy"
                 style="max-width: 250px; max-height: 200px; object-fit: cover;"
                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjUwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICA8cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjhmOWZhIiBzdHJva2U9IiNkZWUyZTYiLz4KICA8dGV4dCB4PSI1MCUiIHk9IjUwJSIgZm9udC1mYW1pbHk9IkFyaWFsLCBzYW5zLXNlcmlmIiBmb250LXNpemU9IjE0IiBmaWxsPSIjNmM3NTdkIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+SW1hZ2UgTm90IEZvdW5kPC90ZXh0Pgo8L3N2Zz4='">

            <!-- 이미지 오버레이 -->
            <div class="image-overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center opacity-0">
                <div class="bg-dark bg-opacity-50 rounded-circle p-2">
                    <i class="fas fa-expand text-white"></i>
                </div>
            </div>
        </div>

        <!-- 이미지 정보 -->
        <div class="file-info mt-2">
            <div class="d-flex align-items-center justify-content-between">
                <div class="file-details">
                    <div class="file-name">{{ $fileName }}</div>
                    @if($fileSize > 0)
                        <div class="file-size">{{ number_format($fileSize / 1024, 1) }} KB</div>
                    @endif
                </div>
                <div class="file-actions">
                    <a href="{{ $downloadUrl }}"
                       class="download-btn"
                       download
                       title="다운로드">
                        <i class="fas fa-download"></i>
                    </a>
                </div>
            </div>
        </div>

    @else
        <!-- 일반 파일 -->
        <div class="file-card">
            <div class="file-info">
                <div class="file-icon">
                    <i class="{{ $iconClass }}"></i>
                </div>

                <div class="file-details">
                    <div class="file-name">{{ $fileName }}</div>
                    @if($fileSize > 0)
                        <div class="file-size">크기: {{ number_format($fileSize / 1024, 1) }} KB</div>
                    @endif
                </div>

                <div class="file-actions">
                    <a href="{{ $downloadUrl }}"
                       class="download-btn"
                       download
                       title="다운로드">
                        <i class="fas fa-download"></i>
                    </a>
                </div>
            </div>
        </div>
    @endif

    @if($isMine && ($isLastInGroup ?? true))
        <div class="message-time mt-2">
            <small>{{ $message['created_at'] }}</small>
        </div>
    @endif
</div>

<style>
.image-preview {
    position: relative;
    display: inline-block;
}

.image-preview:hover .image-overlay {
    opacity: 1 !important;
}

.image-overlay {
    transition: opacity 0.3s ease;
    border-radius: 8px;
}

.text-purple {
    color: #6f42c1 !important;
}

/* 내 메시지 파일 카드 스타일 */
.my-message .file-card {
    background: rgba(255, 255, 255, 0.95);
    border: none;
    color: #333;
}

/* 내 메시지 시간 표시 */
.my-message .message-time small {
    color: rgba(255, 255, 255, 0.8);
}

/* 다운로드 버튼 스타일 */
.download-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    color: #6c757d;
    text-decoration: none;
    transition: all 0.2s ease;
    font-size: 14px;
}

.download-btn:hover {
    color: #007bff;
    transform: scale(1.1);
    text-decoration: none;
}

.download-btn:active {
    transform: scale(0.95);
}

/* 파일 카드 레이아웃 개선 */
.file-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px;
    min-width: 200px;
}

.file-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.file-icon {
    font-size: 24px;
    min-width: 32px;
}

.file-details {
    flex: 1;
    min-width: 0;
}

.file-name {
    font-weight: 500;
    font-size: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.file-size {
    font-size: 12px;
    color: #6c757d;
    margin-top: 2px;
}

.file-actions {
    flex-shrink: 0;
}

/* 이미지 모달 스타일 */
.image-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
}

.image-modal.show {
    display: flex;
}

.image-modal-content {
    position: relative;
    max-width: 90vw;
    max-height: 90vh;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.image-modal-header {
    display: flex;
    justify-content: between;
    align-items: center;
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.image-modal-title {
    font-weight: 500;
    margin: 0;
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.image-modal-actions {
    display: flex;
    gap: 10px;
    margin-left: 15px;
}

.image-modal-btn {
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    padding: 5px;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.image-modal-btn:hover {
    color: #007bff;
    background: #e9ecef;
}

.image-modal-body {
    text-align: center;
    background: #000;
}

.image-modal-img {
    max-width: 100%;
    max-height: 80vh;
    object-fit: contain;
}
</style>

<!-- 이미지 확대 모달 -->
<div id="imageModal" class="image-modal" onclick="closeImageModal(event)">
    <div class="image-modal-content" onclick="event.stopPropagation()">
        <div class="image-modal-header">
            <h5 class="image-modal-title" id="modalImageTitle">이미지</h5>
            <div class="image-modal-actions">
                <a id="modalDownloadBtn" href="#" download class="image-modal-btn" title="다운로드">
                    <i class="fas fa-download"></i>
                </a>
                <button type="button" class="image-modal-btn" onclick="closeImageModal()" title="닫기">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="image-modal-body">
            <img id="modalImage" src="" alt="" class="image-modal-img">
        </div>
    </div>
</div>

<script>
/**
 * 이미지 모달 표시
 */
function showImageModal(imageUrl, fileName, downloadUrl, hasValidFile = true) {
    console.log('showImageModal called:', { imageUrl, fileName, downloadUrl, hasValidFile });

    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const modalTitle = document.getElementById('modalImageTitle');
    const modalDownloadBtn = document.getElementById('modalDownloadBtn');

    if (modal && modalImage && modalTitle && modalDownloadBtn) {
        // 파일이 유효하지 않은 경우 즉시 오류 표시
        if (!hasValidFile || imageUrl === '#') {
            modalTitle.textContent = fileName + ' (파일을 찾을 수 없음)';
            modalImage.style.display = 'none';

            const errorDiv = document.createElement('div');
            errorDiv.className = 'text-center text-muted p-4';
            errorDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle fa-3x mb-3" style="color: #ffc107;"></i><br>
                <h5>파일을 찾을 수 없습니다</h5>
                <p>파일이 삭제되었거나 존재하지 않습니다.</p>
                <small class="text-muted">파일명: ${fileName}</small>
            `;

            const modalBody = modal.querySelector('.image-modal-body');
            modalBody.innerHTML = '';
            modalBody.appendChild(errorDiv);

            modalDownloadBtn.style.display = 'none';
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            return;
        }

        // 이미지 로딩 상태 표시
        modalImage.style.display = 'none';
        modalTitle.textContent = '이미지 로딩 중...';
        modalDownloadBtn.style.display = 'inline-flex';

        // 새 이미지 객체로 미리 로드
        const img = new Image();
        img.onload = function() {
            console.log('Image loaded successfully');
            modalImage.src = imageUrl;
            modalImage.alt = fileName;
            modalImage.style.display = 'block';
            modalTitle.textContent = fileName;
        };

        img.onerror = function() {
            console.error('Failed to load image:', imageUrl);
            modalTitle.textContent = fileName + ' (이미지 로딩 실패)';
            modalImage.style.display = 'none';

            // 오류 메시지 표시
            const errorDiv = document.createElement('div');
            errorDiv.className = 'text-center text-muted p-4';
            errorDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle fa-3x mb-3" style="color: #dc3545;"></i><br>
                <h5>이미지를 불러올 수 없습니다</h5>
                <p>파일이 손상되었거나 접근 권한이 없습니다.</p>
                <small class="text-muted">URL: ${imageUrl}</small>
            `;

            const modalBody = modal.querySelector('.image-modal-body');
            modalBody.innerHTML = '';
            modalBody.appendChild(errorDiv);
        };

        img.src = imageUrl;
        modalDownloadBtn.href = downloadUrl;

        modal.classList.add('show');
        document.body.style.overflow = 'hidden'; // 스크롤 방지
    } else {
        console.error('Modal elements not found');
    }
}

/**
 * 이미지 모달 닫기
 */
function closeImageModal(event) {
    // 이벤트가 있고 모달 콘텐츠 내부 클릭인 경우 닫지 않음
    if (event && event.target.closest('.image-modal-content')) {
        return;
    }

    const modal = document.getElementById('imageModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = ''; // 스크롤 복원

        // 모달 내용 초기화
        const modalBody = modal.querySelector('.image-modal-body');
        const modalImage = document.getElementById('modalImage');
        const modalDownloadBtn = document.getElementById('modalDownloadBtn');
        if (modalBody && modalImage) {
            modalBody.innerHTML = '<img id="modalImage" src="" alt="" class="image-modal-img">';
        }
        if (modalDownloadBtn) {
            modalDownloadBtn.style.display = 'inline-flex';
            modalDownloadBtn.href = '#';
        }
    }
}

// ESC 키로 모달 닫기
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeImageModal();
    }
});
</script>
