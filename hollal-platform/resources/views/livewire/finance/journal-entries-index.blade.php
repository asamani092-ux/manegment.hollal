<x-ds-page>
    <x-ds-page-header
        title="القيود اليومية"
        :show-button="true"
        button-label="قيد يدوي"
        button-icon="fa-plus"
        wire:click="openCreate"
    />

    <p class="ds-text-muted ds-mb-3">القيود الآلية تُنشأ من الحركات المعتمدة؛ القيد اليدوي للمحاسب يجب أن يكون متوازنًا.</p>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>الرقم</th>
                <th>التاريخ</th>
                <th>الوصف</th>
                <th>مدين</th>
                <th>دائن</th>
                <th>النوع</th>
            </tr>
        </x-slot:head>
        @forelse ($entries as $entry)
            <tr wire:key="je-{{ $entry->id }}">
                <td class="ds-ltr-num">{{ $entry->number }}</td>
                <td dir="ltr">{{ $entry->entry_date?->format('Y-m-d') }}</td>
                <td>{{ $entry->description }}</td>
                <td class="ds-ltr-num">{{ number_format($entry->debitTotal(), 2) }}</td>
                <td class="ds-ltr-num">{{ number_format($entry->creditTotal(), 2) }}</td>
                <td>{{ $entry->is_automatic ? 'آلي' : 'يدوي' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="ds-text-muted ds-table-empty">لا توجد قيود</td></tr>
        @endforelse
    </x-ds-table>

    {{ $entries->links() }}

    @if ($showModal)
        <div class="ds-modal-overlay" wire:click.self="$set('showModal', false)">
            <div class="ds-modal ds-modal-lg" role="dialog">
                <div class="ds-modal-header">
                    <h3>قيد يدوي</h3>
                    <button type="button" class="ds-modal-close" wire:click="$set('showModal', false)">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <div class="ds-grid-2">
                        <x-ds-form-group label="الوصف" :error="$errors->first('description')">
                            <input type="text" class="ds-input" wire:model="description">
                        </x-ds-form-group>
                        <x-ds-form-group label="التاريخ" :error="$errors->first('entry_date')">
                            <input type="date" class="ds-input" wire:model="entry_date">
                        </x-ds-form-group>
                    </div>
                    @error('lines') <p class="ds-badge ds-badge-danger">{{ $message }}</p> @enderror
                    <table class="ds-table">
                        <thead>
                            <tr><th>الحساب</th><th>مدين</th><th>دائن</th><th>وصف</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $i => $line)
                                <tr wire:key="jl-{{ $i }}">
                                    <td>
                                        <select class="ds-input" wire:model="lines.{{ $i }}.account_id">
                                            <option value="">—</option>
                                            @foreach ($accounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name_ar }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" step="0.01" class="ds-input ds-ltr-num" wire:model="lines.{{ $i }}.debit"></td>
                                    <td><input type="number" step="0.01" class="ds-input ds-ltr-num" wire:model="lines.{{ $i }}.credit"></td>
                                    <td><input type="text" class="ds-input" wire:model="lines.{{ $i }}.description"></td>
                                    <td><button type="button" class="ds-btn ds-btn-sm" wire:click="removeLine({{ $i }})">حذف</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="addLine">سطر إضافي</button>
                </div>
                <div class="ds-modal-footer">
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="save">ترحيل</button>
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="$set('showModal', false)">إلغاء</button>
                </div>
            </div>
        </div>
    @endif
</x-ds-page>
