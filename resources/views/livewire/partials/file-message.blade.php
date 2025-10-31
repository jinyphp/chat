<!-- 파일 메시지 파셜 -->
@if($file['file_type'] === 'image')
    <!-- 이미지 파일 -->
    <div class="mb-2">
        @if(isset($file['thumbnail_url']))
            <img src="{{ $file['thumbnail_url'] }}"
                 alt="{{ $file['original_name'] }}"
                 class="img-fluid rounded cursor-pointer image-preview"
                 style="max-width: 200px; max-height: 200px;"
                 data-original="{{ $file['preview_url'] }}"
                 data-filename="{{ $file['original_name'] }}"
                 title="클릭하여 원본 보기">
        @else
            <img src="{{ $file['preview_url'] }}"
                 alt="{{ $file['original_name'] }}"
                 class="img-fluid rounded cursor-pointer image-preview"
                 style="max-width: 200px; max-height: 200px;"
                 data-original="{{ $file['preview_url'] }}"
                 data-filename="{{ $file['original_name'] }}"
                 title="클릭하여 확대 보기">
        @endif
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
    <a href="{{ $file['download_url'] }}"
       class="btn btn-sm btn-outline-primary"
       target="_blank"
       download="{{ $file['original_name'] }}">
        <i class="fas fa-download"></i> 다운로드
    </a>
    @if($file['file_type'] === 'image')
        <button onclick="openImageModal('{{ $file['preview_url'] }}', '{{ $file['original_name'] }}')"
                class="btn btn-sm btn-outline-info">
            <i class="fas fa-search-plus"></i> 확대
        </button>
    @endif
    @if(isset($message) && $message['is_mine'])
        <button wire:click="deleteFile('{{ $file['id'] }}')"
                class="btn btn-sm btn-outline-danger"
                onclick="return confirm('파일을 삭제하시겠습니까?')">
            <i class="fas fa-trash"></i> 삭제
        </button>
    @endif
</div>

<!-- 이미지 모달 팝업 -->
<div id="imageModal" class="image-modal" style="display: none;">
    <div class="image-modal-overlay" onclick="closeImageModal()"></div>
    <div class="image-modal-content">
        <div class="image-modal-header">
            <span id="imageModalTitle" class="image-modal-title"></span>
            <button onclick="closeImageModal()" class="image-modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="image-modal-body">
            <img id="imageModalImg" src="" alt="" class="image-modal-img">
        </div>
        <div class="image-modal-footer">
            <button onclick="downloadModalImage()" class="btn btn-sm btn-primary">
                <i class="fas fa-download"></i> 다운로드
            </button>
        </div>
    </div>
</div>

<style>
.cursor-pointer {
    cursor: pointer;
}
.cursor-pointer:hover {
    opacity: 0.8;
    transform: scale(1.02);
    transition: all 0.2s ease;
}

/* 이미지 모달 스타일 */
.image-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.image-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(5px);
}

.image-modal-content {
    position: relative;
    background: white;
    border-radius: 12px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    max-width: 90vw;
    max-height: 90vh;
    overflow: hidden;
    animation: modalFadeIn 0.3s ease-out;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.image-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

.image-modal-title {
    font-weight: 600;
    color: #374151;
    font-size: 16px;
    margin: 0;
}

.image-modal-close {
    background: none;
    border: none;
    font-size: 18px;
    color: #6b7280;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.image-modal-close:hover {
    background-color: #f3f4f6;
    color: #374151;
}

.image-modal-body {
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
}

.image-modal-img {
    max-width: 100%;
    max-height: 70vh;
    height: auto;
    border-radius: 8px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.image-modal-footer {
    padding: 16px 20px;
    border-top: 1px solid #e5e7eb;
    background: #f9fafb;
    display: flex;
    justify-content: center;
}

/* 반응형 처리 */
@media (max-width: 768px) {
    .image-modal-content {
        max-width: 95vw;
        max-height: 95vh;
        margin: 10px;
    }

    .image-modal-img {
        max-height: 60vh;
    }

    .image-modal-header,
    .image-modal-footer {
        padding: 12px 16px;
    }

    .image-modal-body {
        padding: 16px;
    }
}
</style>

<script>
// 전역 변수로 현재 이미지 정보 저장
let currentImageUrl = '';
let currentImageName = '';

// 이미지 모달 열기
function openImageModal(imageUrl, imageName) {
    currentImageUrl = imageUrl;
    currentImageName = imageName;

    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('imageModalImg');
    const modalTitle = document.getElementById('imageModalTitle');

    if (modal && modalImg && modalTitle) {
        modalImg.src = imageUrl;
        modalImg.alt = imageName;
        modalTitle.textContent = imageName;
        modal.style.display = 'flex';

        // body 스크롤 막기
        document.body.style.overflow = 'hidden';
    }
}

// 이미지 모달 닫기
function closeImageModal() {
    const modal = document.getElementById('imageModal');
    if (modal) {
        modal.style.display = 'none';
        // body 스크롤 복원
        document.body.style.overflow = '';
    }
}

// 모달에서 이미지 다운로드
function downloadModalImage() {
    if (currentImageUrl && currentImageName) {
        const link = document.createElement('a');
        link.href = currentImageUrl;
        link.download = currentImageName;
        link.target = '_blank';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// ESC 키로 모달 닫기
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeImageModal();
    }
});

// 모달 외부 클릭 시 닫기 (이미 overlay에 onclick 있지만 안전장치)
document.addEventListener('click', function(event) {
    const modal = document.getElementById('imageModal');
    if (modal && event.target === modal) {
        closeImageModal();
    }
});
</script>