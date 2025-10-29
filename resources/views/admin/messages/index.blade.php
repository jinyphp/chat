{{-- 관리자 메시지 관리 페이지 --}}
@extends('jiny-site::layouts.admin.sidebar')

@section('content')

    <div class="container-fluid py-5">
        <div class="container">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="h4 fw-semibold text-dark mb-4">전체 메시지 관리</h3>

                    {{-- 통계 --}}
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary-subtle border-primary">
                                <div class="card-body">
                                    <h4 class="fw-semibold text-primary">전체 메시지</h4>
                                    <p class="display-6 fw-bold text-primary mb-0">{{ $stats['total_messages'] ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success-subtle border-success">
                                <div class="card-body">
                                    <h4 class="fw-semibold text-success">오늘 메시지</h4>
                                    <p class="display-6 fw-bold text-success mb-0">{{ $stats['today_messages'] ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning-subtle border-warning">
                                <div class="card-body">
                                    <h4 class="fw-semibold text-warning">신고된 메시지</h4>
                                    <p class="display-6 fw-bold text-warning mb-0">{{ $stats['reported_messages'] ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger-subtle border-danger">
                                <div class="card-body">
                                    <h4 class="fw-semibold text-danger">삭제된 메시지</h4>
                                    <p class="display-6 fw-bold text-danger mb-0">{{ $stats['deleted_messages'] ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 검색 및 필터 --}}
                    <div class="mb-4">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label fw-semibold">검색</label>
                                <input type="text" name="search" id="search" value="{{ request('search') }}"
                                       placeholder="메시지 내용 검색..."
                                       class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label for="room_id" class="form-label fw-semibold">채팅방</label>
                                <select name="room_id" id="room_id" class="form-select">
                                    <option value="">전체 채팅방</option>
                                    {{-- 채팅방 목록이 들어갈 곳 --}}
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="type" class="form-label fw-semibold">타입</label>
                                <select name="type" id="type" class="form-select">
                                    <option value="">전체 타입</option>
                                    <option value="text" {{ request('type') === 'text' ? 'selected' : '' }}>텍스트</option>
                                    <option value="image" {{ request('type') === 'image' ? 'selected' : '' }}>이미지</option>
                                    <option value="file" {{ request('type') === 'file' ? 'selected' : '' }}>파일</option>
                                    <option value="system" {{ request('type') === 'system' ? 'selected' : '' }}>시스템</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    검색
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- 메시지 목록 --}}
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="fw-semibold text-muted small text-uppercase">ID</th>
                                    <th scope="col" class="fw-semibold text-muted small text-uppercase">내용</th>
                                    <th scope="col" class="fw-semibold text-muted small text-uppercase">발신자</th>
                                    <th scope="col" class="fw-semibold text-muted small text-uppercase">채팅방</th>
                                    <th scope="col" class="fw-semibold text-muted small text-uppercase">타입</th>
                                    <th scope="col" class="fw-semibold text-muted small text-uppercase">작성일</th>
                                    <th scope="col" class="fw-semibold text-muted small text-uppercase">액션</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($messages ?? [] as $message)
                                <tr class="{{ $message->is_deleted ? 'table-danger' : '' }}">
                                    <td class="text-nowrap small">{{ $message->id }}</td>
                                    <td>
                                        <div class="small text-truncate" style="max-width: 200px;">
                                            @if($message->is_deleted)
                                                <span class="text-danger fst-italic">삭제된 메시지</span>
                                            @else
                                                {{ $message->content }}
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-nowrap small">
                                        {{ $message->sender_name ?? '알 수 없음' }}
                                    </td>
                                    <td class="text-nowrap small">
                                        {{ $message->room_title ?? "방 #{$message->room_id}" }}
                                    </td>
                                    <td class="text-nowrap">
                                        <span class="badge {{ $message->type === 'system' ? 'bg-secondary' : 'bg-primary' }}">
                                            {{ $message->type }}
                                        </span>
                                    </td>
                                    <td class="text-nowrap small text-muted">
                                        {{ $message->created_at->format('Y-m-d H:i') }}
                                    </td>
                                    <td class="text-nowrap">
                                        @if(!$message->is_deleted)
                                            <button onclick="deleteMessage({{ $message->id }})"
                                                    class="btn btn-sm btn-outline-danger">
                                                삭제
                                            </button>
                                        @else
                                            <span class="text-muted small">삭제됨</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        메시지가 없습니다.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- 페이지네이션 --}}
                    @if(isset($messages) && method_exists($messages, 'links'))
                        <div class="mt-4">
                            {{ $messages->appends(request()->query())->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- JavaScript --}}
    <script>
        function deleteMessage(messageId) {
            if (confirm('정말 이 메시지를 삭제하시겠습니까?')) {
                fetch(`/admin/chat/messages/${messageId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                    },
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('삭제에 실패했습니다.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('오류가 발생했습니다.');
                });
            }
        }
    </script>
@endsection