{{-- 채팅방 차단 페이지 --}}
@extends('jiny-site::layouts.home')

@section('content')
<div class="container-fluid py-5">

    {{-- 헤더 --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-2">
                        <i class="fas fa-ban text-danger"></i>
                        접근 차단
                    </h2>
                    <p class="text-muted mb-0">이 채팅방에서 차단되어 참여할 수 없습니다</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('home.chat.rooms.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> 채팅방 목록으로
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-danger">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <div class="bg-danger rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px;">
                        <i class="fas fa-ban text-white fs-2"></i>
                    </div>
                    <h2 class="h4 fw-semibold text-danger mb-2">접근이 차단되었습니다</h2>
                    <p class="text-muted">{{ $room->title }} 채팅방에서 차단되어 참여할 수 없습니다.</p>
                </div>

                {{-- 차단 정보 --}}
                <div class="alert alert-danger border-0">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-exclamation-circle text-danger me-3 mt-1"></i>
                        <div class="flex-fill">
                            <h6 class="fw-semibold mb-2">차단 사유</h6>
                            <p class="mb-2">
                                {{ $participant->ban_reason ?? '관리자에 의해 차단되었습니다.' }}
                            </p>

                            @if($participant->banned_until)
                                <div class="small text-muted mt-2">
                                    <i class="fas fa-clock me-1"></i>
                                    차단 해제 예정: {{ $participant->banned_until->format('Y년 m월 d일 H:i') }}
                                </div>
                            @else
                                <div class="small text-muted mt-2">
                                    <i class="fas fa-infinity me-1"></i>
                                    영구 차단
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- 차단 세부 정보 --}}
                <div class="border-top pt-4">
                    <h3 class="h6 fw-semibold mb-3">차단 정보</h3>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <span class="small text-muted">차단 일시:</span>
                            <span class="small fw-semibold ms-2 d-block">{{ $participant->banned_at->format('Y-m-d H:i') }}</span>
                        </div>
                        @if($participant->banned_by_name)
                            <div class="col-sm-6">
                                <span class="small text-muted">차단 관리자:</span>
                                <span class="small fw-semibold ms-2 d-block">{{ $participant->banned_by_name }}</span>
                            </div>
                        @endif
                        <div class="col-sm-6">
                            <span class="small text-muted">차단 유형:</span>
                            <span class="small fw-semibold ms-2 d-block">
                                @if($participant->banned_until)
                                    임시 차단
                                @else
                                    영구 차단
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                {{-- 안내 메시지 --}}
                <div class="bg-light rounded p-3 mt-4">
                    <h6 class="fw-semibold mb-2">
                        <i class="fas fa-info-circle text-primary me-2"></i>차단 해제 안내
                    </h6>
                    <ul class="small text-muted mb-0 ps-3">
                        <li>차단이 부당하다고 생각되시면 관리자에게 문의하세요.</li>
                        <li>임시 차단의 경우 지정된 시간 후 자동으로 해제됩니다.</li>
                        <li>영구 차단의 경우 관리자의 승인이 필요합니다.</li>
                        <li>규정을 준수하여 건전한 채팅 문화를 만들어주세요.</li>
                    </ul>
                </div>

                {{-- 하단 버튼 --}}
                <div class="d-flex justify-content-center gap-2 mt-4">
                    <a href="{{ route('home.chat.rooms.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>목록으로 돌아가기
                    </a>
                    <button type="button" class="btn btn-outline-primary" onclick="contactAdmin()">
                        <i class="fas fa-envelope me-2"></i>관리자 문의
                    </button>
                </div>
            </div>
        </div>

</div>

<script>
function contactAdmin() {
    alert('관리자 문의 기능은 준비 중입니다.');
    // TODO: 관리자 문의 기능 구현
}
</script>
@endsection
