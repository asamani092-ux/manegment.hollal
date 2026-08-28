<div wire:key="header-att-punch">
    @if ($enabled)
        <div class="ds-navbar-attendance ds-toolbar-actions">
            <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" wire:click="checkIn" title="تسجيل حضور">
                حضور
            </button>
            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="checkOut" title="تسجيل انصراف">
                انصراف
            </button>
        </div>
    @endif
</div>
