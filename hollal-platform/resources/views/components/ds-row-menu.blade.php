@props([
    'align' => 'start',
])

<div
    class="ds-row-menu"
    x-data="{ open: false }"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
>
    <button
        type="button"
        class="ds-btn ds-btn-ghost ds-btn-sm ds-row-menu-trigger"
        @click.stop="open = !open"
        aria-haspopup="true"
        :aria-expanded="open"
        title="إجراءات"
    >
        <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
    </button>
    <div
        class="ds-dropdown-menu ds-row-menu-panel {{ $align === 'end' ? 'is-align-end' : '' }}"
        x-show="open"
        x-cloak
        @click="open = false"
        role="menu"
    >
        {{ $slot }}
    </div>
</div>
