{{-- 방 설정 폼 --}}
<form id="roomSettingsForm" enctype="multipart/form-data" data-room-id="{{ $room->id }}">
    @csrf

    <!-- 탭 네비게이션 -->
    <ul class="nav nav-tabs mb-3" id="settingsTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">
                <i class="fas fa-info-circle me-1"></i> 기본정보
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="access-tab" data-bs-toggle="tab" data-bs-target="#access" type="button" role="tab">
                <i class="fas fa-shield-alt me-1"></i> 접근설정
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="appearance-tab" data-bs-toggle="tab" data-bs-target="#appearance" type="button" role="tab">
                <i class="fas fa-palette me-1"></i> 외관설정
            </button>
        </li>
    </ul>

    <!-- 탭 콘텐츠 -->
    <div class="tab-content" id="settingsTabContent">
        <!-- 기본정보 탭 -->
        <div class="tab-pane fade show active" id="basic" role="tabpanel">
            {{-- 채팅방 제목 --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">
                    채팅방 제목 <span class="text-danger">*</span>
                </label>
                <input type="text" class="form-control" name="title" value="{{ $room->title }}"
                       placeholder="채팅방 제목을 입력하세요" required maxlength="255">
            </div>

            {{-- 채팅방 이미지 --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">채팅방 대표 이미지</label>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-center">
                            <div id="currentImage" class="mb-2">
                                @if($room->image)
                                    <img src="{{ $room->image }}" alt="Room Image"
                                         class="rounded" style="max-width: 100%; max-height: 120px;">
                                @else
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center"
                                         style="width: 120px; height: 120px; margin: 0 auto;">
                                        <i class="fas fa-image text-muted fs-3"></i>
                                    </div>
                                @endif
                            </div>
                            <small class="text-muted">현재 이미지</small>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <input type="file" class="form-control" name="room_image"
                               accept="image/*" onchange="previewRoomImage(this)">
                        <div class="form-text small">
                            JPG, PNG, GIF 파일 업로드 가능 (최대 2MB)
                        </div>
                        <div id="imagePreview" class="mt-2" style="display: none;">
                            <img id="previewImg" src="" alt="Preview"
                                 class="rounded" style="max-width: 100%; max-height: 120px;">
                            <div class="mt-1">
                                <small class="text-success">새 이미지 미리보기</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 설명 --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">설명</label>
                <textarea class="form-control" name="description" rows="3"
                          placeholder="채팅방에 대한 설명을 입력하세요" maxlength="1000">{{ $room->description }}</textarea>
            </div>

            {{-- 채팅방 타입 --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">채팅방 타입</label>
                <select class="form-select" name="type">
                    <option value="public" {{ $room->type === 'public' ? 'selected' : '' }}>
                        공개 - 누구나 검색하고 참여할 수 있습니다
                    </option>
                    <option value="private" {{ $room->type === 'private' ? 'selected' : '' }}>
                        비공개 - 초대를 통해서만 참여할 수 있습니다
                    </option>
                    <option value="group" {{ $room->type === 'group' ? 'selected' : '' }}>
                        그룹 - 소규모 그룹을 위한 채팅방입니다
                    </option>
                </select>
            </div>

            {{-- 최대 참여자 수 --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">최대 참여자 수</label>
                <input type="number" class="form-control" name="max_participants"
                       value="{{ $room->max_participants }}" min="0" max="1000" placeholder="0 (무제한)">
                <div class="form-text small">0 또는 비워두면 무제한, 2-1000명 범위에서 제한 가능</div>
            </div>
        </div>

        <!-- 접근설정 탭 -->
        <div class="tab-pane fade" id="access" role="tabpanel">
            {{-- 접근 권한 --}}
            <div class="mb-4">
                <div class="form-check mb-3">
                    <input id="is_public" type="checkbox" name="is_public" value="1"
                           {{ $room->is_public ? 'checked' : '' }} class="form-check-input">
                    <label for="is_public" class="form-check-label">
                        <div class="fw-semibold">
                            <i class="fas fa-search text-primary me-1"></i> 검색 가능
                        </div>
                        <div class="text-muted small">다른 사용자가 채팅방을 검색할 수 있습니다</div>
                    </label>
                </div>

                <div class="form-check mb-3">
                    <input id="allow_join" type="checkbox" name="allow_join" value="1"
                           {{ $room->allow_join ? 'checked' : '' }} class="form-check-input">
                    <label for="allow_join" class="form-check-label">
                        <div class="fw-semibold">
                            <i class="fas fa-door-open text-success me-1"></i> 자유 참여 허용
                        </div>
                        <div class="text-muted small">승인 없이 자유롭게 참여할 수 있습니다</div>
                    </label>
                </div>

                <div class="form-check mb-3">
                    <input id="allow_invite" type="checkbox" name="allow_invite" value="1"
                           {{ $room->allow_invite ? 'checked' : '' }} class="form-check-input">
                    <label for="allow_invite" class="form-check-label">
                        <div class="fw-semibold">
                            <i class="fas fa-user-plus text-info me-1"></i> 초대 허용
                        </div>
                        <div class="text-muted small">참여자가 다른 사용자를 초대할 수 있습니다</div>
                    </label>
                </div>
            </div>

            {{-- 비밀번호 --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">
                    <i class="fas fa-key text-warning me-1"></i> 비밀번호 (선택사항)
                </label>
                <input type="password" class="form-control" name="password"
                       placeholder="새 비밀번호 입력 (변경시에만)" minlength="4">
                <div class="form-text small">비밀번호를 설정하면 참여 시 비밀번호 입력이 필요합니다</div>
            </div>
        </div>

        <!-- 외관설정 탭 -->
        <div class="tab-pane fade" id="appearance" role="tabpanel">
            {{-- 배경색 --}}
            <div class="mb-4">
                <label class="form-label fw-semibold">배경색</label>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small">색상 선택</label>
                        <input type="color" class="form-control form-control-color w-100"
                               name="background_color" value="{{ $backgroundColor }}" style="height: 50px;">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small">색상 코드</label>
                        <input type="text" class="form-control" name="background_color_text"
                               value="{{ $backgroundColor }}" placeholder="#f8f9fa" pattern="^#[a-fA-F0-9]{6}$">
                        <div class="form-text small">16진수 색상 코드를 입력하세요 (예: #f8f9fa)</div>
                    </div>
                </div>
            </div>

            {{-- 미리보기 --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">미리보기</label>
                <div class="border rounded p-3" id="backgroundPreview" style="background-color: {{ $backgroundColor }};">
                    <div class="d-flex align-items-center mb-2">
                        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-2"
                             style="width: 32px; height: 32px;">
                            <i class="fas fa-user text-white"></i>
                        </div>
                        <div>
                            <div class="fw-semibold small">사용자 이름</div>
                            <div class="text-muted" style="font-size: 11px;">2분 전</div>
                        </div>
                    </div>
                    <div class="bg-white rounded p-2 shadow-sm">
                        <small>이것은 채팅 메시지 미리보기입니다.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 버튼 영역 -->
    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> 저장
        </button>
    </div>
</form>

<script>
// DOM이 로드된 후 이벤트 리스너 설정
document.addEventListener('DOMContentLoaded', function() {
    // 배경색 변경 시 미리보기 업데이트
    const colorInput = document.querySelector('input[name="background_color"]');
    const colorTextInput = document.querySelector('input[name="background_color_text"]');

    if (colorInput) {
        colorInput.addEventListener('change', function() {
            const color = this.value;
            if (colorTextInput) colorTextInput.value = color;
            const preview = document.getElementById('backgroundPreview');
            if (preview) preview.style.backgroundColor = color;
        });
    }

    if (colorTextInput) {
        colorTextInput.addEventListener('input', function() {
            const color = this.value;
            if (/^#[0-9A-F]{6}$/i.test(color)) {
                if (colorInput) colorInput.value = color;
                const preview = document.getElementById('backgroundPreview');
                if (preview) preview.style.backgroundColor = color;
            }
        });
    }

    // 폼 제출 이벤트 처리
    const form = document.getElementById('roomSettingsForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault(); // 기본 폼 제출 방지

            const roomId = this.getAttribute('data-room-id');
            saveRoomSettings(this, roomId);
        });
    }
});

// 채팅방 이미지 미리보기
function previewRoomImage(input) {
    const preview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');

    if (input.files && input.files[0]) {
        const file = input.files[0];

        // 파일 크기 체크 (2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('파일 크기는 2MB 이하여야 합니다.');
            input.value = '';
            preview.style.display = 'none';
            return;
        }

        // 이미지 파일 타입 체크
        if (!file.type.startsWith('image/')) {
            alert('이미지 파일만 업로드 가능합니다.');
            input.value = '';
            preview.style.display = 'none';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
    }
}

// 방 설정 저장
function saveRoomSettings(form, roomId) {
    const formData = new FormData(form);

    // background_color 필드 설정
    formData.set('background_color', document.querySelector('input[name="background_color"]').value);

    // 토큰 및 메소드 설정
    formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'));
    formData.append('_method', 'POST');

    fetch(`/home/chat/room/${roomId}/settings`, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(async response => {
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else {
            const text = await response.text();
            console.error('Non-JSON response:', text);
            throw new Error('서버에서 올바르지 않은 응답을 받았습니다.');
        }
    })
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast(data.message, 'success');
            } else {
                alert(data.message);
            }

            // 모달 닫기
            const modalElement = document.getElementById('roomSettingsModal');
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }

            // 페이지 새로고침
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            if (typeof showToast === 'function') {
                showToast(data.error || '설정 저장에 실패했습니다.', 'danger');
            } else {
                alert(data.error || '설정 저장에 실패했습니다.');
            }
        }
    })
    .catch(error => {
        console.error('Error saving room settings:', error);
        if (typeof showToast === 'function') {
            showToast('설정 저장 중 오류가 발생했습니다.', 'danger');
        } else {
            alert('설정 저장 중 오류가 발생했습니다.');
        }
    });
}
</script>