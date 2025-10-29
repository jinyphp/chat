{{-- 지니채팅 시스템 소개 메인 페이지 --}}
@extends('jiny-site::layouts.app')

@section('title', '지니채팅 - 실시간 채팅 플랫폼')

@section('styles')
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
@endsection

@section('content')
    {{-- Hero Section --}}
    <section class="py-lg-8 py-7 bg-white">
        <div class="container my-lg-8">
            <div class="row align-items-center">
                <div class="col-xl-6 col-lg-6 col-md-12">
                    <div class="mb-4 mb-xl-0 text-center text-md-start">
                        <h1 class="display-2 fw-bold mb-3 ls-sm">💬 지니채팅</h1>
                        <p class="mb-4 lead">빠르고 안전한 실시간 채팅 플랫폼으로 JWT 인증, 사용자 샤딩, 실시간 브로드캐스팅을 지원하는 차세대 채팅 시스템입니다.</p>

                        <!-- Key Features List -->
                        <div class="mb-6">
                            <ul class="list-unstyled fs-5">
                                <li class="mb-2">
                                    <span class="me-2"><i class="fe fe-shield text-success"></i></span>
                                    <span class="align-text-top fw-semibold">JWT 기반 보안 인증</span>
                                </li>
                                <li class="mb-2">
                                    <span class="me-2"><i class="fe fe-zap text-warning"></i></span>
                                    <span class="align-text-top fw-semibold">실시간 메시지 전송</span>
                                </li>
                                <li class="mb-2">
                                    <span class="me-2"><i class="fe fe-users text-primary"></i></span>
                                    <span class="align-text-top fw-semibold">{{ number_format($stats['active_users']) }}+ 활성 사용자</span>
                                </li>
                            </ul>
                        </div>

                        <div class="d-grid d-md-block">
                            <a href="/home/chat/rooms/create" class="btn btn-success btn-lg fs-4 me-2">
                                <i class="fe fe-plus me-2"></i>채팅방 만들기
                            </a>
                            <a href="{{ route('chat.features') }}" class="btn btn-outline-primary btn-lg fs-4">
                                <i class="fe fe-list me-2"></i>기능 살펴보기
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 col-lg-6 col-md-12">
                    <div class="text-center">
                        <div class="position-relative">
                            <div class="bg-light rounded-3 p-6 shadow-lg">
                                <div class="row text-center g-4">
                                    <div class="col-4">
                                        <div class="icon-shape icon-lg rounded-circle bg-success text-center mb-3 mx-auto">
                                            <i class="fe fe-message-circle text-white fs-3"></i>
                                        </div>
                                        <h5 class="fw-bold text-dark">{{ number_format($stats['total_rooms']) }}</h5>
                                        <p class="text-muted small mb-0">활성 채팅방</p>
                                    </div>
                                    <div class="col-4">
                                        <div class="icon-shape icon-lg rounded-circle bg-primary text-center mb-3 mx-auto">
                                            <i class="fe fe-send text-white fs-3"></i>
                                        </div>
                                        <h5 class="fw-bold text-dark">{{ number_format($stats['total_messages']) }}</h5>
                                        <p class="text-muted small mb-0">전송 메시지</p>
                                    </div>
                                    <div class="col-4">
                                        <div class="icon-shape icon-lg rounded-circle bg-info text-center mb-3 mx-auto">
                                            <i class="fe fe-users text-white fs-3"></i>
                                        </div>
                                        <h5 class="fw-bold text-dark">{{ number_format($stats['active_users']) }}</h5>
                                        <p class="text-muted small mb-0">활성 사용자</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Features Section --}}
    <section class="py-4 shadow-sm position-relative bg-light">
        <div class="container">
            <div class="row">
                @foreach($features as $feature)
                <div class="col-lg-4 col-md-6 col-12 mb-3">
                    <div class="text-dark fw-semibold lh-1 fs-5">
                        <span class="icon-shape icon-sm rounded-circle bg-light-primary text-center me-3">
                            <span class="fs-4">{{ $feature['icon'] }}</span>
                        </span>
                        <span class="align-middle">{{ $feature['title'] }}</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Statistics Section --}}
    <section class="py-8 bg-white">
        <div class="container my-lg-4">
            <div class="row">
                <div class="col-md-6 offset-right-md-6">
                    <h1 class="display-4 fw-bold mb-3">실시간 채팅의 새로운 기준</h1>
                    <p class="lead">지니채팅은 최신 기술 스택을 기반으로 안정적이고 확장 가능한 채팅 서비스를 제공합니다. 전 세계 수많은 사용자들이 신뢰하는 플랫폼입니다.</p>
                </div>

                <div class="col-lg-3 col-md-6 col-6">
                    <div class="border-top border-primary pt-4 mt-6 mb-5">
                        <h1 class="display-3 fw-bold mb-0 text-primary">{{ number_format($stats['total_rooms']) }}</h1>
                        <p class="text-uppercase text-muted">활성 채팅방</p>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-6">
                    <div class="border-top border-success pt-4 mt-6 mb-5">
                        <h1 class="display-3 fw-bold mb-0 text-success">{{ number_format($stats['total_messages']) }}</h1>
                        <p class="text-uppercase text-muted">전송된 메시지</p>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-6">
                    <div class="border-top border-info pt-4 mt-6 mb-5">
                        <h1 class="display-3 fw-bold mb-0 text-info">{{ number_format($stats['active_users']) }}</h1>
                        <p class="text-uppercase text-muted">활성 사용자</p>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-6">
                    <div class="border-top border-warning pt-4 mt-6 mb-5">
                        <h1 class="display-3 fw-bold mb-0 text-warning">24/7</h1>
                        <p class="text-uppercase text-muted">실시간 지원</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Detailed Features Section --}}
    <section class="py-8 bg-gray-100">
        <div class="container">
            <div class="row justify-content-center text-center mb-8">
                <div class="col-lg-8 col-md-12">
                    <h2 class="display-4 fw-bold">강력한 기능들</h2>
                    <p class="lead">지니채팅이 제공하는 다양한 기능들로 더욱 효율적인 커뮤니케이션을 경험하세요</p>
                </div>
            </div>

            <div class="row g-4">
                @foreach($features as $index => $feature)
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="card h-100 shadow-sm border-0 card-hover">
                        <div class="card-body p-5 text-center">
                            <div class="icon-shape icon-xl rounded-circle
                                @if($index % 4 == 0) bg-primary
                                @elseif($index % 4 == 1) bg-success
                                @elseif($index % 4 == 2) bg-warning
                                @else bg-info @endif
                                text-center mb-4 mx-auto">
                                <span class="fs-1 text-white">{{ $feature['icon'] }}</span>
                            </div>
                            <h4 class="fw-bold mb-3">{{ $feature['title'] }}</h4>
                            <p class="text-muted">{{ $feature['description'] }}</p>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Technology Stack Section --}}
    <section class="py-8 bg-white">
        <div class="container">
            <div class="row justify-content-center text-center mb-8">
                <div class="col-lg-8 col-md-12">
                    <h2 class="display-4 fw-bold">최신 기술 스택</h2>
                    <p class="lead">안정적이고 확장 가능한 최신 기술로 구축된 차세대 채팅 플랫폼</p>
                </div>
            </div>

            <div class="row g-4 text-center">
                <div class="col-lg-3 col-md-6 col-6">
                    <div class="p-4">
                        <div class="icon-shape icon-xl rounded-circle bg-danger text-center mb-4 mx-auto">
                            <span class="fw-bold text-white fs-2">L</span>
                        </div>
                        <h5 class="fw-bold">Laravel 12</h5>
                        <p class="text-muted">최신 PHP 프레임워크</p>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-6">
                    <div class="p-4">
                        <div class="icon-shape icon-xl rounded-circle bg-success text-center mb-4 mx-auto">
                            <i class="fe fe-shield text-white fs-2"></i>
                        </div>
                        <h5 class="fw-bold">JWT Authentication</h5>
                        <p class="text-muted">안전한 토큰 기반 인증</p>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-6">
                    <div class="p-4">
                        <div class="icon-shape icon-xl rounded-circle bg-primary text-center mb-4 mx-auto">
                            <i class="fe fe-wifi text-white fs-2"></i>
                        </div>
                        <h5 class="fw-bold">WebSocket</h5>
                        <p class="text-muted">실시간 양방향 통신</p>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-6">
                    <div class="p-4">
                        <div class="icon-shape icon-xl rounded-circle bg-info text-center mb-4 mx-auto">
                            <i class="fe fe-layout text-white fs-2"></i>
                        </div>
                        <h5 class="fw-bold">Bootstrap 5</h5>
                        <p class="text-muted">반응형 UI 프레임워크</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA Section --}}
    <section class="py-8 bg-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container my-lg-8">
            <div class="row justify-content-center text-center">
                <div class="col-md-9 col-12">
                    <h2 class="display-4 text-white fw-bold">지금 바로 시작하세요!</h2>
                    <p class="lead text-white-50 px-lg-8 mb-6">몇 분만에 채팅방을 만들고 친구들과 실시간으로 소통을 시작할 수 있습니다. 무료로 체험해보세요.</p>

                    <div class="d-grid d-md-block">
                        <a href="/home/chat/rooms/create" class="btn btn-light btn-lg mb-2 mb-md-0 me-3">
                            <i class="fe fe-plus me-2"></i>무료로 시작하기
                        </a>
                        <a href="{{ route('chat.guide') }}" class="btn btn-outline-light btn-lg">
                            <i class="fe fe-book-open me-2"></i>이용 가이드
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Quick Links Section --}}
    <section class="py-8 bg-gray-100">
        <div class="container">
            <div class="row justify-content-center text-center mb-6">
                <div class="col-lg-8 col-md-12">
                    <h3 class="fw-bold">더 자세한 정보</h3>
                    <p class="text-muted">지니채팅에 대해 더 알아보고 싶으신가요?</p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-3 col-md-6 col-12">
                    <a href="{{ route('chat.features') }}" class="card text-decoration-none shadow-sm border-0 card-hover h-100">
                        <div class="card-body text-center p-4">
                            <div class="icon-shape icon-lg rounded-circle bg-primary text-center mb-3 mx-auto">
                                <i class="fe fe-list text-white fs-4"></i>
                            </div>
                            <h5 class="card-title fw-semibold text-dark">기능 소개</h5>
                            <p class="card-text text-muted small">상세한 기능 목록을 확인하세요</p>
                        </div>
                    </a>
                </div>

                <div class="col-lg-3 col-md-6 col-12">
                    <a href="{{ route('chat.guide') }}" class="card text-decoration-none shadow-sm border-0 card-hover h-100">
                        <div class="card-body text-center p-4">
                            <div class="icon-shape icon-lg rounded-circle bg-success text-center mb-3 mx-auto">
                                <i class="fe fe-book-open text-white fs-4"></i>
                            </div>
                            <h5 class="card-title fw-semibold text-dark">이용 가이드</h5>
                            <p class="card-text text-muted small">단계별 사용법을 알아보세요</p>
                        </div>
                    </a>
                </div>

                <div class="col-lg-3 col-md-6 col-12">
                    <a href="{{ route('chat.api') }}" class="card text-decoration-none shadow-sm border-0 card-hover h-100">
                        <div class="card-body text-center p-4">
                            <div class="icon-shape icon-lg rounded-circle bg-warning text-center mb-3 mx-auto">
                                <i class="fe fe-code text-white fs-4"></i>
                            </div>
                            <h5 class="card-title fw-semibold text-dark">API 문서</h5>
                            <p class="card-text text-muted small">개발자를 위한 API 가이드</p>
                        </div>
                    </a>
                </div>

                <div class="col-lg-3 col-md-6 col-12">
                    <a href="{{ route('chat.docs') }}" class="card text-decoration-none shadow-sm border-0 card-hover h-100">
                        <div class="card-body text-center p-4">
                            <div class="icon-shape icon-lg rounded-circle bg-info text-center mb-3 mx-auto">
                                <i class="fe fe-file-text text-white fs-4"></i>
                            </div>
                            <h5 class="card-title fw-semibold text-dark">개발 문서</h5>
                            <p class="card-text text-muted small">기술 스택과 아키텍처 정보</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection