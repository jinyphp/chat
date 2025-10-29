{{-- 관리자 채팅방 상세 페이지 --}}
@extends('jiny-site::layouts.admin.sidebar')

@section('content')

    <div class="container-fluid py-5">
        <div class="container">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="mb-4">
                        <a href="{{ route('admin.chat.rooms.index') }}" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-2"></i>목록으로 돌아가기
                        </a>
                    </div>

                    <h3 class="h4 fw-semibold text-dark mb-4">채팅방 ID: {{ $id }}</h3>

                    {{-- 채팅방 기본 정보 --}}
                    <div class="card bg-light mb-4">
                        <div class="card-body">
                            <h4 class="h6 fw-semibold text-dark mb-3">기본 정보</h4>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <span class="small fw-semibold text-muted">제목:</span>
                                    <span class="small text-dark ms-2">채팅방 제목</span>
                                </div>
                                <div class="col-md-6">
                                    <span class="small fw-semibold text-muted">방장:</span>
                                    <span class="small text-dark ms-2">방장 이름</span>
                                </div>
                                <div class="col-md-6">
                                    <span class="small fw-semibold text-muted">상태:</span>
                                    <span class="badge bg-success ms-2">active</span>
                                </div>
                                <div class="col-md-6">
                                    <span class="small fw-semibold text-muted">참여자 수:</span>
                                    <span class="small text-dark ms-2">0명</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 참여자 목록 --}}
                    <div class="mb-4">
                        <h4 class="h6 fw-semibold text-dark mb-3">참여자 목록</h4>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col" class="fw-semibold text-muted small text-uppercase">사용자</th>
                                        <th scope="col" class="fw-semibold text-muted small text-uppercase">역할</th>
                                        <th scope="col" class="fw-semibold text-muted small text-uppercase">참여일</th>
                                        <th scope="col" class="fw-semibold text-muted small text-uppercase">상태</th>
                                        <th scope="col" class="fw-semibold text-muted small text-uppercase">액션</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4 small">
                                            참여자가 없습니다.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- 최근 메시지 --}}
                    <div class="mb-4">
                        <h4 class="h6 fw-semibold text-dark mb-3">최근 메시지 (최근 20개)</h4>
                        <div class="card bg-light" style="max-height: 400px; overflow-y: auto;">
                            <div class="card-body">
                                <div class="text-center text-muted small py-4">
                                    메시지가 없습니다.
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 관리 액션 --}}
                    <div class="border-top pt-4">
                        <h4 class="h6 fw-semibold text-dark mb-3">관리 액션</h4>
                        <div class="d-flex gap-3">
                            <form method="POST" action="{{ route('admin.chat.rooms.close', $id) }}" class="d-inline">
                                @csrf
                                <button type="submit"
                                        class="btn btn-danger"
                                        onclick="return confirm('정말 이 채팅방을 종료하시겠습니까?')">
                                    <i class="fas fa-times me-2"></i>채팅방 종료
                                </button>
                            </form>

                            <button type="button"
                                    class="btn btn-secondary"
                                    onclick="alert('기능 준비 중입니다.')">
                                <i class="fas fa-cog me-2"></i>설정 변경
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection