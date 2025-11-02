<div class="d-flex flex-column gap-1">
    <span class="navbar-header">채팅</span>
    <ul class="list-unstyled mb-0">

        <!-- 채팅 대시보드 -->
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('home.chat.index') ? 'active' : '' }}"
               href="{{ route('home.chat.index') }}">
                <i class="fas fa-tachometer-alt nav-icon"></i>
                대시보드
            </a>
        </li>

        <!-- 채팅방 생성 -->
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('home.chat.rooms.create') ? 'active' : '' }}"
               href="{{ route('home.chat.rooms.create') }}">
                <i class="fas fa-plus nav-icon"></i>
                채팅방 생성
            </a>
        </li>

        <!-- 초대링크 관리 -->
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('home.chat.invite.*') ? 'active' : '' }}"
               href="{{ route('home.chat.invite.index') }}">
                <i class="fas fa-link nav-icon"></i>
                초대링크
            </a>
        </li>

    </ul>
</div>
