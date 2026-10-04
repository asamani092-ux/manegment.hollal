<div>
    <x-ds-page>
        <x-ds-page-header title="المخالفات" screen="hr.violations" />
        <div class="ds-tabs">
            <button type="button" class="ds-tab" wire:click="$set('tab','suggested')">مقترحة</button>
            <button type="button" class="ds-tab" wire:click="$set('tab','statement')">بانتظار الإفادة</button>
            <button type="button" class="ds-tab" wire:click="$set('tab','decision')">بانتظار القرار</button>
            <button type="button" class="ds-tab" wire:click="$set('tab','all')">الكل</button>
            @if ($canManage && $tab === 'suggested')
                <button type="button" class="ds-btn ds-btn-sm" wire:click="confirmAllSuggested">تأكيد المقترح</button>
            @endif
        </div>
        @foreach ($rows as $row)
            <p wire:key="v-{{ $row->id }}">{{ $row->status }} — {{ $row->facts }}
                @if ($canManage && $row->status === 'suggested')
                    <button type="button" class="ds-btn ds-btn-sm" wire:click="exclude({{ $row->id }}, 'استبعاد')">استبعاد</button>
                    <button type="button" class="ds-btn ds-btn-sm" wire:click="confirmOne({{ $row->id }})">تأكيد</button>
                @endif
                @if ($canDecide && $row->status === 'pending_decision')
                    <button type="button" class="ds-btn ds-btn-sm" wire:click="decide({{ $row->id }}, 'apply', 'تطبيق الجزاء')">تطبيق</button>
                    <button type="button" class="ds-btn ds-btn-sm" wire:click="decide({{ $row->id }}, 'cancel', 'إلغاء')">إلغاء</button>
                    <a class="ds-link" href="{{ route('hr.violations.statement', $row) }}">ملف الإفادة</a>
                @endif
            </p>
        @endforeach
    </x-ds-page>
</div>
