<x-ds-page>
    <x-ds-page-header title="استبانة التشخيص" subtitle="الأسئلة الظاهرة في بوابة الجهة. الإخفاء لا يحذف الإجابات السابقة." />

    <section class="ds-section">
        <form wire:submit="save">
            <x-ds-form-group label="نص السؤال" :error="$errors->first('label')">
                <input type="text" class="ds-input" wire:model="label">
            </x-ds-form-group>
            <x-ds-form-group label="النوع" :error="$errors->first('type')">
                <select class="ds-input" wire:model="type">
                    <option value="text">نص</option>
                    <option value="number">رقم</option>
                    <option value="textarea">نص طويل</option>
                </select>
            </x-ds-form-group>
            <x-ds-form-group label="الترتيب">
                <input type="number" min="0" class="ds-input ds-ltr-num" wire:model="sort_order">
            </x-ds-form-group>
            <label class="ds-checkbox-label">
                <input type="checkbox" wire:model="required">
                <span>إلزامي</span>
            </label>
            <div class="ds-page-toolbar">
                <button type="submit" class="ds-btn ds-btn-primary">{{ $editingId ? 'تحديث السؤال' : 'إضافة سؤال' }}</button>
                @if ($editingId)
                    <button type="button" class="ds-btn" wire:click="resetForm">إلغاء</button>
                @endif
            </div>
        </form>
    </section>

    <section class="ds-section">
        <x-ds-table>
            <x-slot:head>
                <tr><th>الترتيب</th><th>السؤال</th><th>النوع</th><th>إلزامي</th><th>الحالة</th><th></th></tr>
            </x-slot:head>
            @foreach ($questions as $question)
                <tr wire:key="dq-{{ $question->id }}">
                    <td class="ds-ltr-num">{{ $question->sort_order }}</td>
                    <td>{{ $question->label }}</td>
                    <td>{{ $question->type }}</td>
                    <td>{{ $question->required ? 'نعم' : 'لا' }}</td>
                    <td>{{ $question->is_active ? 'ظاهر' : 'مخفي' }}</td>
                    <td>
                        <button type="button" class="ds-btn ds-btn-sm" wire:click="edit({{ $question->id }})">تعديل</button>
                        <button type="button" class="ds-btn ds-btn-sm" wire:click="toggle({{ $question->id }})">
                            {{ $question->is_active ? 'إخفاء' : 'إظهار' }}
                        </button>
                    </td>
                </tr>
            @endforeach
        </x-ds-table>
    </section>
</x-ds-page>
