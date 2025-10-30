{{-- 초대 링크 관리 페이지 --}}
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
        .invite-card {
            background: white;
            border: 1px solid #e3e6f0;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .invite-card-header {
            background: white;
            border-bottom: 1px solid #e3e6f0;
            border-radius: 8px 8px 0 0;
            padding: 1.5rem;
        }
        .invite-card-body {
            padding: 1.5rem;
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
        .invite-item {
            border: 1px solid #e3e6f0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
        }
        .invite-item:hover {
            border-color: #3b82f6;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
        }
        .invite-link {
            background: #f8f9fa;
            border: 1px solid #e3e6f0;
            border-radius: 4px;
            padding: 0.5rem;
            font-family: monospace;
            font-size: 0.9rem;
            word-break: break-all;
        }
    </style>
@endpush

@section('content')
    <div class="content-wrapper px-4 py-4">
        <!-- 페이지 헤더 -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 text-dark fw-bold">초대 링크 관리</h1>
                <!-- 브레드크럼 -->
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home.chat.index') }}">채팅</a></li>
                        <li class="breadcrumb-item active">초대 링크</li>
                    </ol>
                </nav>
            </div>
            <div>
                <a href="{{ route('home.chat.index') }}" class="btn btn-secondary-cms">
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
            <!-- 메인 영역 -->
            <div class="col-lg-12">
                <div class="invite-card">
                    <!-- 탭 네비게이션 -->
                    <div class="tab-nav">
                        <button type="button" class="tab-nav-item active" onclick="showTab(event, 'my-invites')">
                            <i class="fas fa-share-alt me-1"></i> 내가 발급한 초대링크
                        </button>
                        <button type="button" class="tab-nav-item" onclick="showTab(event, 'received-invites')">
                            <i class="fas fa-inbox me-1"></i> 받은 초대링크
                        </button>
                    </div>

                    <!-- 내가 발급한 초대링크 탭 -->
                    <div id="my-invites-tab" class="tab-content active">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0">내가 발급한 초대링크</h5>
                            <small class="text-muted">내가 생성한 채팅방의 초대링크를 관리할 수 있습니다.</small>
                        </div>

                        @if(isset($myRooms) && $myRooms->count() > 0)
                            @foreach($myRooms as $room)
                                <div class="invite-item">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center mb-2">
                                                @if($room->image)
                                                    <img src="{{ asset('storage/' . $room->image) }}"
                                                         alt="{{ $room->title }}"
                                                         class="rounded me-3"
                                                         style="width: 40px; height: 40px; object-fit: cover;">
                                                @else
                                                    <div class="bg-primary text-white rounded d-flex align-items-center justify-content-center me-3"
                                                         style="width: 40px; height: 40px; font-size: 1.2rem;">
                                                        {{ substr($room->title, 0, 1) }}
                                                    </div>
                                                @endif
                                                <div>
                                                    <h6 class="mb-1">{{ $room->title }}</h6>
                                                    <small class="text-muted">
                                                        <i class="fas fa-users me-1"></i>
                                                        {{ $room->participant_count ?? 0 }}명 참여 중
                                                    </small>
                                                </div>
                                            </div>

                                            <div class="mb-2">
                                                <label class="form-label small fw-semibold">초대 링크:</label>
                                                <div class="input-group">
                                                    <input type="text"
                                                           class="form-control invite-link"
                                                           value="{{ url('/chat/invite/' . $room->invite_code) }}"
                                                           readonly
                                                           id="invite-link-{{ $room->id }}">
                                                    <button class="btn btn-outline-secondary"
                                                            type="button"
                                                            onclick="copyInviteLink('invite-link-{{ $room->id }}')">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-4 text-end">
                                            <div class="d-flex flex-column gap-2">
                                                <button class="btn btn-sm btn-primary-cms"
                                                        onclick="regenerateInviteCode({{ $room->id }})">
                                                    <i class="fas fa-sync-alt me-1"></i> 새로 발급
                                                </button>
                                                <a href="{{ route('home.chat.rooms.edit', $room->id) }}"
                                                   class="btn btn-sm btn-secondary-cms">
                                                    <i class="fas fa-cog me-1"></i> 설정
                                                </a>
                                                <small class="text-muted">
                                                    생성일: {{ $room->created_at->format('Y-m-d') }}
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="text-center py-5">
                                <div class="text-muted mb-4">
                                    <i class="fas fa-share-alt" style="font-size: 48px;"></i>
                                </div>
                                <h5 class="text-muted">생성한 채팅방이 없습니다</h5>
                                <p class="text-muted">새로운 채팅방을 만들어서 친구들을 초대해보세요.</p>
                                <a href="{{ route('home.chat.rooms.create') }}" class="btn btn-primary-cms">
                                    <i class="fas fa-plus me-1"></i> 새 채팅방 만들기
                                </a>
                            </div>
                        @endif
                    </div>

                    <!-- 받은 초대링크 탭 -->
                    <div id="received-invites-tab" class="tab-content">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0">받은 초대링크</h5>
                            <div class="d-flex gap-2">
                                <input type="text"
                                       class="form-control"
                                       id="invite-url-input"
                                       placeholder="초대 링크를 입력하세요"
                                       style="width: 300px;">
                                <button class="btn btn-primary-cms" onclick="joinByInviteLink()">
                                    <i class="fas fa-sign-in-alt me-1"></i> 참여하기
                                </button>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>초대 링크로 채팅방 참여하기</strong><br>
                            다른 사람으로부터 받은 초대 링크를 위 입력창에 붙여넣고 '참여하기' 버튼을 클릭하세요.
                        </div>

                        <!-- 최근 참여한 채팅방 -->
                        @if(isset($recentJoinedRooms) && $recentJoinedRooms->count() > 0)
                            <h6 class="mt-4 mb-3">최근 참여한 채팅방</h6>
                            @foreach($recentJoinedRooms as $room)
                                <div class="invite-item">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center">
                                                @if($room->image)
                                                    <img src="{{ asset('storage/' . $room->image) }}"
                                                         alt="{{ $room->title }}"
                                                         class="rounded me-3"
                                                         style="width: 40px; height: 40px; object-fit: cover;">
                                                @else
                                                    <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center me-3"
                                                         style="width: 40px; height: 40px; font-size: 1.2rem;">
                                                        {{ substr($room->title, 0, 1) }}
                                                    </div>
                                                @endif
                                                <div>
                                                    <h6 class="mb-1">{{ $room->title }}</h6>
                                                    <small class="text-muted">
                                                        <i class="fas fa-crown me-1"></i>
                                                        방장: {{ $room->owner_name ?? '알 수 없음' }}
                                                        <span class="ms-2">
                                                            <i class="fas fa-users me-1"></i>
                                                            {{ $room->participant_count ?? 0 }}명
                                                        </span>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <a href="{{ route('home.chat.room', $room->id) }}"
                                               class="btn btn-sm btn-primary-cms">
                                                <i class="fas fa-sign-in-alt me-1"></i> 입장하기
                                            </a>
                                            <br>
                                            <small class="text-muted mt-1">
                                                참여일: {{ $room->pivot->joined_at ?? $room->created_at->format('Y-m-d') }}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="text-center py-4 mt-4">
                                <div class="text-muted mb-3">
                                    <i class="fas fa-inbox" style="font-size: 36px;"></i>
                                </div>
                                <p class="text-muted">아직 참여한 채팅방이 없습니다.</p>
                            </div>
                        @endif
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

        function copyInviteLink(inputId) {
            const input = document.getElementById(inputId);
            input.select();
            input.setSelectionRange(0, 99999); // 모바일 지원

            navigator.clipboard.writeText(input.value).then(function() {
                // 성공 알림
                const toast = document.createElement('div');
                toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed';
                toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-check me-2"></i>초대 링크가 클립보드에 복사되었습니다!
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                `;
                document.body.appendChild(toast);
                const bsToast = new bootstrap.Toast(toast);
                bsToast.show();
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        document.body.removeChild(toast);
                    }
                }, 3000);
            }).catch(function() {
                alert('클립보드 복사에 실패했습니다. 수동으로 복사해주세요.');
            });
        }

        function regenerateInviteCode(roomId) {
            if (confirm('초대 링크를 새로 발급하시겠습니까?\n기존 링크는 더 이상 사용할 수 없게 됩니다.')) {
                // CSRF 토큰 가져오기
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                fetch(`/home/chat/rooms/${roomId}/regenerate-invite`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // 새로운 초대 링크로 업데이트
                        const input = document.getElementById(`invite-link-${roomId}`);
                        input.value = data.invite_url;

                        // 성공 메시지
                        alert('새로운 초대 링크가 발급되었습니다!');
                    } else {
                        alert('초대 링크 발급에 실패했습니다: ' + (data.message || '알 수 없는 오류'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('초대 링크 발급 중 오류가 발생했습니다.');
                });
            }
        }

        function joinByInviteLink() {
            const inviteUrl = document.getElementById('invite-url-input').value.trim();

            if (!inviteUrl) {
                alert('초대 링크를 입력해주세요.');
                return;
            }

            // 초대 링크 형식 확인
            const inviteCodeMatch = inviteUrl.match(/\/chat\/invite\/([a-zA-Z0-9]+)$/);
            if (!inviteCodeMatch) {
                alert('올바른 초대 링크 형식이 아닙니다.\n예시: https://example.com/chat/invite/abc123');
                return;
            }

            // 초대 링크로 이동
            window.location.href = inviteUrl;
        }
    </script>
@endsection