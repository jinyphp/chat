<div class="room-info">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <div class="d-flex align-items-center mb-2">
                <a href="{{ route('home.chat.index') }}" class="back-button me-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h3 class="mb-0">{{ $room->title }}</h3>
                <span id="connectionStatus" class="connection-status ms-3 small">
                    <i class="fas fa-circle text-secondary"></i> 연결 중...
                </span>
                {{-- 폴링 간격 설정 --}}
                <div class="ms-3 d-flex align-items-center">
                    <label for="pollingInterval" class="form-label mb-0 me-2 small text-white-50">
                        <i class="fas fa-clock me-1"></i>폴링:
                    </label>
                    <select id="pollingInterval" class="form-select form-select-sm" style="width: 90px;">
                        <option value="0">중단</option>
                        <option value="1">1초</option>
                        <option value="2">2초</option>
                        <option value="3" selected>3초</option>
                        <option value="5">5초</option>
                        <option value="10">10초</option>
                        <option value="30">30초</option>
                    </select>
                </div>
            </div>
            @if ($room->description)
                <p class="mb-2 opacity-75">{{ $room->description }}</p>
            @endif
            <div class="room-meta">
                <span>
                    <i class="fas fa-users me-1"></i>
                    참여자 {{ $participants->count() }}명
                </span>
                @if ($room->is_public)
                    <span>
                        <i class="fas fa-globe me-1"></i>
                        공개방
                    </span>
                @else
                    <span>
                        <i class="fas fa-lock me-1"></i>
                        비공개방
                    </span>
                @endif
                @if ($room->is_owner)
                    <span>
                        <i class="fas fa-crown me-1"></i>
                        방장
                    </span>
                @endif
            </div>
        </div>
        @if ($room->image)
            <img src="{{ asset('storage/' . $room->image) }}" alt="{{ $room->title }}" class="rounded"
                style="width: 60px; height: 60px; object-fit: cover;">
        @endif
    </div>
</div>
