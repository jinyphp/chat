{{-- 관리자 사용자 관리 페이지 --}}
@extends('jiny-site::layouts.admin.sidebar')

@section('content')

    <div class="container-fluid py-5">
        <div class="container">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="h4 fw-semibold text-dark mb-4">채팅 사용자 관리</h3>

                    {{-- 통계 --}}
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary-subtle border-primary">
                                <div class="card-body">
                                    <h4 class="fw-semibold text-primary">전체 참여자</h4>
                                    <p class="display-6 fw-bold text-primary mb-0">{{ $stats['total_participants'] ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success-subtle border-success">
                                <div class="card-body">
                                    <h4 class="fw-semibold text-success">활성 사용자</h4>
                                    <p class="display-6 fw-bold text-success mb-0">{{ $stats['active_users'] ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning-subtle border-warning">
                                <div class="card-body">
                                    <h4 class="fw-semibold text-warning">오늘 접속</h4>
                                    <p class="display-6 fw-bold text-warning mb-0">{{ $stats['today_active'] ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger-subtle border-danger">
                                <div class="card-body">
                                    <h4 class="fw-semibold text-danger">차단된 사용자</h4>
                                    <p class="display-6 fw-bold text-danger mb-0">{{ $stats['banned_users'] ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 검색 및 필터 --}}
                    <div class="mb-4">
                        <form method="GET" class="row g-3">
                            <div class="col-md-5">
                                <label for="search" class="form-label fw-semibold">검색</label>
                                <input type="text" name="search" id="search" value="{{ request('search') }}"
                                       placeholder="사용자 이름 또는 이메일 검색..."
                                       class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label for="status" class="form-label fw-semibold">상태</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="">전체</option>
                                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>활성</option>
                                    <option value="banned" {{ request('status') === 'banned' ? 'selected' : '' }}>차단</option>
                                    <option value="left" {{ request('status') === 'left' ? 'selected' : '' }}>탈퇴</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    검색
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- 사용자 목록 --}}
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="fw-semibold text-muted small text-uppercase">사용자</th>
                                    <th scope="col" class="fw-semibold text-muted small text-uppercase">참여 채팅방</th>
                                    <th scope="col" class="fw-semibold text-muted small text-uppercase">메시지 수</th>
                                    <th scope="col" class="fw-semibold text-muted small text-uppercase">마지막 활동</th>
                                    <th scope="col" class="fw-semibold text-muted small text-uppercase">상태</th>
                                    <th scope="col" class="fw-semibold text-muted small text-uppercase">액션</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($users ?? [] as $user)
                                <tr class="{{ $user->is_banned ? 'table-danger' : '' }}">
                                    <td class="text-nowrap">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <span class="small fw-semibold text-white">
                                                        {{ substr($user->name ?? 'U', 0, 1) }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="small fw-semibold text-dark">{{ $user->name ?? '알 수 없음' }}</div>
                                                <div class="small text-muted">{{ $user->email ?? 'N/A' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-nowrap small">
                                        {{ $user->room_count ?? 0 }}개
                                    </td>
                                    <td class="text-nowrap small">
                                        {{ $user->message_count ?? 0 }}개
                                    </td>
                                    <td class="text-nowrap small text-muted">
                                        {{ $user->last_activity_at ? $user->last_activity_at->diffForHumans() : '없음' }}
                                    </td>
                                    <td class="text-nowrap">
                                        @if($user->is_banned ?? false)
                                            <span class="badge bg-danger">
                                                차단됨
                                            </span>
                                        @else
                                            <span class="badge bg-success">
                                                활성
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <div class="d-flex gap-2">
                                            @if($user->is_banned ?? false)
                                                <form method="POST" action="{{ route('admin.chat.users.unban', $user->uuid ?? 'unknown') }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('이 사용자의 차단을 해제하시겠습니까?')">
                                                        차단해제
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('admin.chat.users.ban', $user->uuid ?? 'unknown') }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('이 사용자를 차단하시겠습니까?')">
                                                        차단
                                                    </button>
                                                </form>
                                            @endif

                                            <button onclick="viewUserDetail('{{ $user->uuid ?? 'unknown' }}')"
                                                    class="btn btn-sm btn-outline-primary">
                                                상세
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        사용자가 없습니다.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- 페이지네이션 --}}
                    @if(isset($users) && method_exists($users, 'links'))
                        <div class="mt-4">
                            {{ $users->appends(request()->query())->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- 사용자 상세 모달 --}}
    <div class="modal fade" id="userDetailModal" tabindex="-1" aria-labelledby="userDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userDetailModalLabel">사용자 상세 정보</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="userDetailContent">
                        <p class="text-center text-muted">로딩 중...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        닫기
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- JavaScript --}}
    <script>
        function viewUserDetail(userUuid) {
            const modal = new bootstrap.Modal(document.getElementById('userDetailModal'));
            document.getElementById('userDetailContent').innerHTML = '<p class="text-center text-muted">로딩 중...</p>';
            modal.show();

            // TODO: 실제 사용자 상세 정보 API 호출
            setTimeout(() => {
                document.getElementById('userDetailContent').innerHTML = `
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex">
                            <span class="fw-semibold me-2">UUID:</span>
                            <span class="text-muted">${userUuid}</span>
                        </div>
                        <div class="d-flex">
                            <span class="fw-semibold me-2">참여 채팅방:</span>
                            <span class="text-muted">상세 정보 준비 중</span>
                        </div>
                        <div class="d-flex">
                            <span class="fw-semibold me-2">활동 내역:</span>
                            <span class="text-muted">상세 정보 준비 중</span>
                        </div>
                    </div>
                `;
            }, 1000);
        }
    </script>
@endsection