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

    </ul>
</div>
