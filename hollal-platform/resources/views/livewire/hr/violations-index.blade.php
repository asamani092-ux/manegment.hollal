<div>
    <x-ds-page>
        <x-ds-page-header title="المخالفات" screen="hr.violations" />
        <div class="ds-tabs">
            <button type="button" class="ds-tab {{ $tab === 'catalog' ? 'ds-tab-active' : '' }}" wire:click="$set('tab','catalog')">جدول المخالفات</button>
            <button type="button" class="ds-tab" wire:click="$set('tab','suggested')">مقترحة</button>
            <button type="button" class="ds-tab" wire:click="$set('tab','statement')">بانتظار الإفادة</button>
            <button type="button" class="ds-tab" wire:click="$set('tab','decision')">بانتظار القرار</button>
            <button type="button" class="ds-tab" wire:click="$set('tab','window')">نافذة</button>
            <button type="button" class="ds-tab" wire:click="$set('tab','all')">الكل</button>
            @if ($canManage && $tab === 'suggested')
                <button type="button" class="ds-btn ds-btn-sm" wire:click="confirmAllSuggested">تأكيد المقترح</button>
            @endif
        </div>
        @if ($tab === 'catalog')
            <livewire:hr.violation-catalog />
        @else
        @foreach ($rows as $row)
            <p wire:key="v-{{ $row->id }}">{{ \App\Support\ArabicStatus::label($row->status) }} — {{ $row->facts }}
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
        @if ($canManage)
            <h2>تسجيل مخالفة</h2>
            <select class="ds-input" wire:model="manualEmployeeId">
                <option value="">الموظف</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                @endforeach
            </select>
            <select class="ds-input" wire:model="manualItemId">
                <option value="">المخالفة من القائمة</option>
                @foreach ($violationItems as $item)
                    <option value="{{ $item->id }}">{{ $item->name_ar }}</option>
                @endforeach
            </select>
            <input class="ds-input" wire:model="manualOccurred" placeholder="2026-10-06" inputmode="numeric">
            <textarea class="ds-input" wire:model="manualFacts" placeholder="الوقائع"></textarea>
            <button type="button" class="ds-btn" wire:click="recordManualFromList">تسجيل</button>
        @endif
        @endif
    </x-ds-page>
</div>
