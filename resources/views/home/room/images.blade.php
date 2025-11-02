{{-- 채팅방 이미지 갤러리 --}}
@extends('jiny-site::layouts.home')

{{-- FontAwesome 아이콘 확실히 로드 --}}
@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="container-fluid">
        {{-- 갤러리 헤더 --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">
                    <i class="fas fa-images me-2"></i>
                    {{ $room->title }} - 이미지 갤러리
                </h4>
                <p class="text-muted mb-0">
                    총 {{ $stats['total_images'] }}개의 이미지
                    @if($stats['total_pages'] > 1)
                        ({{ $stats['current_page'] }}/{{ $stats['total_pages'] }} 페이지)
                    @endif
                </p>
            </div>
            <div>
                <a href="{{ route('home.chat.room.show', $room->id) }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>
                    채팅방으로 돌아가기
                </a>
            </div>
        </div>

        {{-- 이미지가 없는 경우 --}}
        @if($imageFiles->isEmpty())
            <div class="text-center py-5">
                <i class="fas fa-images fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">아직 공유된 이미지가 없습니다</h5>
                <p class="text-muted">채팅방에서 이미지를 업로드해보세요!</p>
                <a href="{{ route('home.chat.room.show', $room->id) }}" class="btn btn-primary">
                    <i class="fas fa-comment me-1"></i>
                    채팅방으로 이동
                </a>
            </div>
        @else
            {{-- 이미지 그리드 --}}
            <div class="row g-3">
                @foreach($imageFiles as $file)
                    @php
                        // Storage 링크를 통한 이미지 URL 생성
                        $fileUrl = asset('storage/' . $file->storage_path);
                        $downloadUrl = $fileUrl; // 동일한 URL로 다운로드

                        // 타임스탬프_파일명 형식에서 실제 파일명 추출
                        $displayName = $file->original_name;
                        if (preg_match('/^\d{10}_(.+)$/', $file->original_name, $matches)) {
                            $displayName = $matches[1];
                        }

                        // 파일명에서 업로드 시간 추출 (타임스탬프_파일명 형식)
                        $uploadTime = null;
                        if (preg_match('/^(\d{10})_/', $file->original_name, $matches)) {
                            $uploadTime = \Carbon\Carbon::createFromTimestamp($matches[1]);
                        }
                    @endphp

                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <div class="card h-100 shadow-sm image-card">
                            {{-- 이미지 --}}
                            <div class="image-container position-relative" style="height: 200px; overflow: hidden;">
                                <img src="{{ $fileUrl }}"
                                     alt="{{ $file->original_name }}"
                                     class="card-img-top h-100 w-100 object-fit-cover"
                                     style="cursor: pointer;"
                                     data-bs-toggle="modal"
                                     data-bs-target="#imageModal"
                                     data-image-url="{{ $fileUrl }}"
                                     data-image-name="{{ $file->original_name }}"
                                     data-download-url="{{ $downloadUrl }}"
                                     loading="lazy">

                                {{-- 오버레이 --}}
                                <div class="image-overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center">
                                    <i class="fas fa-search-plus text-white fs-3"></i>
                                </div>
                            </div>

                            {{-- 카드 정보 --}}
                            <div class="card-body p-3">
                                <h6 class="card-title text-truncate mb-2" title="{{ $displayName }}">
                                    {{ $displayName }}
                                </h6>

                                <div class="d-flex justify-content-between align-items-center text-muted small">
                                    <span>
                                        <i class="fas fa-clock me-1"></i>
                                        {{ $file->created_at->format('m/d H:i') }}
                                    </span>
                                    <span>
                                        <i class="fas fa-file me-1"></i>
                                        {{ strtoupper($file->extension) }}
                                    </span>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted">
                                        {{ number_format($file->file_size / 1024, 1) }} KB
                                    </small>
                                    <div class="d-flex gap-1">
                                        <a href="{{ $downloadUrl }}" class="btn btn-sm btn-outline-primary" title="다운로드">
                                            <i class="fas fa-download" aria-hidden="true"></i>
                                            <span class="visually-hidden">다운로드</span>
                                        </a>
                                        @if($isRoomOwner)
                                            <button type="button"
                                                    class="btn btn-danger btn-sm delete-image-btn"
                                                    data-file-hash="{{ $file->id }}"
                                                    data-file-name="{{ $displayName }}"
                                                    title="이미지 삭제">
                                                <i class="fas fa-trash text-white" aria-hidden="true"></i>
                                                <span class="visually-hidden">삭제</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- 페이지네이션 --}}
            @if($imageFiles->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $imageFiles->links() }}
                </div>
            @endif
        @endif
    </div>

    {{-- 이미지 모달 --}}
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">이미지 보기</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-0">
                    <img id="modalImage" src="" alt="" class="img-fluid w-100">
                </div>
                <div class="modal-footer">
                    <span id="modalImageName" class="me-auto text-muted"></span>
                    <a id="modalDownloadBtn" href="" class="btn btn-primary">
                        <i class="fas fa-download me-1" aria-hidden="true"></i>
                        다운로드
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                </div>
            </div>
        </div>
    </div>

    {{-- 삭제 확인 모달 --}}
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                        이미지 삭제 확인
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>다음 이미지를 삭제하시겠습니까?</p>
                    <p class="fw-bold text-danger" id="deleteFileName"></p>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>주의:</strong> 삭제된 이미지는 복구할 수 없습니다.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="fas fa-trash me-1"></i>
                        삭제하기
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- 갤러리 스타일 --}}
    <style>
        .image-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }

        .image-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
        }

        .image-container {
            position: relative;
            background: #f8f9fa;
        }

        .image-overlay {
            background: rgba(0,0,0,0.5);
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }

        .image-container:hover .image-overlay {
            opacity: 1;
        }

        .object-fit-cover {
            object-fit: cover;
        }

        /* FontAwesome 아이콘이 로드되지 않을 경우 대비 */
        .fas.fa-download:before {
            content: "⬇";
            font-family: inherit !important;
        }

        .fas.fa-images:before {
            content: "🖼";
            font-family: inherit !important;
        }

        .fas.fa-comment:before {
            content: "💬";
            font-family: inherit !important;
        }

        .fas.fa-arrow-left:before {
            content: "←";
            font-family: inherit !important;
        }

        .fas.fa-user:before {
            content: "👤";
            font-family: inherit !important;
        }

        .fas.fa-clock:before {
            content: "⏰";
            font-family: inherit !important;
        }

        .fas.fa-search-plus:before {
            content: "🔍";
            font-family: inherit !important;
        }

        .fas.fa-file:before {
            content: "📄";
            font-family: inherit !important;
        }

        .fas.fa-trash:before {
            content: "🗑";
            font-family: inherit !important;
        }

        .fas.fa-exclamation-triangle:before {
            content: "⚠";
            font-family: inherit !important;
        }

        /* 삭제 버튼 스타일 */
        .delete-image-btn {
            transition: transform 0.2s ease-in-out;
        }

        .delete-image-btn:hover {
            transform: scale(1.05);
        }

        /* 삭제 버튼 아이콘을 흰색으로 설정 */
        .delete-image-btn i,
        .delete-image-btn .fas,
        .delete-image-btn .fa-trash {
            color: white !important;
        }

        /* FontAwesome fallback 이모지도 흰색으로 */
        .delete-image-btn .fas.fa-trash:before {
            color: white !important;
        }
    </style>

    {{-- 갤러리 스크립트 --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 이미지 모달 이벤트
            const imageModal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            const modalImageName = document.getElementById('modalImageName');
            const modalDownloadBtn = document.getElementById('modalDownloadBtn');

            imageModal.addEventListener('show.bs.modal', function(event) {
                const trigger = event.relatedTarget;
                const imageUrl = trigger.getAttribute('data-image-url');
                const imageName = trigger.getAttribute('data-image-name');
                const downloadUrl = trigger.getAttribute('data-download-url');

                modalImage.src = imageUrl;
                modalImage.alt = imageName;
                modalImageName.textContent = imageName;
                modalDownloadBtn.href = downloadUrl;
            });

            // 삭제 모달 이벤트
            const deleteModal = document.getElementById('deleteModal');
            const deleteFileName = document.getElementById('deleteFileName');
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

            let currentFileHash = null;
            let currentFileName = null;

            // 삭제 버튼 클릭 이벤트
            document.addEventListener('click', function(event) {
                if (event.target.closest('.delete-image-btn')) {
                    event.stopPropagation(); // 이미지 모달 방지

                    const btn = event.target.closest('.delete-image-btn');
                    currentFileHash = btn.getAttribute('data-file-hash');
                    currentFileName = btn.getAttribute('data-file-name');

                    deleteFileName.textContent = currentFileName;

                    const modal = new bootstrap.Modal(deleteModal);
                    modal.show();
                }
            });

            // 삭제 확인 버튼 클릭
            confirmDeleteBtn.addEventListener('click', function() {
                if (!currentFileHash) return;

                // 버튼 비활성화 및 로딩 표시
                confirmDeleteBtn.disabled = true;
                confirmDeleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>삭제 중...';

                // DELETE 요청 전송
                fetch(`/home/chat/room/{{ $room->id }}/images/${currentFileHash}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // 성공 메시지 표시
                        alert('파일이 성공적으로 삭제되었습니다.');

                        // 모달 닫기
                        bootstrap.Modal.getInstance(deleteModal).hide();

                        // 페이지 새로고침
                        location.reload();
                    } else {
                        // 오류 메시지 표시
                        alert('삭제 실패: ' + (data.message || '알 수 없는 오류가 발생했습니다.'));
                    }
                })
                .catch(error => {
                    console.error('Delete error:', error);
                    alert('삭제 중 오류가 발생했습니다.');
                })
                .finally(() => {
                    // 버튼 복원
                    confirmDeleteBtn.disabled = false;
                    confirmDeleteBtn.innerHTML = '<i class="fas fa-trash me-1"></i>삭제하기';
                });
            });
        });
    </script>
@endsection