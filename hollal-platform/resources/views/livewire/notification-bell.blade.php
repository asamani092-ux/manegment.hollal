<div class="ds-notifications-dropdown" wire:poll.30s>
    <button
        type="button"
        class="ds-notifications-trigger"
        wire:click="toggleDropdown"
        aria-haspopup="true"
        aria-expanded="{{ $open ? 'true' : 'false' }}"
        aria-label="الإشعارات"
    >
        <svg class="ds-icon ds-icon-md" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
        </svg>
        @if ($unreadCount > 0)
            <span class="ds-notifications-badge">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
        @endif
    </button>

    @if ($open)
        <div class="ds-notifications-menu" role="menu">
            <div class="ds-notifications-header">
                <span class="ds-notifications-title">الإشعارات</span>
                @if ($unreadCount > 0)
                    <button type="button" class="ds-notifications-mark-all" wire:click="markAllAsRead">
                        تحديد الكل كمقروء
                    </button>
                @endif
            </div>

            @if ($notifications->isEmpty())
                <div class="ds-notifications-empty">لا توجد إشعارات</div>
            @else
                <ul class="ds-notifications-list">
                    @foreach ($notifications as $notification)
                        <li class="ds-notifications-item {{ $notification->read_at === null ? 'is-unread' : '' }}">
                            <a
                                href="{{ $notification->data['url'] ?? '#' }}"
                                class="ds-notifications-link"
                                wire:click="markAsRead('{{ $notification->id }}')"
                            >
                                <span class="ds-notifications-message">{{ $notification->data['message'] ?? '' }}</span>
                                <span class="ds-notifications-time">{{ $notification->created_at->diffForHumans() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
