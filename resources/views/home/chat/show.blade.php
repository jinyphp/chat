{{-- 채팅방 메인 인터페이스 --}}
@extends('jiny-chat::layouts.chat')

@push('styles')
    <!-- FontAwesome 아이콘 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
@endpush

@section('content')

    <div class="chat-container">
        <div class="row g-0">
            <div class="col-xl-3 col-lg-12 col-md-12 col-12">
                <div class="bg-white border-end border-top">
                    <!-- chat users -->
                    @livewire('jiny-chat::chat-participants', ['roomId' => $room->id])
                </div>
            </div>
            <div class="col-xl-9 col-lg-12 col-md-12 col-12">
                <!-- chat list -->
                <div class="chat-body d-flex flex-column w-100">
                    {{-- 메시지 헤더 --}}
                    <div class="flex-shrink-0">
                        @livewire('jiny-chat::chat-header', ['roomId' => $room->id])
                    </div>

                    {{-- 메시지 영역 --}}
                    <div class="flex-grow-1 overflow-hidden">
                        @livewire('jiny-chat::chat-messages', ['roomId' => $room->id])
                    </div>

                    {{-- 메시지 작성 --}}
                    <div class="flex-shrink-0">
                        @livewire('jiny-chat::chat-write', ['roomId' => $room->id])
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 페이지 제목 업데이트
            document.title = '{{ $room->title }} - 지니채팅';

            // 현재 사용자 정보 디버깅 (브라우저 콘솔에서 확인)
            console.log('🔍 현재 사용자 디버깅 정보:');
            console.log('URL:', window.location.href);
            console.log('쿠키:', document.cookie);

            // JWT 토큰 확인
            const checkToken = (storage, name) => {
                const token = storage.getItem('jwt_token') || storage.getItem('token') || storage.getItem(
                    'auth_token');
                if (token) {
                    console.log(`${name}에서 토큰 발견:`, token);
                    try {
                        const payload = JSON.parse(atob(token.split('.')[1]));
                        console.log(`${name} JWT 페이로드:`, payload);
                        if (payload.uuid) {
                            console.log(`${name} 사용자 UUID:`, payload.uuid);
                        }
                    } catch (e) {
                        console.log(`${name} JWT 토큰 파싱 실패:`, e);
                    }
                }
            };

            checkToken(localStorage, 'LocalStorage');
            checkToken(sessionStorage, 'SessionStorage');

            // 쿠키에서 JWT 토큰 확인
            const cookies = document.cookie.split(';');
            cookies.forEach(cookie => {
                const [name, value] = cookie.trim().split('=');
                if (name && (name.includes('jwt') || name.includes('token') || name.includes('auth'))) {
                    console.log(`쿠키에서 발견 - ${name}:`, value);
                }
            });


            // 레이아웃 디버깅 및 안정화
            function ensureLayoutStability() {
                const chatLayout = document.querySelector('.chat-main-container');
                const participantsContainer = document.querySelector('.chat-left-panel');
                const messagesContainer = document.querySelector('.chat-right-panel');

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
