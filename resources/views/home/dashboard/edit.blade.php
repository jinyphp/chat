{{-- 채팅방 수정 페이지 --}}
@extends('jiny-site::layouts.home')

@push('styles')
    <!-- FontAwesome 아이콘 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .content-wrapper {
            max-width: 1400px;
            margin: 0 auto;
        }
        .edit-card {
            background: white;
            border: 1px solid #e3e6f0;
            border-radius: 8px;
        }
        .tab-nav {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #e3e6f0;
            border-radius: 8px 8px 0 0;
        }
        .tab-nav-item {
            flex: 1;
            padding: 1rem;
            background: none;
            border: none;
            color: #6b7280;
            font-weight: 500;
            font-size: 0.9rem;
            border-right: 1px solid #e3e6f0;
            transition: all 0.2s;
        }
        .tab-nav-item:last-child {
            border-right: none;
        }
        .tab-nav-item:hover {
            background: #e9ecef;
            color: #374151;
        }
        .tab-nav-item.active {
            background: white;
            color: #3b82f6;
            font-weight: 600;
            border-bottom: 2px solid #3b82f6;
            margin-bottom: -1px;
        }
        .tab-content {
            display: none;
            padding: 1.5rem;
        }
        .tab-content.active {
            display: block;
        }
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .btn-cms {
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.875rem;
            border: 1px solid;
            transition: all 0.2s;
        }
        .btn-primary-cms {
            background: #3b82f6;
            border-color: #3b82f6;
            color: white;
        }
        .btn-primary-cms:hover {
            background: #2563eb;
            border-color: #2563eb;
            color: white;
        }
        .btn-secondary-cms {
            background: #6b7280;
            border-color: #6b7280;
            color: white;
        }
        .btn-secondary-cms:hover {
            background: #4b5563;
            border-color: #4b5563;
            color: white;
        }
        .sidebar-card {
            background: white;
            border: 1px solid #e3e6f0;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .sidebar-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e3e6f0;
            font-weight: 600;
            color: #374151;
        }
        .sidebar-body {
            padding: 1.5rem;
        }
        .current-image {
            width: 150px;
            height: 150px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #e3e6f0;
        }
        .image-upload-area {
            border: 2px dashed #e3e6f0;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.2s;
        }
        .image-upload-area:hover {
            border-color: #3b82f6;
            background: #f0f9ff;
        }
        .image-upload-area input[type="file"] {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
    </style>
@endpush

@section('content')
    <div class="content-wrapper px-4 py-4">
        <!-- 페이지 헤더 -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 text-dark fw-bold">채팅방 수정</h1>
                <!-- 브레드크럼 -->
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home.chat.index') }}">채팅</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('home.chat.rooms.index') }}">채팅방 목록</a></li>
                        <li class="breadcrumb-item active">채팅방 수정</li>
                    </ol>
                </nav>
            </div>
            <div>
                <a href="{{ route('home.chat.rooms.index') }}" class="btn btn-secondary-cms">
                    <i class="fas fa-arrow-left me-1"></i> 돌아가기
                </a>
            </div>
        </div>

        {{-- 오류 메시지 --}}
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <strong><i class="fas fa-exclamation-triangle"></i> 오류가 발생했습니다:</strong>
                <ul class="list-unstyled mt-2 mb-0">
                    @foreach ($errors->all() as $error)
                        <li>• {{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- 성공 메시지 --}}
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- 에러 메시지 --}}
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-exclamation-triangle"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row">
            <!-- 메인 수정 영역 -->
            <div class="col-lg-8">
                <form method="POST" action="{{ route('home.chat.rooms.update', $room->id) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="edit-card">
                        <!-- 탭 네비게이션 -->
                        <div class="tab-nav">
                            <button type="button" class="tab-nav-item active" onclick="showTab(event, 'basic')">
                                <i class="fas fa-info-circle me-1"></i> 기본 정보
                            </button>
                            <button type="button" class="tab-nav-item" onclick="showTab(event, 'access')">
                                <i class="fas fa-shield-alt me-1"></i> 접근 및 권한
                            </button>
                            <button type="button" class="tab-nav-item" onclick="showTab(event, 'features')">
                                <i class="fas fa-cogs me-1"></i> 기능 설정
                            </button>
                        </div>

                        <!-- 기본 정보 탭 -->
                        <div id="basic-tab" class="tab-content active">
                            <!-- 채팅방 이미지 -->
                            <div class="form-group">
                                <label class="form-label">채팅방 대표 이미지</label>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="current-image d-flex align-items-center justify-content-center mx-auto bg-light">
                                            @if($room->image && \Illuminate\Support\Facades\Storage::exists('public/' . $room->image))
                                                <img src="{{ asset('storage/' . $room->image) }}"
                                                     alt="{{ $room->title }}"
                                                     class="current-image">
                                            @else
                                                <i class="fas fa-image text-muted fs-2"></i>
                                            @endif
                                        </div>
                                        <div class="text-center mt-2">
                                            <small class="text-muted">현재 이미지</small>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="image-upload-area position-relative">
                                            <i class="fas fa-cloud-upload-alt fs-4 text-muted mb-2"></i>
                                            <p class="text-muted mb-2">이미지를 드래그하거나 클릭하여 업로드</p>
                                            <input type="file" class="form-control" name="room_image"
                                                   accept="image/*" onchange="previewRoomImage(this)">
                                            <small class="text-muted">JPG, PNG, GIF (최대 2MB)</small>
                                        </div>
                                        <div id="imagePreview" class="mt-3" style="display: none;">
                                            <img id="previewImg" src="" alt="Preview" class="current-image d-block mx-auto">
                                            <div class="text-center mt-2">
                                                <small class="text-success">새 이미지 미리보기</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 채팅방 제목 -->
                            <div class="form-group">
                                <label for="title" class="form-label">
                                    채팅방 제목 <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       name="title"
                                       id="title"
                                       value="{{ old('title', $room->title) }}"
                                       required
                                       maxlength="255"
                                       placeholder="채팅방 제목을 입력하세요"
                                       class="form-control @error('title') is-invalid @enderror">
                                @error('title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">채팅방을 식별할 수 있는 제목을 입력하세요.</small>
                            </div>

                            <!-- 채팅방 슬러그 -->
                            <div class="form-group">
                                <label for="slug" class="form-label">
                                    채팅방 슬러그 (URL)
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="fas fa-link text-muted"></i>
                                    </span>
                                    <input type="text"
                                           name="slug"
                                           id="slug"
                                           value="{{ old('slug', $room->slug) }}"
                                           maxlength="255"
                                           placeholder="영문-소문자-하이픈"
                                           pattern="^[a-z0-9-]+$"
                                           class="form-control @error('slug') is-invalid @enderror">
                                </div>
                                @error('slug')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">
                                    영문 소문자, 숫자, 하이픈(-)만 사용 가능합니다. 비워두면 제목을 기반으로 자동 생성됩니다.
                                </small>
                            </div>

                            <!-- 설명 -->
                            <div class="form-group">
                                <label for="description" class="form-label">
                                    설명
                                </label>
                                <textarea name="description"
                                          id="description"
                                          rows="3"
                                          maxlength="1000"
                                          placeholder="채팅방에 대한 설명을 입력하세요 (선택사항)"
                                          class="form-control @error('description') is-invalid @enderror">{{ old('description', $room->description) }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">채팅방의 목적이나 규칙을 설명해주세요.</small>
                            </div>

                            <!-- 채팅방 타입 -->
                            <div class="form-group">
                                <label class="form-label">채팅방 타입</label>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="form-check p-3 border rounded">
                                            <input id="type_public"
                                                   name="type"
                                                   type="radio"
                                                   value="public"
                                                   {{ old('type', $room->type) === 'public' ? 'checked' : '' }}
                                                   class="form-check-input">
                                            <label for="type_public" class="form-check-label">
                                                <div class="fw-semibold">
                                                    <i class="fas fa-globe text-success"></i> 공개 채팅방
                                                </div>
                                                <div class="text-muted small">누구나 검색하고 참여할 수 있습니다.</div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check p-3 border rounded">
                                            <input id="type_private"
                                                   name="type"
                                                   type="radio"
                                                   value="private"
                                                   {{ old('type', $room->type) === 'private' ? 'checked' : '' }}
                                                   class="form-check-input">
                                            <label for="type_private" class="form-check-label">
                                                <div class="fw-semibold">
                                                    <i class="fas fa-lock text-warning"></i> 비공개 채팅방
                                                </div>
                                                <div class="text-muted small">초대를 통해서만 참여할 수 있습니다.</div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check p-3 border rounded">
                                            <input id="type_group"
                                                   name="type"
                                                   type="radio"
                                                   value="group"
                                                   {{ old('type', $room->type) === 'group' ? 'checked' : '' }}
                                                   class="form-check-input">
                                            <label for="type_group" class="form-check-label">
                                                <div class="fw-semibold">
                                                    <i class="fas fa-users text-info"></i> 그룹 채팅방
                                                </div>
                                                <div class="text-muted small">소규모 그룹을 위한 채팅방입니다.</div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 접근 및 권한 탭 -->
                        <div id="access-tab" class="tab-content">
                            <div class="form-group">
                                <label class="form-label">접근 설정</label>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-check p-3 border rounded h-100">
                                            <input id="is_public"
                                                   name="is_public"
                                                   type="checkbox"
                                                   value="1"
                                                   {{ old('is_public', $room->is_public) ? 'checked' : '' }}
                                                   class="form-check-input">
                                            <label for="is_public" class="form-check-label">
                                                <div class="fw-semibold">
                                                    <i class="fas fa-search text-primary"></i> 검색 가능
                                                </div>
                                                <div class="text-muted small">다른 사용자가 채팅방을 검색할 수 있습니다.</div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check p-3 border rounded h-100">
                                            <input id="allow_join"
                                                   name="allow_join"
                                                   type="checkbox"
                                                   value="1"
                                                   {{ old('allow_join', $room->allow_join) ? 'checked' : '' }}
                                                   class="form-check-input">
                                            <label for="allow_join" class="form-check-label">
                                                <div class="fw-semibold">
                                                    <i class="fas fa-door-open text-success"></i> 자유 참여 허용
                                                </div>
                                                <div class="text-muted small">승인 없이 자유롭게 참여할 수 있습니다.</div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check p-3 border rounded h-100">
                                            <input id="allow_invite"
                                                   name="allow_invite"
                                                   type="checkbox"
                                                   value="1"
                                                   {{ old('allow_invite', $room->allow_invite) ? 'checked' : '' }}
                                                   class="form-check-input">
                                            <label for="allow_invite" class="form-check-label">
                                                <div class="fw-semibold">
                                                    <i class="fas fa-user-plus text-info"></i> 초대 허용
                                                </div>
                                                <div class="text-muted small">참여자가 다른 사용자를 초대할 수 있습니다.</div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded h-100">
                                            <label for="password" class="form-label fw-semibold mb-2">
                                                <i class="fas fa-key text-warning"></i> 비밀번호 (선택사항)
                                            </label>
                                            <input type="password"
                                                   name="password"
                                                   id="password"
                                                   minlength="4"
                                                   placeholder="새 비밀번호 (변경시에만 입력)"
                                                   class="form-control @error('password') is-invalid @enderror">
                                            @error('password')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="form-text text-muted">
                                                @if($room->password)
                                                    현재 비밀번호가 설정되어 있습니다. 변경시에만 새 비밀번호를 입력하세요.
                                                @else
                                                    비밀번호를 설정하지 않으면 누구나 참여할 수 있습니다.
                                                @endif
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 기능 설정 탭 -->
                        <div id="features-tab" class="tab-content">
                            <div class="form-group">
                                <label for="max_participants" class="form-label">
                                    최대 참여자 수
                                </label>
                                <input type="number"
                                       name="max_participants"
                                       id="max_participants"
                                       value="{{ old('max_participants', $room->max_participants) }}"
                                       min="0"
                                       max="1000"
                                       placeholder="0 (무제한)"
                                       class="form-control @error('max_participants') is-invalid @enderror">
                                @error('max_participants')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">0 또는 비워두면 무제한, 2-1000명 범위에서 제한 가능</small>
                            </div>
                        </div>

                        <!-- 제출 버튼 -->
                        <div class="d-flex gap-3 justify-content-end mt-4 p-3 border-top">
                            <a href="{{ route('home.chat.rooms.index') }}"
                               class="btn btn-secondary-cms">
                                <i class="fas fa-arrow-left"></i> 취소
                            </a>
                            <button type="submit"
                                    class="btn btn-primary-cms">
                                <i class="fas fa-save"></i> 채팅방 수정
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 사이드바 -->
            <div class="col-lg-4">
                <div class="sidebar-card">
                    <div class="sidebar-header">
                        <i class="fas fa-info-circle"></i> 수정 정보
                    </div>
                    <div class="sidebar-body">
                        <div class="text-muted">
                            <p><strong>채팅방 정보:</strong></p>
                            <p class="small">
                                • 채팅방 ID: {{ $room->id }}<br>
                                • 채팅방 코드: {{ $room->code }}<br>
                                • 생성일: {{ $room->created_at->format('Y-m-d H:i') }}<br>
                                • 최근 수정: {{ $room->updated_at->format('Y-m-d H:i') }}
                            </p>
                            <hr>
                            <p><strong>이미지 업로드:</strong></p>
                            <p class="small">
                                • JPG, PNG, GIF 형식 지원<br>
                                • 최대 파일 크기: 2MB<br>
                                • 권장 크기: 400x400px<br>
                                • 새 이미지 업로드시 기존 이미지 교체
                            </p>
                            <hr>
                            <p><strong>비밀번호 변경:</strong></p>
                            <p class="small">
                                비밀번호 필드가 비어있으면 기존 비밀번호가 유지됩니다.
                                새 비밀번호를 입력하면 기존 비밀번호가 변경됩니다.
                            </p>
                            <hr>
                            <p class="small text-info">
                                <i class="fas fa-lightbulb"></i>
                                수정 내용은 즉시 반영되며, 채팅방 참여자들에게 알림이 전송됩니다.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(event, tabName) {
            // 모든 탭 내용 숨기기
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });

            // 모든 탭 버튼에서 active 클래스 제거
            const tabButtons = document.querySelectorAll('.tab-nav-item');
            tabButtons.forEach(button => {
                button.classList.remove('active');
            });

            // 선택된 탭 내용 보이기
            document.getElementById(tabName + '-tab').classList.add('active');

            // 선택된 탭 버튼에 active 클래스 추가
            event.target.classList.add('active');

            // 폼 제출 방지
            event.preventDefault();
        }

        function previewRoomImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();

                reader.onload = function(e) {
                    // 미리보기 이미지 설정
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';

                    // 기본 플레이스홀더 숨기기
                    const placeholder = document.querySelector('.col-md-4 .current-image');
                    placeholder.style.display = 'none';
                }

                reader.readAsDataURL(input.files[0]);
            }
        }

        // 드래그 앤 드롭 기능
        document.addEventListener('DOMContentLoaded', function() {
            const uploadArea = document.querySelector('.image-upload-area');
            const fileInput = document.querySelector('input[name="room_image"]');

            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadArea.style.borderColor = '#3b82f6';
                uploadArea.style.background = '#f0f9ff';
            });

            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                uploadArea.style.borderColor = '#e3e6f0';
                uploadArea.style.background = '#f8f9fa';
            });

            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadArea.style.borderColor = '#e3e6f0';
                uploadArea.style.background = '#f8f9fa';

                const files = e.dataTransfer.files;
                if (files.length > 0 && files[0].type.startsWith('image/')) {
                    fileInput.files = files;
                    previewRoomImage(fileInput);
                }
            });

            uploadArea.addEventListener('click', function() {
                fileInput.click();
            });
        });
    </script>
@endsection