@php
    $open = $expanded[$account->id] ?? ($depth < 1);
    $hasChildren = $account->children->isNotEmpty();
@endphp
<article class="ds-coa-node" style="padding-inline-start: {{ $depth * 1.25 }}rem" wire:key="coa-{{ $account->id }}">
    <div class="ds-coa-row">
        @if ($hasChildren)
            <button type="button" class="ds-btn ds-btn-sm ds-btn-outline" wire:click="toggle({{ $account->id }})" aria-expanded="{{ $open ? 'true' : 'false' }}">
                <i class="fas fa-{{ $open ? 'chevron-down' : 'chevron-left' }}" aria-hidden="true"></i>
            </button>
        @else
            <span class="ds-coa-spacer"></span>
        @endif
        <span class="ds-ltr-num">{{ $account->code }}</span>
        <strong>{{ $account->name_ar }}</strong>
        <span class="ds-badge">{{ str_replace('_', ' ', $account->type) }}</span>
        <span class="ds-text-muted">{{ $account->nature }}</span>
        @unless ($account->is_active)
            <span class="ds-badge ds-badge-warning">معطّل</span>
        @endunless
        <div class="ds-btn-group">
            <button type="button" class="ds-btn ds-btn-sm" wire:click="openCreate({{ $account->id }})">فرعي</button>
            <button type="button" class="ds-btn ds-btn-sm ds-btn-outline" wire:click="openEdit({{ $account->id }})">تعديل</button>
            <button type="button" class="ds-btn ds-btn-sm ds-btn-outline" wire:click="deleteAccount({{ $account->id }})" wire:confirm="حذف هذا الحساب؟">حذف</button>
        </div>
    </div>
    @if ($hasChildren && $open)
        @foreach ($account->children as $child)
            @include('livewire.finance.partials.coa-node', ['account' => $child, 'depth' => $depth + 1])
        @endforeach
    @endif
</article>
