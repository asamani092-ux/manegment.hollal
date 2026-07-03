<nav class="ds-navbar">
    <div class="ds-navbar-start">
        <button type="button" class="ds-sidebar-toggle" id="ds-sidebar-toggle" aria-label="فتح القائمة" aria-expanded="false">
            <svg class="ds-icon ds-icon-md" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
            </svg>
        </button>
        <a href="{{ route('dashboard') }}" class="ds-navbar-brand">
            <img src="{{ asset('assets/logos/logo.svg') }}" alt="منصة حلل" class="ds-logo-img">
        </a>
        <div class="ds-navbar-title">{{ $title ?? 'الرئيسية' }}</div>
    </div>

    <div class="ds-navbar-end">
        <div class="ds-user-dropdown" id="ds-user-dropdown">
            <button type="button" class="ds-user-trigger" id="ds-user-trigger" aria-haspopup="true" aria-expanded="false">
                <span class="ds-user-avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                <span class="ds-user-meta">
                    <span class="ds-user-name">{{ auth()->user()->name }}</span>
                    <span class="ds-user-position">
                        @if (auth()->user()->roles->first())
                            <x-ds-role-label :name="auth()->user()->roles->first()->name" />
                        @else
                            مستخدم
                        @endif
                    </span>
                </span>
                <svg class="ds-icon ds-icon-sm ds-user-chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                </svg>
            </button>
            <div class="ds-dropdown-menu" id="ds-user-menu" role="menu">
                <div class="ds-dropdown-header">
                    <span class="ds-user-name">{{ auth()->user()->name }}</span>
                    <span class="ds-user-position">{{ auth()->user()->email }}</span>
                </div>
                <div class="ds-dropdown-divider"></div>
                <form method="POST" action="{{ route('logout') }}" class="ds-dropdown-form">
                    @csrf
                    <button type="submit" class="ds-dropdown-item ds-dropdown-item-danger" role="menuitem">
                        <svg class="ds-icon ds-icon-sm" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/>
                        </svg>
                        تسجيل الخروج
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
