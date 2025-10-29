{{-- 채팅 설정 페이지 --}}
@extends('jiny-site::layouts.home')

@section('content')
    <div class="container-fluid py-5">

        {{-- 헤더 --}}
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-2">
                            <i class="fas fa-cog text-primary"></i>
                            채팅 설정
                        </h2>
                        <p class="text-muted mb-0">채팅 환경을 개인화하고 알림 설정을 관리하세요</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('home.chat.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> 대시보드로
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>오류가 발생했습니다:</strong>
                        <ul class="mb-0 mt-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <form method="POST" action="{{ route('home.chat.settings.update') }}">
                    @csrf

                    {{-- 알림 설정 --}}
                    <div class="border-bottom pb-4 mb-4">
                        <h5 class="mb-3">알림 설정</h5>

                        <div class="mb-3">
                            {{-- 전체 알림 --}}
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <label for="notifications_enabled" class="form-label fw-semibold">
                                        채팅 알림 사용
                                    </label>
                                    <p class="text-muted small mb-0">새 메시지가 도착할 때 알림을 받습니다.</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        id="notifications_enabled" name="notifications_enabled" value="1"
                                        {{ $chatSettings['notifications_enabled'] ? 'checked' : '' }}>
                                </div>
                            </div>

                            {{-- 소리 알림 --}}
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <label for="sound_enabled" class="form-label fw-semibold">
                                        소리 알림
                                    </label>
                                    <p class="text-muted small mb-0">메시지 도착 시 소리로 알림을 받습니다.</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="sound_enabled"
                                        name="sound_enabled" value="1"
                                        {{ $chatSettings['sound_enabled'] ? 'checked' : '' }}>
                                </div>
                            </div>

                            {{-- 데스크톱 알림 --}}
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <label for="desktop_notifications" class="form-label fw-semibold">
                                        데스크톱 알림
                                    </label>
                                    <p class="text-muted small mb-0">브라우저 데스크톱 알림을 사용합니다.</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        id="desktop_notifications" name="desktop_notifications" value="1"
                                        {{ $chatSettings['desktop_notifications'] ?? false ? 'checked' : '' }}>
                                </div>
                            </div>

                            {{-- 이메일 알림 --}}
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <label for="email_notifications" class="form-label fw-semibold">
                                        이메일 알림
                                    </label>
                                    <p class="text-muted small mb-0">오프라인 상태에서 메시지가 도착하면 이메일로 알림을 받습니다.</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="email_notifications"
                                        name="email_notifications" value="1"
                                        {{ $chatSettings['email_notifications'] ?? false ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 화면 설정 --}}
                    <div class="border-bottom pb-4 mb-4">
                        <h5 class="mb-3">화면 설정</h5>

                        <div class="mb-3">
                            {{-- 테마 설정 --}}
                            <div class="mb-3">
                                <label for="theme" class="form-label fw-semibold">
                                    테마
                                </label>
                                <select name="theme" id="theme" class="form-select">
                                    <option value="light" {{ $chatSettings['theme'] === 'light' ? 'selected' : '' }}>라이트 모드
                                    </option>
                                    <option value="dark" {{ $chatSettings['theme'] === 'dark' ? 'selected' : '' }}>다크 모드
                                    </option>
                                    <option value="auto"
                                        {{ ($chatSettings['theme'] ?? 'auto') === 'auto' ? 'selected' : '' }}>시스템 설정 따름
                                    </option>
                                </select>
                                <div class="form-text">채팅 인터페이스의 색상 테마를 선택합니다.</div>
                            </div>

                            {{-- 메시지 크기 --}}
                            <div class="mb-3">
                                <label for="message_size" class="form-label fw-semibold">
                                    메시지 크기
                                </label>
                                <select name="message_size" id="message_size" class="form-select">
                                    <option value="small"
                                        {{ ($chatSettings['message_size'] ?? 'medium') === 'small' ? 'selected' : '' }}>작게
                                    </option>
                                    <option value="medium"
                                        {{ ($chatSettings['message_size'] ?? 'medium') === 'medium' ? 'selected' : '' }}>보통
                                    </option>
                                    <option value="large"
                                        {{ ($chatSettings['message_size'] ?? 'medium') === 'large' ? 'selected' : '' }}>크게
                                    </option>
                                </select>
                            </div>

                            {{-- 자동 스크롤 --}}
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <label for="auto_scroll" class="form-label fw-semibold">
                                        자동 스크롤
                                    </label>
                                    <p class="text-muted small mb-0">새 메시지가 도착하면 자동으로 스크롤됩니다.</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="auto_scroll"
                                        name="auto_scroll" value="1"
                                        {{ $chatSettings['auto_scroll'] ?? true ? 'checked' : '' }}>
                                </div>
                            </div>

                            {{-- 이모지 자동 완성 --}}
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <label for="emoji_autocomplete" class="form-label fw-semibold">
                                        이모지 자동 완성
                                    </label>
                                    <p class="text-muted small mb-0">: 입력 시 이모지 자동 완성을 제공합니다.</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        id="emoji_autocomplete" name="emoji_autocomplete" value="1"
                                        {{ $chatSettings['emoji_autocomplete'] ?? true ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 개인정보 설정 --}}
                    <div class="border-bottom pb-4 mb-4">
                        <h5 class="mb-3">개인정보 설정</h5>

                        <div class="mb-3">
                            {{-- 온라인 상태 표시 --}}
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <label for="show_online_status" class="form-label fw-semibold">
                                        온라인 상태 표시
                                    </label>
                                    <p class="text-muted small mb-0">다른 사용자에게 온라인 상태를 표시합니다.</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        id="show_online_status" name="show_online_status" value="1"
                                        {{ $chatSettings['show_online_status'] ?? true ? 'checked' : '' }}>
                                </div>
                            </div>

                            {{-- 읽음 상태 표시 --}}
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <label for="show_read_status" class="form-label fw-semibold">
                                        읽음 상태 전송
                                    </label>
                                    <p class="text-muted small mb-0">메시지를 읽었을 때 발신자에게 알립니다.</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="show_read_status"
                                        name="show_read_status" value="1"
                                        {{ $chatSettings['show_read_status'] ?? true ? 'checked' : '' }}>
                                </div>
                            </div>

                            {{-- 타이핑 상태 표시 --}}
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <label for="show_typing_status" class="form-label fw-semibold">
                                        타이핑 상태 전송
                                    </label>
                                    <p class="text-muted small mb-0">타이핑 중일 때 다른 사용자에게 표시됩니다.</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        id="show_typing_status" name="show_typing_status" value="1"
                                        {{ $chatSettings['show_typing_status'] ?? true ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 메시지 설정 --}}
                    <div class="border-bottom pb-4 mb-4">
                        <h5 class="mb-3">메시지 설정</h5>

                        <div class="mb-3">
                            {{-- 엔터키 동작 --}}
                            <div class="mb-3">
                                <label for="enter_send" class="form-label fw-semibold">
                                    엔터키 동작
                                </label>
                                <select name="enter_send" id="enter_send" class="form-select">
                                    <option value="send"
                                        {{ ($chatSettings['enter_send'] ?? 'send') === 'send' ? 'selected' : '' }}>메시지 전송
                                    </option>
                                    <option value="newline"
                                        {{ ($chatSettings['enter_send'] ?? 'send') === 'newline' ? 'selected' : '' }}>줄바꿈
                                        (Ctrl+Enter로 전송)</option>
                                </select>
                            </div>

                            {{-- 메시지 미리보기 --}}
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <label for="message_preview" class="form-label fw-semibold">
                                        메시지 미리보기
                                    </label>
                                    <p class="text-muted small mb-0">링크나 이미지의 미리보기를 표시합니다.</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="message_preview"
                                        name="message_preview" value="1"
                                        {{ $chatSettings['message_preview'] ?? true ? 'checked' : '' }}>
                                </div>
                            </div>

                            {{-- 메시지 편집 허용 시간 --}}
                            <div>
                                <label for="edit_timeout" class="form-label fw-semibold">
                                    메시지 편집 허용 시간
                                </label>
                                <select name="edit_timeout" id="edit_timeout" class="form-select">
                                    <option value="5"
                                        {{ ($chatSettings['edit_timeout'] ?? '15') === '5' ? 'selected' : '' }}>5분</option>
                                    <option value="15"
                                        {{ ($chatSettings['edit_timeout'] ?? '15') === '15' ? 'selected' : '' }}>15분
                                    </option>
                                    <option value="60"
                                        {{ ($chatSettings['edit_timeout'] ?? '15') === '60' ? 'selected' : '' }}>1시간
                                    </option>
                                    <option value="unlimited"
                                        {{ ($chatSettings['edit_timeout'] ?? '15') === 'unlimited' ? 'selected' : '' }}>제한
                                        없음</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- 저장 버튼 --}}
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>설정 저장
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 사용자 정보 --}}
        <div class="card shadow-sm mt-4">
            <div class="card-body">
                <h5 class="card-title mb-3">사용자 정보</h5>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <p class="fw-semibold text-muted mb-1">이름</p>
                        <p class="mb-0">{{ $user->name }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <p class="fw-semibold text-muted mb-1">이메일</p>
                        <p class="mb-0">{{ $user->email }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <p class="fw-semibold text-muted mb-1">UUID</p>
                        <p class="mb-0 font-monospace">{{ $user->uuid }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <p class="fw-semibold text-muted mb-1">가입일</p>
                        <p class="mb-0">{{ $user->created_at->format('Y-m-d') }}</p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- JavaScript for dynamic settings --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 알림 권한 요청
            const desktopNotifications = document.getElementById('desktop_notifications');

            if (desktopNotifications && desktopNotifications.checked) {
                requestNotificationPermission();
            }

            desktopNotifications?.addEventListener('change', function() {
                if (this.checked) {
                    requestNotificationPermission();
                }
            });

            function requestNotificationPermission() {
                if ('Notification' in window && Notification.permission === 'default') {
                    Notification.requestPermission().then(function(permission) {
                        if (permission !== 'granted') {
                            desktopNotifications.checked = false;
                            alert('데스크톱 알림을 사용하려면 브라우저에서 알림 권한을 허용해주세요.');
                        }
                    });
                }
            }

            // 테마 미리보기
            const themeSelect = document.getElementById('theme');
            themeSelect?.addEventListener('change', function() {
                // 실시간 테마 미리보기 구현 가능
                console.log('테마 변경:', this.value);
            });
        });
    </script>
@endsection
