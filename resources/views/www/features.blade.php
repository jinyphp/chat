{{-- 지니채팅 기능 소개 페이지 --}}
@extends('jiny-site::layouts.app')

@section('title', '지니채팅 기능 소개')

@section('styles')
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
@endsection

@section('content')

    {{-- Hero Section --}}
    <section class="py-lg-8 py-7" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container my-lg-8">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8 col-md-12">
                    <h1 class="display-2 fw-bold text-white mb-4 ls-sm">🎯 강력한 기능들</h1>
                    <p class="lead text-white-50 px-lg-8 mb-6">
                        지니채팅이 제공하는 혁신적이고 다양한 기능들로
                        새로운 차원의 커뮤니케이션을 경험해보세요
                    </p>
                    <div class="d-grid d-md-block">
                        <a href="{{ route('chat.index') }}" class="btn btn-light btn-lg me-3">
                            <i class="fas fa-rocket me-2"></i>지금 시작하기
                        </a>
                        <a href="{{ route('chat.guide') }}" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-book-open me-2"></i>이용 가이드
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Core Features Section --}}
    <section class="py-8 bg-white">
        <div class="container">
            @foreach($features as $index => $category)
                <div class="mb-8 {{ $index > 0 ? 'border-top pt-8' : '' }}">
                    <div class="row justify-content-center text-center mb-6">
                        <div class="col-lg-8 col-md-12">
                            <div class="icon-shape icon-xl rounded-circle
                                @if($index % 4 == 0) bg-primary
                                @elseif($index % 4 == 1) bg-success
                                @elseif($index % 4 == 2) bg-warning
                                @else bg-info @endif
                                text-center mb-4 mx-auto">
                                <span class="fs-1 text-white">
                                    @if($index == 0) 💬
                                    @elseif($index == 1) 🏠
                                    @elseif($index == 2) 🔒
                                    @else 👨‍💼 @endif
                                </span>
                            </div>
                            <h2 class="display-4 fw-bold mb-3">{{ $category['category'] }}</h2>
                            <div class="mx-auto rounded-pill
                                @if($index % 4 == 0) bg-primary
                                @elseif($index % 4 == 1) bg-success
                                @elseif($index % 4 == 2) bg-warning
                                @else bg-info @endif"
                                style="width: 80px; height: 4px;"></div>
                        </div>
                    </div>

                    <div class="row g-4">
                        @foreach($category['items'] as $itemIndex => $item)
                            <div class="col-lg-4 col-md-6 col-12">
                                <div class="card h-100 shadow-sm border-0 card-hover">
                                    <div class="card-body p-5 text-center">
                                        <div class="icon-shape icon-lg rounded-circle
                                            @if($itemIndex % 4 == 0) bg-primary
                                            @elseif($itemIndex % 4 == 1) bg-success
                                            @elseif($itemIndex % 4 == 2) bg-warning
                                            @else bg-info @endif
                                            text-center mb-4 mx-auto">
                                            <i class="fas fa-check text-white fs-4"></i>
                                        </div>
                                        <h4 class="fw-bold mb-3">{{ $item }}</h4>
                                        <p class="text-muted small">{{ $category['category'] }}의 핵심 기능으로 효율적인 커뮤니케이션을 지원합니다.</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Advanced Features Section --}}
    <section class="py-8 bg-gray-100">
        <div class="container">
            <div class="row justify-content-center text-center mb-8">
                <div class="col-lg-8 col-md-12">
                    <h2 class="display-4 fw-bold mb-3">🌟 차별화된 핵심 기술</h2>
                    <p class="lead text-muted">다른 채팅 시스템과는 완전히 다른 혁신적인 기술들로 구축된 플랫폼</p>
                </div>
            </div>

            <div class="row g-6">
                {{-- 사용자 샤딩 --}}
                <div class="col-lg-6 col-12">
                    <div class="card h-100 border-0 shadow-lg position-relative overflow-hidden">
                        <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, #22c55e 0%, #3b82f6 100%); opacity: 0.05;"></div>
                        <div class="card-body p-6 position-relative">
                            <div class="icon-shape icon-xl rounded-circle bg-success text-center mb-4">
                                <span class="fs-1 text-white">🔧</span>
                            </div>
                            <h3 class="h3 fw-bold mb-3">사용자 샤딩 시스템</h3>
                            <p class="fs-5 text-muted mb-4">
                                대용량 사용자 처리를 위한 고성능 샤딩 아키텍처로
                                수십만 명의 동시 접속자도 안정적으로 처리합니다.
                            </p>
                            <div class="bg-success bg-opacity-10 rounded-3 p-4 border-start border-success border-4">
                                <div class="font-monospace fw-semibold text-success">
                                    <i class="fas fa-database me-2"></i>user_shard_001, user_shard_002...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- JWT 인증 --}}
                <div class="col-lg-6 col-12">
                    <div class="card h-100 border-0 shadow-lg position-relative overflow-hidden">
                        <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, #a855f7 0%, #ec4899 100%); opacity: 0.05;"></div>
                        <div class="card-body p-6 position-relative">
                            <div class="icon-shape icon-xl rounded-circle bg-primary text-center mb-4">
                                <span class="fs-1 text-white">🔒</span>
                            </div>
                            <h3 class="h3 fw-bold mb-3">JWT 기반 인증</h3>
                            <p class="fs-5 text-muted mb-4">
                                상태가 없는(Stateless) JWT 토큰으로 확장성과 보안성을
                                동시에 확보한 최신 인증 시스템입니다.
                            </p>
                            <div class="bg-primary bg-opacity-10 rounded-3 p-4 border-start border-primary border-4">
                                <div class="font-monospace fw-semibold text-primary">
                                    <i class="fas fa-key me-2"></i>Bearer eyJ0eXAiOiJKV1Q...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 실시간 브로드캐스팅 --}}
                <div class="col-lg-6 col-12">
                    <div class="card h-100 border-0 shadow-lg position-relative overflow-hidden">
                        <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, #fbbf24 0%, #f97316 100%); opacity: 0.05;"></div>
                        <div class="card-body p-6 position-relative">
                            <div class="icon-shape icon-xl rounded-circle bg-warning text-center mb-4">
                                <span class="fs-1 text-white">⚡</span>
                            </div>
                            <h3 class="h3 fw-bold mb-3">실시간 브로드캐스팅</h3>
                            <p class="fs-5 text-muted mb-4">
                                WebSocket과 Laravel Broadcasting을 활용한 초고속
                                실시간 메시지 전송으로 지연 없는 대화를 제공합니다.
                            </p>
                            <div class="bg-warning bg-opacity-10 rounded-3 p-4 border-start border-warning border-4">
                                <div class="font-monospace fw-semibold text-warning">
                                    <i class="fas fa-wifi me-2"></i>chat-room.{roomId}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 역할 기반 권한 --}}
                <div class="col-lg-6 col-12">
                    <div class="card h-100 border-0 shadow-lg position-relative overflow-hidden">
                        <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, #6366f1 0%, #7c3aed 100%); opacity: 0.05;"></div>
                        <div class="card-body p-6 position-relative">
                            <div class="icon-shape icon-xl rounded-circle bg-info text-center mb-4">
                                <span class="fs-1 text-white">👥</span>
                            </div>
                            <h3 class="h3 fw-bold mb-3">역할 기반 권한 관리</h3>
                            <p class="fs-5 text-muted mb-4">
                                방장, 관리자, 일반 사용자 등 세분화된 역할 시스템으로
                                체계적인 채팅방 관리가 가능합니다.
                            </p>
                            <div class="bg-info bg-opacity-10 rounded-3 p-4 border-start border-info border-4">
                                <div class="font-monospace fw-semibold text-info">
                                    <i class="fas fa-users me-2"></i>owner → admin → moderator → member
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Technical Excellence Section --}}
    <section class="py-8 bg-white">
        <div class="container">
            <div class="row justify-content-center text-center mb-8">
                <div class="col-lg-8 col-md-12">
                    <h2 class="display-4 fw-bold mb-3">⚙️ 기술적 우수성</h2>
                    <p class="lead text-muted">최신 기술과 최적화된 아키텍처로 구현된 안정적인 플랫폼</p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-4 col-md-6 col-12 text-center">
                    <div class="p-4">
                        <div class="icon-shape icon-xl rounded-circle bg-primary text-center mb-4 mx-auto">
                            <span class="fs-1 text-white">🚀</span>
                        </div>
                        <h3 class="h4 fw-bold mb-3">고성능 처리</h3>
                        <p class="text-muted fs-5">
                            최적화된 데이터베이스 쿼리와 인메모리 캐싱으로
                            밀리초 단위의 빠른 응답 속도를 보장합니다.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 col-12 text-center">
                    <div class="p-4">
                        <div class="icon-shape icon-xl rounded-circle bg-success text-center mb-4 mx-auto">
                            <span class="fs-1 text-white">📈</span>
                        </div>
                        <h3 class="h4 fw-bold mb-3">무한 확장성</h3>
                        <p class="text-muted fs-5">
                            마이크로서비스와 샤딩 아키텍처로
                            사용자 증가에 따른 선형적 확장이 가능합니다.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 col-12 text-center">
                    <div class="p-4">
                        <div class="icon-shape icon-xl rounded-circle bg-warning text-center mb-4 mx-auto">
                            <span class="fs-1 text-white">🛡️</span>
                        </div>
                        <h3 class="h4 fw-bold mb-3">강력한 보안</h3>
                        <p class="text-muted fs-5">
                            다층 보안 시스템과 end-to-end 암호화로
                            완벽하게 보호되는 안전한 통신 환경을 제공합니다.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Technology Stack --}}
            <div class="row justify-content-center mt-8">
                <div class="col-lg-10 col-12">
                    <div class="bg-light rounded-3 p-6">
                        <h4 class="fw-bold text-center mb-4">💻 핵심 기술 스택</h4>
                        <div class="row g-3 text-center">
                            <div class="col-md-3 col-6">
                                <div class="border-end pe-3">
                                    <div class="fw-bold text-danger">Laravel 12</div>
                                    <small class="text-muted">Backend Framework</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="border-end pe-3">
                                    <div class="fw-bold text-primary">WebSocket</div>
                                    <small class="text-muted">Real-time Communication</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="border-end pe-3">
                                    <div class="fw-bold text-success">JWT Auth</div>
                                    <small class="text-muted">Secure Authentication</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="fw-bold text-info">Bootstrap 5</div>
                                <small class="text-muted">UI Framework</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Final CTA Section --}}
    <section class="py-8" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container my-lg-8">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8 col-md-12">
                    <h2 class="display-4 text-white fw-bold mb-4">지금 바로 시작하세요!</h2>
                    <p class="lead text-white-50 px-lg-8 mb-6">
                        혁신적인 기능들을 직접 체험하고 지니채팅만의 차별화된
                        커뮤니케이션 경험을 느껴보세요
                    </p>

                    <div class="d-grid d-md-block">
                        <a href="{{ route('chat.index') }}" class="btn btn-light btn-lg mb-2 mb-md-0 me-3">
                            <i class="fas fa-comments me-2"></i>무료로 시작하기
                        </a>
                        <a href="{{ route('chat.guide') }}" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-book-open me-2"></i>이용 가이드
                        </a>
                    </div>

                    {{-- Quick Stats --}}
                    <div class="row mt-8 text-center">
                        <div class="col-md-4 col-12 mb-3 mb-md-0">
                            <div class="text-white">
                                <h3 class="fw-bold mb-1">12+</h3>
                                <small class="text-white-50">활성 채팅방</small>
                            </div>
                        </div>
                        <div class="col-md-4 col-12 mb-3 mb-md-0">
                            <div class="text-white">
                                <h3 class="fw-bold mb-1">1,547+</h3>
                                <small class="text-white-50">전송된 메시지</small>
                            </div>
                        </div>
                        <div class="col-md-4 col-12">
                            <div class="text-white">
                                <h3 class="fw-bold mb-1">89+</h3>
                                <small class="text-white-50">활성 사용자</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection