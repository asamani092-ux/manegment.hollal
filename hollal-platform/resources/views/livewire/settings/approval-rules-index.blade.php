<x-ds-page>
    <x-ds-page-header
        title="سلسلة الاعتماد"
        :show-button="true"
        button-label="إضافة قاعدة"
        button-icon="fa-plus"
        wire:click="openCreate"
    />

    <p class="ds-text-muted ds-mb-3">قواعد ديناميكية حسب نوع العملية ونطاق المبلغ. تتجاوز النمط الثابت (كامل/مختصر) عند وجود قواعد نشطة.</p>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>النوع</th>
                <th>من</th>
                <th>إلى</th>
                <th>الخطوات</th>
                <th>نشط</th>
                <th>إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($rules as $rule)
            <tr wire:key="rule-{{ $rule->id }}">
                <td>{{ $typeLabels[$rule->transaction_type] ?? $rule->transaction_type }}</td>
                <td class="ds-ltr-num">{{ number_format((float) $rule->min_amount, 2) }}</td>
                <td class="ds-ltr-num">{{ $rule->max_amount === null ? 'بلا سقف' : number_format((float) $rule->max_amount, 2) }}</td>
                <td>
                    @foreach ($rule->approval_steps ?? [] as $step)
                        @php $role = is_array($step) ? ($step['role'] ?? '') : $step; @endphp
                        <span class="ds-badge">{{ $roleLabels[$role] ?? $role }}</span>
                    @endforeach
                </td>
                <td>{{ $rule->is_active ? 'نعم' : 'لا' }}</td>
                <td>
                    <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openEdit({{ $rule->id }})">تعديل</button>
                    <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="toggleActive({{ $rule->id }})">تبديل</button>
                    <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="deleteRule({{ $rule->id }})" wire:confirm="حذف القاعدة؟">حذف</button>
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-ds-empty-state message="لا قواعد بعد — أضف قاعدة أو شغّل البذرة" icon="fa-sitemap" /></td></tr>
        @endforelse
    </x-ds-table>

    <x-ds-modal :show="$showModal" :title="$editingId ? 'تعديل قاعدة' : 'إضافة قاعدة'" close-action="$set('showModal', false)">
        <x-ds-form-group label="نوع العملية" :error="$errors->first('transaction_type')">
            <select class="ds-input" wire:model="transaction_type">
                @foreach ($typeLabels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </x-ds-form-group>
        <x-ds-form-group label="الحد الأدنى" :error="$errors->first('min_amount')">
            <input type="number" step="0.01" class="ds-input" wire:model="min_amount">
        </x-ds-form-group>
        <x-ds-form-group label="الحد الأقصى (فارغ = بلا سقف)" :error="$errors->first('max_amount')">
            <input type="number" step="0.01" class="ds-input" wire:model="max_amount" placeholder="بلا سقف">
        </x-ds-form-group>
        <x-ds-form-group label="خطوات الاعتماد" :error="$errors->first('selectedRoles')">
            @foreach ($availableRoles as $role)
                <label class="ds-checkbox-label">
                    <input type="checkbox" value="{{ $role }}" wire:model="selectedRoles">
                    <span>{{ $roleLabels[$role] ?? $role }}</span>
                </label>
            @endforeach
        </x-ds-form-group>
        <x-ds-form-group label="نشط">
            <label class="ds-checkbox-label">
                <input type="checkbox" wire:model="is_active">
                <span>القاعدة فعّالة</span>
            </label>
        </x-ds-form-group>
        <x-slot:footer>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="save">حفظ</button>
            <button type="button" class="ds-btn ds-btn-outline" wire:click="$set('showModal', false)">إلغاء</button>
        </x-slot:footer>
    </x-ds-modal>
</x-ds-page>
