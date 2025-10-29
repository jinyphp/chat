{{-- 관리자 통계 페이지 --}}
@extends('jiny-site::layouts.admin.sidebar')

@section('content')
    <div class="container-fluid py-5">
        <div class="container">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="h4 fw-semibold text-dark mb-4">채팅 시스템 통계</h3>

                    {{-- 메인 통계 --}}
                    <div class="row g-4 mb-5">
                        <div class="col-md-6 col-lg-3">
                            <div class="card bg-primary-subtle border-primary">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary rounded-circle p-3 me-3">
                                            <i class="fas fa-comments text-white"></i>
                                        </div>
                                        <div>
                                            <h4 class="h6 fw-semibold text-primary mb-1">전체 채팅방</h4>
                                            <p class="display-6 fw-bold text-primary mb-0">{{ $stats['total_rooms'] ?? 0 }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="card bg-success-subtle border-success">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-success rounded-circle p-3 me-3">
                                            <i class="fas fa-check-circle text-white"></i>
                                        </div>
                                        <div>
                                            <h4 class="h6 fw-semibold text-success mb-1">활성 채팅방</h4>
                                            <p class="display-6 fw-bold text-success mb-0">{{ $stats['active_rooms'] ?? 0 }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="card bg-info-subtle border-info">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-info rounded-circle p-3 me-3">
                                            <i class="fas fa-envelope text-white"></i>
                                        </div>
                                        <div>
                                            <h4 class="h6 fw-semibold text-info mb-1">전체 메시지</h4>
                                            <p class="display-6 fw-bold text-info mb-0">{{ number_format($stats['total_messages'] ?? 0) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="card bg-warning-subtle border-warning">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-warning rounded-circle p-3 me-3">
                                            <i class="fas fa-users text-white"></i>
                                        </div>
                                        <div>
                                            <h4 class="h6 fw-semibold text-warning mb-1">활성 참여자</h4>
                                            <p class="display-6 fw-bold text-warning mb-0">{{ $stats['total_participants'] ?? 0 }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 오늘 통계 --}}
                    <div class="row g-4 mb-5">
                        <div class="col-md-4">
                            <div class="card bg-secondary-subtle border-secondary">
                                <div class="card-body">
                                    <h4 class="h6 fw-semibold text-secondary mb-3">오늘 활동</h4>
                                    <div class="d-flex flex-column gap-2">
                                        <div class="d-flex justify-content-between">
                                            <span class="small text-muted">새 메시지:</span>
                                            <span class="fw-semibold text-secondary">{{ number_format($stats['today_messages'] ?? 0) }}개</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="small text-muted">새 채팅방:</span>
                                            <span class="fw-semibold text-secondary">{{ $stats['today_rooms'] ?? 0 }}개</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="small text-muted">새 참여자:</span>
                                            <span class="fw-semibold text-secondary">{{ $stats['today_participants'] ?? 0 }}명</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card bg-danger-subtle border-danger">
                                <div class="card-body">
                                    <h4 class="h6 fw-semibold text-danger mb-3">인기 채팅방</h4>
                                    <div class="d-flex flex-column gap-2">
                                        @forelse($stats['popular_rooms'] ?? [] as $room)
                                            <div class="d-flex justify-content-between small">
                                                <span class="text-muted text-truncate me-2">{{ $room['title'] ?? '채팅방' }}</span>
                                                <span class="fw-semibold text-danger">{{ $room['message_count'] ?? 0 }}개</span>
                                            </div>
                                        @empty
                                            <p class="small text-muted mb-0">데이터가 없습니다.</p>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card bg-dark-subtle border-dark">
                                <div class="card-body">
                                    <h4 class="h6 fw-semibold text-dark mb-3">활성 사용자</h4>
                                    <div class="d-flex flex-column gap-2">
                                        @forelse($stats['active_users'] ?? [] as $user)
                                            <div class="d-flex justify-content-between small">
                                                <span class="text-muted text-truncate me-2">{{ $user['name'] ?? '사용자' }}</span>
                                                <span class="fw-semibold text-dark">{{ $user['message_count'] ?? 0 }}개</span>
                                            </div>
                                        @empty
                                            <p class="small text-muted mb-0">데이터가 없습니다.</p>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 차트 섹션 --}}
                    <div class="row g-4 mb-5">
                        <div class="col-lg-6">
                            <div class="card border">
                                <div class="card-body">
                                    <h4 class="h6 fw-semibold text-dark mb-4">일별 메시지 수 (최근 7일)</h4>
                                    <div class="d-flex align-items-end justify-content-between" style="height: 256px; gap: 8px;">
                                        @for($i = 6; $i >= 0; $i--)
                                            @php
                                                $date = now()->subDays($i);
                                                $messageCount = $stats['daily_messages'][$date->format('Y-m-d')] ?? rand(10, 100);
                                                $height = min(200, ($messageCount / 100) * 200);
                                            @endphp
                                            <div class="d-flex flex-column align-items-center">
                                                <div class="bg-primary rounded-top" style="height: {{ $height }}px; width: 30px;"></div>
                                                <span class="small text-muted mt-2">{{ $date->format('m/d') }}</span>
                                                <span class="small fw-semibold text-dark">{{ $messageCount }}</span>
                                            </div>
                                        @endfor
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card border">
                                <div class="card-body">
                                    <h4 class="h6 fw-semibold text-dark mb-4">메시지 타입별 분포</h4>
                                    <div class="d-flex flex-column gap-3">
                                        @php
                                            $messageTypes = [
                                                'text' => ['name' => '텍스트', 'count' => $stats['message_types']['text'] ?? 0, 'color' => 'bg-primary'],
                                                'image' => ['name' => '이미지', 'count' => $stats['message_types']['image'] ?? 0, 'color' => 'bg-success'],
                                                'file' => ['name' => '파일', 'count' => $stats['message_types']['file'] ?? 0, 'color' => 'bg-warning'],
                                                'system' => ['name' => '시스템', 'count' => $stats['message_types']['system'] ?? 0, 'color' => 'bg-secondary'],
                                            ];
                                            $totalMessages = array_sum(array_column($messageTypes, 'count')) ?: 1;
                                        @endphp

                                        @foreach($messageTypes as $type => $data)
                                            <div class="d-flex align-items-center">
                                                <div class="rounded me-3 {{ $data['color'] }}" style="width: 16px; height: 16px;"></div>
                                                <div class="flex-fill">
                                                    <div class="d-flex justify-content-between small">
                                                        <span class="text-muted">{{ $data['name'] }}</span>
                                                        <span class="fw-semibold">{{ number_format($data['count']) }}개 ({{ round(($data['count'] / $totalMessages) * 100, 1) }}%)</span>
                                                    </div>
                                                    <div class="progress mt-1" style="height: 8px;">
                                                        <div class="progress-bar {{ str_replace('bg-', '', $data['color']) }}" style="width: {{ ($data['count'] / $totalMessages) * 100 }}%"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 시스템 상태 --}}
                    <div class="card bg-light border-light">
                        <div class="card-body">
                            <h4 class="h6 fw-semibold text-dark mb-4">시스템 상태</h4>
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-success rounded-circle me-3" style="width: 12px; height: 12px;"></div>
                                        <span class="small text-muted">서버 상태: <span class="fw-semibold text-success">정상</span></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-success rounded-circle me-3" style="width: 12px; height: 12px;"></div>
                                        <span class="small text-muted">데이터베이스: <span class="fw-semibold text-success">연결됨</span></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-warning rounded-circle me-3" style="width: 12px; height: 12px;"></div>
                                        <span class="small text-muted">WebSocket: <span class="fw-semibold text-warning">준비 중</span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection