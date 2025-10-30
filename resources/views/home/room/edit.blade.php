{{-- 채팅방 편집 페이지 --}}
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
        .edit-card-header {
            background: white;
            border-bottom: 1px solid #e3e6f0;
            border-radius: 8px 8px 0 0;
            padding: 1.5rem;
        }
        .edit-card-body {
            padding: 1.5rem;
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
        .form-control, .form-select {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 0.75rem;
            font-size: 0.875rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .checkbox-item {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 1rem;
            transition: all 0.2s;
        }
        .checkbox-item:hover {
            background: #f3f4f6;
            border-color: #3b82f6;
        }
        .checkbox-item input:checked + label {
            color: #3b82f6;
        }
        .image-upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            background: #f9fafb;
            padding: 2rem;
            text-align: center;
            transition: all 0.2s;
        }
        .image-upload-area:hover {
            border-color: #3b82f6;
            background: #eff6ff;
        }
        .current-image {
            max-width: 100px;
            max-height: 100px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
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
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 500;
            color: #6b7280;
        }
        .info-value {
            font-weight: 600;
            color: #374151;
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
        .btn-danger-cms {
            background: #ef4444;
            border-color: #ef4444;
            color: white;
        }
        .btn-danger-cms:hover {
            background: #dc2626;
            border-color: #dc2626;
            color: white;
        }
        .breadcrumb {
            background: none;
            padding: 0;
            margin-bottom: 2rem;
        }
        .breadcrumb-item a {
            color: #3b82f6;
            text-decoration: none;
        }
        .breadcrumb-item.active {
            color: #6b7280;
        }

        /* 탭 스타일 */
        .tab-nav {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #e3e6f0;
            border-radius: 8px 8px 0 0;
        }

        .tab-nav-item {
            flex: 1;
            padding: 1rem 1.5rem;
            text-align: center;
            cursor: pointer;
            border: none;
            background: transparent;
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
    </style>
@endpush

@section('content')
    <div class="content-wrapper px-4 py-4">
        <!-- 페이지 헤더 -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 text-dark fw-bold">채팅방 편집</h1>
                <!-- 브레드크럼 -->
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home.chat.index') }}">채팅</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('home.chat.index') }}">채팅방 목록</a></li>
                        <li class="breadcrumb-item active">{{ $room->title }}</li>
                    </ol>
                </nav>
            </div>
            <div>
                <a href="{{ route('home.chat.index') }}" class="btn btn-secondary-cms">
                    <i class="fas fa-arrow-left me-1"></i> 돌아가기
                </a>
            </div>
        </div>

        <div class="row">
            <!-- 메인 편집 영역 -->
            <div class="col-lg-8">
                <form id="roomEditForm" enctype="multipart/form-data" data-room-id="{{ $room->id }}">
                    @csrf

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
                            <button type="button" class="tab-nav-item" onclick="showTab(event, 'appearance')">
                                <i class="fas fa-palette me-1"></i> 외관 설정
                            </button>
                        </div>

                        <!-- 기본 정보 탭 -->
                        <div id="basic-tab" class="tab-content active">

                                <!-- 채팅방 이미지 -->
                                <div class="form-group">
                                    <label class="form-label">채팅방 대표 이미지</label>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            @if($room->image)
                                                <img src="{{ asset('storage/' . $room->image) }}" alt="Current Image"
                                                     class="current-image d-block mx-auto">
                                                <div class="text-center mt-2">
                                                    <small class="text-muted">현재 이미지</small>
                                                </div>
                                            @else
                                                <div class="current-image d-flex align-items-center justify-content-center mx-auto bg-light">
                                                    <i class="fas fa-image text-muted"></i>
                                                </div>
                                                <div class="text-center mt-2">
                                                    <small class="text-muted">이미지 없음</small>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="col-md-8">
                                            <div class="image-upload-area">
                                                <i class="fas fa-cloud-upload-alt fs-4 text-muted mb-2"></i>
                                                <p class="text-muted mb-2">이미지를 드래그하거나 클릭하여 업로드</p>
                                                <input type="file" class="form-control" name="room_image"
                                                       accept="image/*" onchange="previewRoomImage(this)">
                                                <small class="text-muted">JPG, PNG, GIF (최대 2MB)</small>
                                            </div>
                                            <div id="imagePreview" class="mt-3" style="display: none;">
                                                <img id="previewImg" src="" alt="Preview" class="current-image">
                                                <div class="text-center mt-2">
                                                    <small class="text-success">새 이미지 미리보기</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 제목 -->
                                <div class="form-group">
                                    <label class="form-label">채팅방 제목 <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="title" value="{{ $room->title }}"
                                           placeholder="채팅방 제목을 입력하세요" required maxlength="255">
                                </div>

                                <!-- 설명 -->
                                <div class="form-group">
                                    <label class="form-label">설명</label>
                                    <textarea class="form-control" name="description" rows="4"
                                              placeholder="채팅방에 대한 설명을 입력하세요"
                                              maxlength="1000">{{ $room->description }}</textarea>
                                </div>

                                <!-- 타입과 카테고리 -->
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">타입</label>
                                            <select class="form-select" name="type">
                                                <option value="public" {{ $room->type === 'public' ? 'selected' : '' }}>공개</option>
                                                <option value="private" {{ $room->type === 'private' ? 'selected' : '' }}>비공개</option>
                                                <option value="group" {{ $room->type === 'group' ? 'selected' : '' }}>그룹</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">카테고리</label>
                                            <select class="form-select" name="category">
                                                <option value="">선택하세요</option>
                                                <option value="general" {{ $room->category === 'general' ? 'selected' : '' }}>일반</option>
                                                <option value="work" {{ $room->category === 'work' ? 'selected' : '' }}>업무</option>
                                                <option value="study" {{ $room->category === 'study' ? 'selected' : '' }}>스터디</option>
                                                <option value="hobby" {{ $room->category === 'hobby' ? 'selected' : '' }}>취미</option>
                                                <option value="community" {{ $room->category === 'community' ? 'selected' : '' }}>커뮤니티</option>
                                                <option value="game" {{ $room->category === 'game' ? 'selected' : '' }}>게임</option>
                                                <option value="etc" {{ $room->category === 'etc' ? 'selected' : '' }}>기타</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                        </div>

                        <!-- 접근 및 권한 탭 -->
                        <div id="access-tab" class="tab-content">

                                <div class="form-group">
                                    <label class="form-label">접근 권한</label>
                                    <div class="checkbox-group">
                                        <div class="checkbox-item">
                                            <div class="form-check">
                                                <input id="is_public" type="checkbox" name="is_public" value="1"
                                                       {{ $room->is_public ? 'checked' : '' }} class="form-check-input">
                                                <label for="is_public" class="form-check-label">
                                                    <div class="fw-semibold">
                                                        <i class="fas fa-search text-primary me-1"></i> 검색 가능
                                                    </div>
                                                    <small class="text-muted">다른 사용자가 검색할 수 있습니다</small>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="checkbox-item">
                                            <div class="form-check">
                                                <input id="allow_join" type="checkbox" name="allow_join" value="1"
                                                       {{ $room->allow_join ? 'checked' : '' }} class="form-check-input">
                                                <label for="allow_join" class="form-check-label">
                                                    <div class="fw-semibold">
                                                        <i class="fas fa-door-open text-success me-1"></i> 자유 참여
                                                    </div>
                                                    <small class="text-muted">승인 없이 참여할 수 있습니다</small>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="checkbox-item">
                                            <div class="form-check">
                                                <input id="allow_invite" type="checkbox" name="allow_invite" value="1"
                                                       {{ $room->allow_invite ? 'checked' : '' }} class="form-check-input">
                                                <label for="allow_invite" class="form-check-label">
                                                    <div class="fw-semibold">
                                                        <i class="fas fa-user-plus text-info me-1"></i> 초대 허용
                                                    </div>
                                                    <small class="text-muted">사용자를 초대할 수 있습니다</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 보안 설정 -->
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label class="form-label">비밀번호 (선택)</label>
                                            <input type="password" class="form-control" name="password"
                                                   placeholder="새 비밀번호 입력 (변경시에만)" minlength="4">
                                            <small class="text-muted">비밀번호 설정시 참여할 때 입력이 필요합니다</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">최대 참여자</label>
                                            <input type="number" class="form-control" name="max_participants"
                                                   value="{{ $room->max_participants }}" min="0" max="1000" placeholder="0 (무제한)">
                                            <small class="text-muted">0이면 무제한</small>
                                        </div>
                                    </div>
                                </div>
                        </div>

                        <!-- 기능 설정 탭 -->
                        <div id="features-tab" class="tab-content">

                                <div class="form-group">
                                    <label class="form-label">허용 기능</label>
                                    <div class="checkbox-group">
                                        <div class="checkbox-item">
                                            <div class="form-check">
                                                <input id="allow_file_upload" type="checkbox" name="allow_file_upload" value="1"
                                                       {{ $room->allow_file_upload ?? true ? 'checked' : '' }} class="form-check-input">
                                                <label for="allow_file_upload" class="form-check-label">
                                                    <div class="fw-semibold">
                                                        <i class="fas fa-paperclip text-primary me-1"></i> 파일 업로드
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="checkbox-item">
                                            <div class="form-check">
                                                <input id="allow_image_upload" type="checkbox" name="allow_image_upload" value="1"
                                                       {{ $room->allow_image_upload ?? true ? 'checked' : '' }} class="form-check-input">
                                                <label for="allow_image_upload" class="form-check-label">
                                                    <div class="fw-semibold">
                                                        <i class="fas fa-image text-success me-1"></i> 이미지 업로드
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="checkbox-item">
                                            <div class="form-check">
                                                <input id="allow_mentions" type="checkbox" name="allow_mentions" value="1"
                                                       {{ $room->allow_mentions ?? true ? 'checked' : '' }} class="form-check-input">
                                                <label for="allow_mentions" class="form-check-label">
                                                    <div class="fw-semibold">
                                                        <i class="fas fa-at text-info me-1"></i> 멘션
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="checkbox-item">
                                            <div class="form-check">
                                                <input id="allow_reactions" type="checkbox" name="allow_reactions" value="1"
                                                       {{ $room->allow_reactions ?? true ? 'checked' : '' }} class="form-check-input">
                                                <label for="allow_reactions" class="form-check-label">
                                                    <div class="fw-semibold">
                                                        <i class="fas fa-smile text-warning me-1"></i> 리액션
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        </div>

                        <!-- 외관 설정 탭 -->
                        <div id="appearance-tab" class="tab-content">

                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">배경색</label>
                                            <input type="color" class="form-control form-control-color" name="background_color"
                                                   value="{{ $backgroundColor }}" style="height: 3rem;">
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label class="form-label">색상 코드</label>
                                            <input type="text" class="form-control" name="background_color_text"
                                                   value="{{ $backgroundColor }}" placeholder="#f8f9fa">
                                            <small class="text-muted">16진수 색상 코드 (예: #f8f9fa)</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">미리보기</label>
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
                </form>
            </div>

            <!-- 사이드바 -->
            <div class="col-lg-4">
                <!-- 채팅방 정보 -->
                <div class="sidebar-card">
                    <div class="sidebar-header">
                        채팅방 정보
                    </div>
                    <div class="sidebar-body">
                        <div class="info-item">
                            <span class="info-label">채팅방 ID</span>
                            <span class="info-value">{{ $room->id }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">상태</span>
                            <span class="info-value">
                                <span class="badge bg-success">{{ ucfirst($room->status) }}</span>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">방장</span>
                            <span class="info-value">{{ $room->owner_name }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">참여자 수</span>
                            <span class="info-value">{{ $room->participant_count }}명</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">생성일</span>
                            <span class="info-value">{{ $room->created_at->format('Y.m.d H:i') }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">최근 활동</span>
                            <span class="info-value">{{ $room->last_activity_at ? $room->last_activity_at->diffForHumans() : '없음' }}</span>
                        </div>
                    </div>
                </div>

                <!-- 액션 -->
                <div class="sidebar-card">
                    <div class="sidebar-header">
                        액션
                    </div>
                    <div class="sidebar-body">
                        <div class="d-grid gap-2">
                            <button type="submit" form="roomEditForm" class="btn btn-primary-cms">
                                <i class="fas fa-save me-1"></i> 설정 저장
                            </button>
                            <a href="{{ route('home.chat.room', $room->id) }}" class="btn btn-secondary-cms">
                                <i class="fas fa-comments me-1"></i> 채팅방 입장
                            </a>
                            <hr>
                            <button type="button" class="btn btn-danger-cms" onclick="confirmDelete()">
                                <i class="fas fa-trash me-1"></i> 채팅방 삭제
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 참여자 요약 -->
                <div class="sidebar-card">
                    <div class="sidebar-header">
                        최근 참여자
                    </div>
                    <div class="sidebar-body">
                        @if($room->activeParticipants->count() > 0)
                            @foreach($room->activeParticipants->take(5) as $participant)
                                <div class="d-flex align-items-center mb-2">
                                    <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-2"
                                         style="width: 24px; height: 24px;">
                                        <small class="text-white fw-bold">{{ substr($participant->name, 0, 1) }}</small>
                                    </div>
                                    <div class="flex-grow-1">
                                        <small class="fw-semibold">{{ $participant->name }}</small>
                                        @if($participant->role === 'owner')
                                            <span class="badge bg-warning text-dark ms-1">방장</span>
                                        @elseif($participant->role === 'admin')
                                            <span class="badge bg-info ms-1">관리자</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                            @if($room->activeParticipants->count() > 5)
                                <small class="text-muted">외 {{ $room->activeParticipants->count() - 5 }}명</small>
                            @endif
                        @else
                            <p class="text-muted small mb-0">참여자가 없습니다.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // 탭 전환 함수
        function showTab(event, tabName) {
            // 기본 동작 방지 (폼 제출 방지)
            event.preventDefault();

            // 모든 탭 콘텐츠 숨기기
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });

            // 모든 탭 네비게이션 버튼 비활성화
            const tabNavItems = document.querySelectorAll('.tab-nav-item');
            tabNavItems.forEach(item => {
                item.classList.remove('active');
            });

            // 선택된 탭 활성화
            const selectedTab = document.getElementById(tabName + '-tab');
            if (selectedTab) {
                selectedTab.classList.add('active');
            }

            // 선택된 탭 버튼 활성화
            const selectedTabButton = event.target;
            selectedTabButton.classList.add('active');
        }

        // 페이지 로드 완료 후 실행
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
            const form = document.getElementById('roomEditForm');
            if (form) {
                form.addEventListener('submit', function(event) {
                    event.preventDefault();
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

                if (file.size > 2 * 1024 * 1024) {
                    alert('파일 크기는 2MB 이하여야 합니다.');
                    input.value = '';
                    preview.style.display = 'none';
                    return;
                }

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
            formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}');
            formData.append('_method', 'POST');

            // 로딩 상태 표시
            const submitButton = document.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> 저장 중...';
            submitButton.disabled = true;

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
                    showToast(data.message || '설정이 저장되었습니다.', 'success');
                    setTimeout(() => {
                        window.location.href = '{{ route("home.chat.index") }}';
                    }, 1000);
                } else {
                    showToast(data.error || '설정 저장에 실패했습니다.', 'danger');
                }
            })
            .catch(error => {
                console.error('Error saving room settings:', error);
                showToast('설정 저장 중 오류가 발생했습니다.', 'danger');
            })
            .finally(() => {
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
            });
        }

        // 채팅방 삭제 확인
        function confirmDelete() {
            if (confirm('정말로 이 채팅방을 삭제하시겠습니까?\n\n이 작업은 되돌릴 수 없으며, 모든 메시지와 참여자 정보가 삭제됩니다.')) {
                deleteRoom();
            }
        }

        // 채팅방 삭제
        function deleteRoom() {
            const roomId = document.getElementById('roomEditForm').getAttribute('data-room-id');

            fetch(`/home/chat/room/${roomId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('채팅방이 삭제되었습니다.', 'success');
                    setTimeout(() => {
                        window.location.href = '{{ route("home.chat.index") }}';
                    }, 1000);
                } else {
                    showToast(data.error || '채팅방 삭제에 실패했습니다.', 'danger');
                }
            })
            .catch(error => {
                console.error('Error deleting room:', error);
                showToast('채팅방 삭제 중 오류가 발생했습니다.', 'danger');
            });
        }

        // 토스트 알림 표시
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} position-fixed alert-dismissible fade show`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(toast);

            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 5000);
        }
    </script>
@endpush