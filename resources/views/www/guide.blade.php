{{-- 지니채팅 이용 가이드 페이지 --}}
@extends('jiny-site::layouts.app')

@section('title', '지니채팅 이용 가이드')

@section('styles')
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
@endsection

@section('content')

    {{-- Hero Section --}}
    <section class="py-lg-8 py-7" style="background: linear-gradient(135deg, #10b981 0%, #2563eb 100%);">
        <div class="container my-lg-8">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8 col-md-12">
                    <h1 class="display-2 fw-bold text-white mb-4 ls-sm">📖 이용 가이드</h1>
                    <p class="lead text-white-50 px-lg-8 mb-6">
                        지니채팅을 처음 사용하는 분들을 위한 상세한 단계별 가이드와
                        유용한 팁을 제공합니다
                    </p>
                    <div class="d-grid d-md-block">
                        <a href="#steps" class="btn btn-light btn-lg me-3">
                            <i class="fas fa-play me-2"></i>가이드 시작하기
                        </a>
                        <a href="{{ route('chat.features') }}" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-star me-2"></i>기능 살펴보기
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Step-by-Step Guide Section --}}
    <section id="steps" class="py-8 bg-gray-100">
        <div class="container">
            <div class="row justify-content-center text-center mb-8">
                <div class="col-lg-8 col-md-12">
                    <h2 class="display-4 fw-bold mb-3">단계별 가이드</h2>
                    <p class="lead text-muted">4단계로 쉽게 시작하는 지니채팅</p>
                </div>
            </div>

            <div class="d-flex flex-column gap-8">
                @foreach($steps as $step)
                    <div class="card border-0 shadow-sm {{ $loop->last ? '' : 'mb-6' }}">
                        <div class="card-body p-6">
                            <div class="row align-items-center">
                                {{-- 스텝 번호 --}}
                                <div class="col-lg-2 col-md-3 col-12 text-center mb-4 mb-lg-0">
                                    <div class="icon-shape icon-xxl rounded-circle
                                        @if($step['step'] % 4 == 1) bg-primary
                                        @elseif($step['step'] % 4 == 2) bg-success
                                        @elseif($step['step'] % 4 == 3) bg-warning
                                        @else bg-info @endif
                                        text-center mx-auto">
                                        <span class="display-4 fw-bold text-white">{{ $step['step'] }}</span>
                                    </div>
                                </div>

                                {{-- 내용 --}}
                                <div class="col-lg-8 col-md-6 col-12 text-center text-md-start mb-4 mb-lg-0">
                                    <h3 class="h3 fw-bold mb-3">{{ $step['title'] }}</h3>
                                    <p class="fs-5 text-muted mb-0">{{ $step['description'] }}</p>
                                </div>

                                {{-- 액션 버튼 --}}
                                <div class="col-lg-2 col-md-3 col-12 text-center">
                                    <a href="{{ $step['url'] }}" class="btn
                                        @if($step['step'] % 4 == 1) btn-primary
                                        @elseif($step['step'] % 4 == 2) btn-success
                                        @elseif($step['step'] % 4 == 3) btn-warning
                                        @else btn-info @endif
                                        btn-lg">
                                        {{ $step['action'] }}
                                        <i class="fas fa-arrow-right ms-2"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Quick Start Section --}}
    <section class="py-8 bg-white">
        <div class="container">
            <div class="row justify-content-center text-center mb-8">
                <div class="col-lg-8 col-md-12">
                    <h2 class="display-4 fw-bold mb-3">⚡ 빠른 시작 가이드</h2>
                    <p class="lead text-muted">5분만에 채팅을 시작하는 간단한 방법</p>
                </div>
            </div>

            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <div class="d-flex flex-column gap-4">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0 bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3"
                                 style="width: 32px; height: 32px;">
                                <span class="text-primary fw-bold">1</span>
                            </div>
                            <div>
                                <h5 class="fw-semibold">계정 생성</h5>
                                <p class="text-muted mb-0">이메일과 비밀번호로 간단하게 회원가입</p>
                            </div>
                        </div>

                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0 bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3"
                                 style="width: 32px; height: 32px;">
                                <span class="text-success fw-bold">2</span>
                            </div>
                            <div>
                                <h5 class="fw-semibold">채팅방 참여</h5>
                                <p class="text-muted mb-0">공개 채팅방 목록에서 관심있는 주제 선택</p>
                            </div>
                        </div>

                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0 bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3"
                                 style="width: 32px; height: 32px;">
                                <span class="text-info fw-bold">3</span>
                            </div>
                            <div>
                                <h5 class="fw-semibold">대화 시작</h5>
                                <p class="text-muted mb-0">실시간으로 메시지를 주고받으며 소통</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="bg-dark rounded-3 p-4 text-white">
                        <div class="mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <div class="bg-danger rounded-circle me-2" style="width: 12px; height: 12px;"></div>
                                <div class="bg-warning rounded-circle me-2" style="width: 12px; height: 12px;"></div>
                                <div class="bg-success rounded-circle me-2" style="width: 12px; height: 12px;"></div>
                                <span class="text-muted small ms-2">지니채팅 - 데모</span>
                            </div>
                        </div>

                        <div class="font-monospace small">
                            <div class="text-success mb-2">$ 채팅방 입장...</div>
                            <div class="text-info mb-2">💬 일반 채팅방에 연결되었습니다</div>
                            <div class="text-light mb-2">
                                <span class="text-warning">Alice:</span> 안녕하세요! 👋
                            </div>
                            <div class="text-light mb-2">
                                <span class="text-primary">Bob:</span> 반가워요!
                            </div>
                            <div class="text-light mb-2">
                                <span class="text-danger">You:</span> 안녕하세요~ 잘 부탁드려요 😊
                            </div>
                            <div class="text-success">
                                <span class="opacity-75">▋</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Useful Tips Section --}}
    <section class="py-8 bg-gray-100">
        <div class="container">
            <div class="row justify-content-center text-center mb-8">
                <div class="col-lg-8 col-md-12">
                    <h2 class="display-4 fw-bold mb-3">💡 유용한 팁</h2>
                    <p class="lead text-muted">지니채팅을 더 효과적으로 사용하는 전문가 팁</p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="card h-100 border-0 shadow-sm card-hover">
                        <div class="card-body p-5 text-center">
                            <div class="icon-shape icon-lg rounded-circle bg-primary text-center mb-4 mx-auto">
                                <i class="fas fa-search text-white"></i>
                            </div>
                            <h4 class="fw-bold mb-3">메시지 검색</h4>
                            <p class="text-muted">
                                채팅방 내에서 특정 키워드로 이전 메시지를 빠르게 찾을 수 있습니다.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 col-12">
                    <div class="card h-100 border-0 shadow-sm card-hover">
                        <div class="card-body p-5 text-center">
                            <div class="icon-shape icon-lg rounded-circle bg-success text-center mb-4 mx-auto">
                                <i class="fas fa-thumbtack text-white"></i>
                            </div>
                            <h4 class="fw-bold mb-3">메시지 고정</h4>
                            <p class="text-muted">
                                중요한 메시지를 채팅방 상단에 고정하여 모든 참여자가 볼 수 있게 합니다.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 col-12">
                    <div class="card h-100 border-0 shadow-sm card-hover">
                        <div class="card-body p-5 text-center">
                            <div class="icon-shape icon-lg rounded-circle bg-warning text-center mb-4 mx-auto">
                                <i class="fas fa-bell text-white"></i>
                            </div>
                            <h4 class="fw-bold mb-3">알림 설정</h4>
                            <p class="text-muted">
                                채팅방별로 알림을 설정하여 중요한 메시지를 놓치지 않을 수 있습니다.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 col-12">
                    <div class="card h-100 border-0 shadow-sm card-hover">
                        <div class="card-body p-5 text-center">
                            <div class="icon-shape icon-lg rounded-circle bg-info text-center mb-4 mx-auto">
                                <i class="fas fa-file-image text-white"></i>
                            </div>
                            <h4 class="fw-bold mb-3">파일 공유</h4>
                            <p class="text-muted">
                                이미지와 파일을 드래그 앤 드롭으로 간편하게 공유할 수 있습니다.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 col-12">
                    <div class="card h-100 border-0 shadow-sm card-hover">
                        <div class="card-body p-5 text-center">
                            <div class="icon-shape icon-lg rounded-circle bg-purple text-center mb-4 mx-auto">
                                <i class="fas fa-palette text-white"></i>
                            </div>
                            <h4 class="fw-bold mb-3">테마 설정</h4>
                            <p class="text-muted">
                                라이트/다크 모드를 선택하여 자신만의 채팅 환경을 만들 수 있습니다.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 col-12">
                    <div class="card h-100 border-0 shadow-sm card-hover">
                        <div class="card-body p-5 text-center">
                            <div class="icon-shape icon-lg rounded-circle bg-danger text-center mb-4 mx-auto">
                                <i class="fas fa-shield-alt text-white"></i>
                            </div>
                            <h4 class="fw-bold mb-3">개인정보 보호</h4>
                            <p class="text-muted">
                                강력한 암호화와 개인정보 보호 설정으로 안전하게 채팅할 수 있습니다.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- FAQ Section --}}
    <section class="py-8 bg-white">
        <div class="container">
            <div class="row justify-content-center text-center mb-8">
                <div class="col-lg-8 col-md-12">
                    <h2 class="display-4 fw-bold mb-3">❓ 자주 묻는 질문</h2>
                    <p class="lead text-muted">궁금한 점들을 미리 확인해보세요</p>
                </div>
            </div>

            <div class="d-flex flex-column gap-4">
                <div class="card border-0 bg-light">
                    <div class="card-body p-4">
                        <h5 class="card-title fw-semibold mb-3">Q. 채팅방은 몇 개까지 만들 수 있나요?</h5>
                        <p class="card-text text-muted mb-0">
                            A. 개인당 채팅방 생성 제한은 없습니다. 하지만 동시에 참여할 수 있는 채팅방 수는 최대 50개입니다.
                        </p>
                    </div>
                </div>

                <div class="card border-0 bg-light">
                    <div class="card-body p-4">
                        <h5 class="card-title fw-semibold mb-3">Q. 메시지 기록은 얼마나 보관되나요?</h5>
                        <p class="card-text text-muted mb-0">
                            A. 모든 메시지는 영구적으로 보관됩니다. 단, 사용자가 직접 삭제하거나 관리자가 삭제한 메시지는 복구할 수 없습니다.
                        </p>
                    </div>
                </div>

                <div class="card border-0 bg-light">
                    <div class="card-body p-4">
                        <h5 class="card-title fw-semibold mb-3">Q. 모바일에서도 사용할 수 있나요?</h5>
                        <p class="card-text text-muted mb-0">
                            A. 네, 반응형 웹 디자인으로 제작되어 모바일, 태블릿, 데스크톱 모든 기기에서 최적화된 경험을 제공합니다.
                        </p>
                    </div>
                </div>

                <div class="card border-0 bg-light">
                    <div class="card-body p-4">
                        <h5 class="card-title fw-semibold mb-3">Q. 비공개 채팅방은 어떻게 만드나요?</h5>
                        <p class="card-text text-muted mb-0">
                            A. 채팅방 생성 시 '비공개' 옵션을 선택하고 필요에 따라 비밀번호를 설정할 수 있습니다. 초대받은 사람만 참여 가능합니다.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Final CTA Section --}}
    <section class="py-8" style="background: linear-gradient(135deg, #059669 0%, #2563eb 100%);">
        <div class="container my-lg-8">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8 col-md-12">
                    <h2 class="display-4 text-white fw-bold mb-4">준비되셨나요?</h2>
                    <p class="lead text-white-50 px-lg-8 mb-6">
                        이제 가이드를 따라 지니채팅을 시작해보세요!
                        새로운 소통의 경험이 기다리고 있습니다.
                    </p>

                    <div class="d-grid d-md-block">
                        <a href="{{ route('home.chat.rooms.index') }}" class="btn btn-light btn-lg mb-2 mb-md-0 me-3">
                            <i class="fas fa-home me-2"></i>채팅방 둘러보기
                        </a>
                        <a href="{{ route('home.chat.rooms.create') }}" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-plus me-2"></i>채팅방 만들기
                        </a>
                    </div>

                    {{-- Guide Stats --}}
                    <div class="row mt-8 text-center">
                        <div class="col-md-4 col-12 mb-3 mb-md-0">
                            <div class="text-white">
                                <h3 class="fw-bold mb-1">4단계</h3>
                                <small class="text-white-50">간단한 시작</small>
                            </div>
                        </div>
                        <div class="col-md-4 col-12 mb-3 mb-md-0">
                            <div class="text-white">
                                <h3 class="fw-bold mb-1">5분</h3>
                                <small class="text-white-50">빠른 설정</small>
                            </div>
                        </div>
                        <div class="col-md-4 col-12">
                            <div class="text-white">
                                <h3 class="fw-bold mb-1">24/7</h3>
                                <small class="text-white-50">언제든 시작</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection