{{-- 관리자 채팅방 관리 페이지 --}}
@extends('jiny-site::layouts.admin.sidebar')

@section('content')

    <div class="container-fluid py-5">
        <div class="container">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="h4 fw-semibold text-dark mb-4">전체 채팅방 목록</h3>

                    {{-- 통계 --}}
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary-subtle border-primary">
                                <div class="card-body">
                                    <h4 class="fw-semibold text-primary">전체 채팅방</h4>
                                    <p class="display-6 fw-bold text-primary mb-0">{{ $stats['total_rooms'] ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success-subtle border-success">
                                <div class="card-body">
                                    <h4 class="fw-semibold text-success">활성 채팅방</h4>
                                    <p class="display-6 fw-bold text-success mb-0">{{ $stats['active_rooms'] ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning-subtle border-warning">
                                <div class="card-body">
                                    <h4 class="fw-semibold text-warning">오늘 생성</h4>
                                    <p class="display-6 fw-bold text-warning mb-0">{{ $stats['today_rooms'] ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger-subtle border-danger">
                                <div class="card-body">
                                    <h4 class="fw-semibold text-danger">비활성 채팅방</h4>
                                    <p class="display-6 fw-bold text-danger mb-0">{{ $stats['inactive_rooms'] ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 채팅방 목록 테이블 --}}
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="fw-semibold text-muted small text-uppercase">ID</th>
                                    <th scope="col" class="fw-semibold text-muted small text-uppercase">제목</th>
                                    <th scope="col" class="fw-semibold text-muted small text-uppercase">방장</th>
                                    <th scope="col" class="fw-semibold text-muted small text-uppercase">참여자</th>
                                    <th scope="col" class="fw-semibold text-muted small text-uppercase">상태</th>
                                    <th scope="col" class="fw-semibold text-muted small text-uppercase">생성일</th>
                                    <th scope="col" class="fw-semibold text-muted small text-uppercase">액션</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rooms ?? [] as $room)
                                <tr>
                                    <td class="text-nowrap small">{{ $room->id }}</td>
                                    <td>
                                        <div class="small fw-semibold text-dark">{{ $room->title }}</div>
                                        <div class="small text-muted">{{ Str::limit($room->description, 50) }}</div>
                                    </td>
                                    <td class="text-nowrap small">{{ $room->owner_name ?? '없음' }}</td>
                                    <td class="text-nowrap small">{{ $room->participant_count ?? 0 }}</td>
                                    <td class="text-nowrap">
                                        <span class="badge {{ $room->status === 'active' ? 'bg-success' : 'bg-danger' }}">
                                            {{ $room->status }}
                                        </span>
                                    </td>
                                    <td class="text-nowrap small text-muted">{{ $room->created_at->format('Y-m-d H:i') }}</td>
                                    <td class="text-nowrap">
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('admin.chat.rooms.show', $room->id) }}" class="btn btn-sm btn-outline-primary">상세</a>
                                            @if($room->status === 'active')
                                                <form method="POST" action="{{ route('admin.chat.rooms.close', $room->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('정말 이 채팅방을 종료하시겠습니까?')">종료</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        등록된 채팅방이 없습니다.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- 페이지네이션 --}}
                    @if(isset($rooms) && method_exists($rooms, 'links'))
                        <div class="mt-4">
                            {{ $rooms->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection