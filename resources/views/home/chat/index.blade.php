{{-- 채팅 대시보드 메인 페이지 --}}
@extends('jiny-site::layouts.home')

@section('content')

    <div class="container-fluid py-5">

        {{-- 헤더 --}}
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-2">
                            <i class="fas fa-comments text-primary"></i>
                            채팅 대시보드
                        </h2>
                        <p class="text-muted mb-0">참여 중인 채팅방과 최근 활동을 확인하세요</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('home.chat.rooms.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-list"></i> 모든 채팅방
                        </a>
                        <a href="{{ route('home.chat.rooms.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> 새 채팅방 만들기
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- 상태 요약 --}}
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="p-2 bg-primary bg-opacity-10 rounded-circle me-3">
                                    <i class="fas fa-comments text-primary" style="font-size: 1.5rem;"></i>
                                </div>
                                <div>
                                    <p class="text-muted mb-0 small">참여 중인 채팅방</p>
                                    <h4 class="mb-0 fw-bold">{{ $participatingRooms->total() }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="p-2 bg-danger bg-opacity-10 rounded-circle me-3">
                                    <i class="fas fa-envelope text-danger" style="font-size: 1.5rem;"></i>
                                </div>
                                <div>
                                    <p class="text-muted mb-0 small">읽지 않은 메시지</p>
                                    <h4 class="mb-0 fw-bold">{{ array_sum($unreadCounts) }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="p-2 bg-success bg-opacity-10 rounded-circle me-3">
                                    <i class="fas fa-users text-success" style="font-size: 1.5rem;"></i>
                                </div>
                                <div>
                                    <p class="text-muted mb-0 small">온라인 참가자</p>
                                    <h4 class="mb-0 fw-bold">{{ $participatingRooms->sum(function($room) { return $room->activeParticipants->count(); }) }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 최근 채팅방 목록 --}}
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title mb-0">최근 채팅방</h5>
                        <a href="{{ route('home.chat.rooms.index') }}" class="text-primary text-decoration-none">
                            모든 채팅방 보기 →
                        </a>
                    </div>

                    @if($participatingRooms->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($participatingRooms as $room)
                                <div class="list-group-item border rounded mb-2 p-3">
                                    <div class="d-flex align-items-center">
                                        {{-- 채팅방 아바타 --}}
                                        <div class="flex-shrink-0 me-3">
                                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center"
                                                 style="width: 48px; height: 48px;">
                                                <span class="text-white fw-semibold fs-5">
                                                    {{ substr($room->title, 0, 1) }}
                                                </span>
                                            </div>
                                        </div>

                                        {{-- 채팅방 정보 --}}
                                        <div class="flex-fill">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1 fw-semibold">{{ $room->title }}</h6>
                                                    <p class="mb-0 text-muted small">
                                                        @if($room->latestMessage)
                                                            {{ Str::limit($room->latestMessage->content, 50) }}
                                                        @else
                                                            채팅을 시작해보세요!
                                                        @endif
                                                    </p>
                                                </div>
                                                <div class="text-end">
                                                    @if($room->latestMessage)
                                                        <p class="mb-1 text-muted" style="font-size: 0.75rem;">
                                                            {{ $room->latestMessage->created_at->diffForHumans() }}
                                                        </p>
                                                    @endif
                                                    @if(isset($unreadCounts[$room->id]) && $unreadCounts[$room->id] > 0)
                                                        <span class="badge bg-danger rounded-pill">
                                                            {{ $unreadCounts[$room->id] }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        {{-- 입장 버튼 --}}
                                        <div class="flex-shrink-0 ms-3">
                                            <a href="{{ route('home.chat.room', $room->id) }}"
                                               class="btn btn-primary btn-sm">
                                                입장
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- 페이지네이션 --}}
                        <div class="mt-4">
                            {{ $participatingRooms->links() }}
                        </div>
                    @else
                        {{-- 빈 상태 --}}
                        <div class="text-center py-5">
                            <i class="fas fa-comments text-muted mb-3" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mb-2">채팅방이 없습니다</h5>
                            <p class="text-muted mb-4">새로운 채팅방을 만들거나 기존 채팅방에 참여해보세요.</p>
                            <a href="{{ route('home.chat.rooms.create') }}"
                               class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>새 채팅방 만들기
                            </a>
                        </div>
                    @endif
                </div>
            </div>


    </div>

    {{-- 실시간 알림을 위한 스크립트 --}}
    <script>
        // 페이지가 로드될 때 실시간 알림 설정
        document.addEventListener('DOMContentLoaded', function() {
            // WebSocket 연결 또는 polling 설정
            // TODO: 실시간 알림 구현
        });
    </script>
@endsection
