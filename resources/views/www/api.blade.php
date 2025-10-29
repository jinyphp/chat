{{-- 지니채팅 API 문서 페이지 --}}
@extends('jiny-site::layouts.app')

@section('title', '지니채팅 API 문서')

@section('styles')
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
@endsection

@section('content')

    {{-- Hero Section --}}
    <section class="py-lg-8 py-7" style="background: linear-gradient(135deg, #6366f1 0%, #7c3aed 100%);">
        <div class="container my-lg-8">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8 col-md-12">
                    <h1 class="display-2 fw-bold text-white mb-4 ls-sm">🔧 API 문서</h1>
                    <p class="lead text-white-50 px-lg-8 mb-6">
                        개발자를 위한 강력하고 직관적인 지니채팅 RESTful API 가이드
                    </p>
                    <div class="d-grid d-md-block">
                        <a href="#endpoints" class="btn btn-light btn-lg me-3">
                            <i class="fas fa-code me-2"></i>API 탐색하기
                        </a>
                        <a href="{{ route('chat.docs') }}" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-book me-2"></i>개발 문서
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- API Overview Section --}}
    <section class="py-8 bg-white">
        <div class="container">
            <div class="row justify-content-center text-center mb-8">
                <div class="col-lg-8 col-md-12">
                    <h2 class="display-4 fw-bold mb-3">API 개요</h2>
                    <p class="lead text-muted">RESTful API 설계 원칙을 따라 직관적이고 사용하기 쉬운 API를 제공합니다</p>
                </div>
            </div>

            <div class="row g-6 mb-8">
                <div class="col-lg-4 col-md-6 col-12 text-center">
                    <div class="card h-100 border-0 shadow-sm card-hover">
                        <div class="card-body p-6">
                            <div class="icon-shape icon-xl rounded-circle bg-primary text-center mb-4 mx-auto">
                                <i class="fas fa-link text-white fs-2"></i>
                            </div>
                            <h4 class="fw-bold mb-3">Base URL</h4>
                            <div class="bg-primary bg-opacity-10 rounded-3 p-3 border-start border-primary border-4">
                                <code class="text-primary fw-semibold">/api/chat</code>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 col-12 text-center">
                    <div class="card h-100 border-0 shadow-sm card-hover">
                        <div class="card-body p-6">
                            <div class="icon-shape icon-xl rounded-circle bg-success text-center mb-4 mx-auto">
                                <i class="fas fa-key text-white fs-2"></i>
                            </div>
                            <h4 class="fw-bold mb-3">인증</h4>
                            <div class="bg-success bg-opacity-10 rounded-3 p-3 border-start border-success border-4">
                                <code class="text-success fw-semibold">Bearer Token</code>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 col-12 text-center">
                    <div class="card h-100 border-0 shadow-sm card-hover">
                        <div class="card-body p-6">
                            <div class="icon-shape icon-xl rounded-circle bg-info text-center mb-4 mx-auto">
                                <i class="fas fa-file-code text-white fs-2"></i>
                            </div>
                            <h4 class="fw-bold mb-3">응답 형식</h4>
                            <div class="bg-info bg-opacity-10 rounded-3 p-3 border-start border-info border-4">
                                <code class="text-info fw-semibold">JSON</code>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Authentication Guide --}}
            <div class="card border-0 shadow-lg bg-gray-50">
                <div class="card-body p-6">
                    <div class="d-flex align-items-center mb-4">
                        <div class="icon-shape icon-lg rounded-circle bg-warning text-center me-3">
                            <i class="fas fa-shield-alt text-white"></i>
                        </div>
                        <h3 class="fw-bold mb-0">인증 방법</h3>
                    </div>
                    <p class="fs-5 text-muted mb-4">
                        모든 API 요청은 JWT 토큰을 사용한 Bearer 인증이 필요합니다.
                    </p>
                    <div class="bg-dark rounded-3 p-4 overflow-auto">
                        <pre class="text-success small mb-0 font-monospace"><code>curl -H "Authorization: Bearer YOUR_JWT_TOKEN" \
     -H "Content-Type: application/json" \
     -X GET https://yourdomain.com/api/chat/rooms</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- API Endpoints Section --}}
    <section id="endpoints" class="py-8 bg-gray-100">
        <div class="container">
            <div class="row justify-content-center text-center mb-8">
                <div class="col-lg-8 col-md-12">
                    <h2 class="display-4 fw-bold mb-3">📋 API 엔드포인트</h2>
                    <p class="lead text-muted">사용 가능한 모든 API 엔드포인트와 상세 설명</p>
                </div>
            </div>

            <div class="d-flex flex-column gap-4">
                @foreach($endpoints as $endpoint)
                    <div class="card border-0 shadow-sm card-hover h-100">
                        <div class="card-body p-5">
                            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between mb-4">
                                <div class="d-flex align-items-center mb-3 mb-lg-0">
                                    <span class="badge me-4 px-4 py-3 fs-6 fw-bold
                                        {{ $endpoint['method'] === 'GET' ? 'bg-primary' : '' }}
                                        {{ $endpoint['method'] === 'POST' ? 'bg-success' : '' }}
                                        {{ $endpoint['method'] === 'PUT' ? 'bg-warning text-dark' : '' }}
                                        {{ $endpoint['method'] === 'DELETE' ? 'bg-danger' : '' }}">
                                        {{ $endpoint['method'] }}
                                    </span>
                                    <code class="fs-5 fw-semibold text-dark bg-light px-3 py-2 rounded">{{ $endpoint['endpoint'] }}</code>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-lock text-warning me-2"></i>
                                    <span class="badge bg-warning text-dark px-3 py-2 fw-semibold">
                                        {{ $endpoint['auth'] }}
                                    </span>
                                </div>
                            </div>
                            <p class="text-muted fs-5 mb-0">{{ $endpoint['description'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Response Examples Section --}}
    <section class="py-8 bg-white">
        <div class="container">
            <div class="row justify-content-center text-center mb-8">
                <div class="col-lg-8 col-md-12">
                    <h2 class="display-4 fw-bold mb-3">📄 응답 예제</h2>
                    <p class="lead text-muted">일반적인 API 응답 형식과 실제 예제</p>
                </div>
            </div>

            <div class="row g-6">
                {{-- 성공 응답 --}}
                <div class="col-lg-6 col-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-5">
                            <div class="d-flex align-items-center mb-4">
                                <div class="icon-shape icon-lg rounded-circle bg-success text-center me-3">
                                    <i class="fas fa-check text-white"></i>
                                </div>
                                <h3 class="fw-bold mb-0">성공 응답</h3>
                            </div>
                            <div class="bg-dark rounded-3 p-4 overflow-auto">
                                <pre class="text-success small mb-0 font-monospace"><code>{
  "success": true,
  "data": {
    "id": 1,
    "title": "일반 채팅",
    "description": "자유로운 대화 공간",
    "type": "public",
    "is_public": true,
    "participants_count": 15,
    "created_at": "2024-01-01T00:00:00Z"
  },
  "meta": {
    "timestamp": "2024-01-01T12:00:00Z"
  }
}</code></pre>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 오류 응답 --}}
                <div class="col-lg-6 col-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-5">
                            <div class="d-flex align-items-center mb-4">
                                <div class="icon-shape icon-lg rounded-circle bg-danger text-center me-3">
                                    <i class="fas fa-times text-white"></i>
                                </div>
                                <h3 class="fw-bold mb-0">오류 응답</h3>
                            </div>
                            <div class="bg-dark rounded-3 p-4 overflow-auto">
                                <pre class="text-danger small mb-0 font-monospace"><code>{
  "success": false,
  "error": {
    "code": 401,
    "message": "Unauthorized",
    "details": "JWT token is invalid or expired"
  },
  "meta": {
    "timestamp": "2024-01-01T12:00:00Z"
  }
}</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 페이지네이션 응답 --}}
            <div class="row justify-content-center mt-8">
                <div class="col-lg-10 col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-5">
                            <div class="d-flex align-items-center mb-4">
                                <div class="icon-shape icon-lg rounded-circle bg-info text-center me-3">
                                    <i class="fas fa-list text-white"></i>
                                </div>
                                <h3 class="fw-bold mb-0">페이지네이션 응답</h3>
                            </div>
                            <div class="bg-dark rounded-3 p-4 overflow-auto">
                                <pre class="text-info small mb-0 font-monospace"><code>{
  "success": true,
  "data": [
    // ... 채팅방 목록
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 95,
    "from": 1,
    "to": 20
  }
}</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- HTTP Status Codes Section --}}
    <section class="py-8 bg-dark text-white">
        <div class="container">
            <div class="row justify-content-center text-center mb-8">
                <div class="col-lg-8 col-md-12">
                    <h2 class="display-4 fw-bold mb-3">📊 HTTP 상태 코드</h2>
                    <p class="lead text-white-50">API에서 사용되는 주요 HTTP 상태 코드와 의미</p>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-3 col-md-6">
                    <div class="bg-success rounded-3 p-4 text-center">
                        <div class="display-5 fw-bold mb-2">200</div>
                        <div class="h5 mb-2">OK</div>
                        <div class="small text-success-emphasis">요청 성공</div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="bg-primary rounded-3 p-4 text-center">
                        <div class="display-5 fw-bold mb-2">201</div>
                        <div class="h5 mb-2">Created</div>
                        <div class="small text-primary-emphasis">리소스 생성 성공</div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="bg-danger rounded-3 p-4 text-center">
                        <div class="display-5 fw-bold mb-2">401</div>
                        <div class="h5 mb-2">Unauthorized</div>
                        <div class="small text-danger-emphasis">인증 실패</div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="bg-warning rounded-3 p-4 text-center">
                        <div class="display-5 fw-bold mb-2">404</div>
                        <div class="h5 mb-2">Not Found</div>
                        <div class="small text-warning-emphasis">리소스 없음</div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="bg-info rounded-3 p-4 text-center">
                        <div class="display-5 fw-bold mb-2">422</div>
                        <div class="h5 mb-2">Validation Error</div>
                        <div class="small text-info-emphasis">유효성 검사 실패</div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="bg-secondary rounded-3 p-4 text-center">
                        <div class="display-5 fw-bold mb-2">429</div>
                        <div class="h5 mb-2">Rate Limited</div>
                        <div class="small text-secondary-emphasis">요청 제한 초과</div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="bg-dark-subtle rounded-3 p-4 text-center border">
                        <div class="display-5 fw-bold mb-2 text-dark">500</div>
                        <div class="h5 mb-2 text-dark">Server Error</div>
                        <div class="small text-dark-emphasis">서버 내부 오류</div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="bg-light rounded-3 p-4 text-center">
                        <div class="display-5 fw-bold mb-2 text-dark">503</div>
                        <div class="h5 mb-2 text-dark">Unavailable</div>
                        <div class="small text-dark-emphasis">서비스 이용 불가</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Limitations Section --}}
    <section class="py-8 bg-warning-subtle">
        <div class="container">
            <div class="row justify-content-center text-center mb-8">
                <div class="col-lg-8 col-md-12">
                    <h2 class="display-4 fw-bold mb-3">⚠️ 제한사항 및 주의사항</h2>
                    <p class="lead text-muted">API 사용 시 알아두어야 할 중요한 정보와 정책</p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h3 class="h4 fw-bold mb-3">📊 Rate Limiting</h3>
                            <ul class="list-unstyled">
                                <li class="mb-2">• 분당 최대 60회 요청</li>
                                <li class="mb-2">• 메시지 전송: 분당 최대 10회</li>
                                <li class="mb-2">• 채팅방 생성: 시간당 최대 5개</li>
                                <li class="mb-0">• 제한 초과 시 429 상태 코드 반환</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h3 class="h4 fw-bold mb-3">🔐 보안 정책</h3>
                            <ul class="list-unstyled">
                                <li class="mb-2">• JWT 토큰 유효기간: 24시간</li>
                                <li class="mb-2">• HTTPS 연결 필수</li>
                                <li class="mb-2">• 토큰 탈취 시 즉시 갱신 필요</li>
                                <li class="mb-0">• API 키 노출 금지</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h3 class="h4 fw-bold mb-3">📝 데이터 형식</h3>
                            <ul class="list-unstyled">
                                <li class="mb-2">• 요청/응답 모두 JSON 형식</li>
                                <li class="mb-2">• UTF-8 인코딩 사용</li>
                                <li class="mb-2">• 날짜 형식: ISO 8601</li>
                                <li class="mb-0">• 최대 페이로드 크기: 10MB</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h3 class="h4 fw-bold mb-3">🕐 서비스 시간</h3>
                            <ul class="list-unstyled">
                                <li class="mb-2">• 24/7 서비스 제공</li>
                                <li class="mb-2">• 정기 점검: 매주 일요일 02:00-04:00</li>
                                <li class="mb-2">• 응급 점검 시 사전 공지</li>
                                <li class="mb-0">• 점검 중 503 상태 코드 반환</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Developer Support Section --}}
    <section class="py-8" style="background: linear-gradient(135deg, #6366f1 0%, #7c3aed 100%);">
        <div class="container my-lg-8">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8 col-md-12">
                    <h2 class="display-4 text-white fw-bold mb-4">🛠️ 개발자 지원</h2>
                    <p class="lead text-white-50 px-lg-8 mb-6">
                        API 관련 문의사항이 있으시면 언제든지 연락주세요.
                        전문 개발팀이 신속하게 지원해드립니다.
                    </p>

                    <div class="d-grid d-md-block">
                        <a href="{{ route('chat.docs') }}" class="btn btn-light btn-lg mb-2 mb-md-0 me-3">
                            <i class="fas fa-book me-2"></i>개발 문서 보기
                        </a>
                        <a href="mailto:support@example.com" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-envelope me-2"></i>기술 지원 문의
                        </a>
                    </div>

                    {{-- Support Stats --}}
                    <div class="row mt-8 text-center">
                        <div class="col-md-4 col-12 mb-3 mb-md-0">
                            <div class="text-white">
                                <h3 class="fw-bold mb-1">24/7</h3>
                                <small class="text-white-50">기술 지원</small>
                            </div>
                        </div>
                        <div class="col-md-4 col-12 mb-3 mb-md-0">
                            <div class="text-white">
                                <h3 class="fw-bold mb-1">< 2시간</h3>
                                <small class="text-white-50">평균 응답 시간</small>
                            </div>
                        </div>
                        <div class="col-md-4 col-12">
                            <div class="text-white">
                                <h3 class="fw-bold mb-1">99.9%</h3>
                                <small class="text-white-50">API 가용률</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection