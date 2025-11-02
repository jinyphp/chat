{{-- 채팅방 접근 권한 페이지 --}}
@extends('jiny-site::layouts.home')

@section('content')
<div class="container-fluid py-5">

    {{-- 헤더 --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-2">
                        <i class="fas fa-lock text-warning"></i>
                        채팅방 접근 권한
                    </h2>
                    <p class="text-muted mb-0">채팅방에 참여하기 위해 추가 정보가 필요합니다</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('home.chat.rooms.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> 채팅방 목록으로
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px;">
                        <i class="fas fa-lock text-white fs-2"></i>
                    </div>
                    <h2 class="h4 fw-semibold text-dark mb-2">{{ $room->title }}</h2>
                    <p class="text-muted">이 채팅방에 참여하려면 추가 정보가 필요합니다.</p>
                </div>

                @if($needsPassword)
                    {{-- 비밀번호 입력 --}}
                    <div class="mb-4">
                        <form method="POST" action="{{ route('home.chat.rooms.join', $room->id) }}">
                            @csrf
                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold">
                                    <i class="fas fa-key me-2"></i>채팅방 비밀번호
                                </label>
                                <input type="password"
                                       name="password"
                                       id="password"
                                       class="form-control"
                                       placeholder="비밀번호를 입력하세요"
                                       required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>채팅방 참여
                            </button>
                        </form>
                    </div>
                @endif

                @if($needsInvite)
                    {{-- 초대 필요 --}}
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        이 채팅방은 초대를 통해서만 참여할 수 있습니다.
                    </div>
                @endif

                @if(!$canJoin)
                    {{-- 참여 불가 --}}
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        현재 이 채팅방에 참여할 수 없습니다.
                    </div>
                @endif

                {{-- 채팅방 정보 --}}
                <div class="border-top pt-4">
                    <h3 class="h6 fw-semibold mb-3">채팅방 정보</h3>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <span class="small text-muted">타입:</span>
                            <span class="small fw-semibold ms-2">
                                @if($room->type === 'public')
                                    <i class="fas fa-globe text-success me-1"></i>공개방
                                @elseif($room->type === 'private')
                                    <i class="fas fa-lock text-warning me-1"></i>비공개방
                                @else
                                    <i class="fas fa-users text-info me-1"></i>그룹방
                                @endif
                            </span>
                        </div>
                        <div class="col-sm-6">
                            <span class="small text-muted">참여자:</span>
                            <span class="small fw-semibold ms-2">{{ $room->participant_count }}명</span>
                        </div>
                        @if($room->description)
                            <div class="col-12">
                                <span class="small text-muted">설명:</span>
                                <p class="small mb-0 mt-1">{{ $room->description }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- 하단 버튼 --}}
                <div class="d-flex justify-content-center gap-2 mt-4">
                    <a href="{{ route('home.chat.rooms.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>목록으로 돌아가기
                    </a>
                </div>
            </div>
        </div>

</div>
@endsection
