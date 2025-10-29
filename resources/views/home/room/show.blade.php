{{-- 채팅방 메인 인터페이스 --}}
@extends('jiny-site::layouts.home')

@push('styles')
    <!-- FontAwesome 아이콘 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        /* 채팅 레이아웃 스타일 */
        .chat-main-container {
            height: calc(100vh - 250px);
            min-height: 600px;
        }

        .chat-left-panel {
            width: 250px;
            flex-shrink: 0;
            background-color: #f8f9fa;
            border-right: 1px solid #dee2e6;
        }

        .chat-right-panel {
            flex: 1;
            background-color: #ffffff;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid px-4">
        <!-- 페이지 헤더 -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <div class="d-flex align-items-center">
                    <!-- 채팅방 아이콘 -->
                    <div class="me-3">
                        @if ($room && $room->image)
                            <img src="{{ $room->image }}" alt="{{ $room->title }}" class="rounded-circle"
                                style="width: 50px; height: 50px;">
                        @else
                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center"
                                style="width: 50px; height: 50px;">
                                <i class="fas fa-comments text-white fs-4"></i>
                            </div>
                        @endif
                    </div>

                    <!-- 채팅방 정보 -->
                    <div>
                        <h1 class="h3 mb-1 fw-bold text-dark">
                            {{ $room ? $room->title : '채팅방' }}
                        </h1>
                        <p class="text-muted mb-0 small">
                            {{ $room ? $room->description : '' }}
                            @if ($room)
                                · 참여자 {{ $room->participant_count }}명
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            <!-- 네비게이션 버튼들 -->
            <div>
                <div class="d-flex gap-2">
                    <a href="{{ route('home.chat.index') }}" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-list"></i> 참여채팅방
                    </a>
                    <a href="{{ route('home.chat.rooms.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-th-list"></i> 전체목록
                    </a>
                    <a href="{{ route('home.chat.rooms.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> 채팅방 개설
                    </a>
                </div>
            </div>
        </div>

        <!-- 채팅방 메인 영역 -->
        <section>
            <div class="d-flex chat-main-container">
                <div class="chat-left-panel">
                    @livewire('chat-participants', ['roomId' => $room->id])
                </div>
                <div class="chat-right-panel">
                    @livewire('chat-messages', ['roomId' => $room->id])
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 페이지 제목 업데이트
            document.title = '{{ $room->title }} - 지니채팅';

            // 레이아웃 디버깅 및 안정화
            function ensureLayoutStability() {
                const chatLayout = document.querySelector('.chat-layout');
                const participantsContainer = document.querySelector('.chat-participants-container');
                const messagesContainer = document.querySelector('.chat-messages-container');

                if (chatLayout && participantsContainer && messagesContainer) {
                    console.log('✅ 채팅 레이아웃 컨테이너들이 모두 로드되었습니다.');
                    console.log('참여자 컨테이너:', participantsContainer.offsetWidth + 'px');
                    console.log('메시지 컨테이너:', messagesContainer.offsetWidth + 'px');
                    console.log('전체 레이아웃:', chatLayout.offsetWidth + 'px');

                    // 컴포넌트 내부 확인
                    const participantsWrapper = participantsContainer.querySelector('.chat-participants-wrapper');
                    const messagesWrapper = messagesContainer.querySelector('.chat-messages-wrapper');

                    if (participantsWrapper) {
                        console.log('✅ 참여자 Livewire 컴포넌트 로드됨');
                    } else {
                        console.log('❌ 참여자 Livewire 컴포넌트 누락');
                    }

                    if (messagesWrapper) {
                        console.log('✅ 메시지 Livewire 컴포넌트 로드됨');
                    } else {
                        console.log('❌ 메시지 Livewire 컴포넌트 누락');
                    }
                } else {
                    console.log('❌ 채팅 레이아웃 컨테이너 중 일부가 누락되었습니다.');
                    setTimeout(ensureLayoutStability, 500); // 재시도
                }
            }

            // 초기 레이아웃 확인
            setTimeout(ensureLayoutStability, 100);

            // Livewire 컴포넌트 로드 완료 후 재확인
            document.addEventListener('livewire:navigated', ensureLayoutStability);

            // 윈도우 리사이즈 시 레이아웃 유지
            window.addEventListener('resize', function() {
                ensureLayoutStability();
            });

            // 뒤로가기 방지 (선택적)
            window.addEventListener('beforeunload', function(e) {
                // 채팅 중인 경우 뒤로가기 확인
                const message = '채팅방을 나가시겠습니까?';
                e.returnValue = message;
                return message;
            });
        });
    </script>
@endpush
