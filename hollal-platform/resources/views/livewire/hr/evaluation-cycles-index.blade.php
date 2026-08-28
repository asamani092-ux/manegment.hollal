<x-ds-page>
    <div class="ds-toolbar-actions ds-mb-3">
        <button type="button" class="ds-btn ds-btn-primary" wire:click="openCreate">
            <i class="fas fa-plus" aria-hidden="true"></i> دورة جديدة
        </button>
        <a href="{{ route('evaluation-templates.index') }}" class="ds-btn ds-btn-secondary">قوالب التقييم</a>
    </div>

    <x-ds-page-header title="دورات التقييم الربعي" :show-button="false" />

    <p class="ds-text-muted ds-mb-3">
        عند فتح الدورة تُنسخ بنود القالب كلقطة ثابتة لا تتأثر بتعديل القالب لاحقاً.
        الفتح الجماعي يستثني المجمدين ومنتهية علاقتهم ومن انضموا بعد بداية الربع.
    </p>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th scope="col">الفترة</th>
                <th scope="col">القالب</th>
                <th scope="col">من–إلى</th>
                <th scope="col">الحالة</th>
                <th scope="col">بنود اللقطة</th>
                <th scope="col">تقييمات</th>
                <th scope="col">إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($cycles as $cycle)
            <tr wire:key="cycle-{{ $cycle->id }}">
                <td class="ds-ltr-num">{{ $cycle->periodLabel() }}</td>
                <td>{{ $cycle->template?->name ?? '—' }}</td>
                <td class="ds-ltr-num">
                    {{ $cycle->starts_at?->toDateString() }}
                    –
                    {{ $cycle->ends_at?->toDateString() }}
                </td>
                <td><x-ds-status-badge :status="$cycle->status" /></td>
                <td class="ds-ltr-num">{{ $cycle->items_count }}</td>
                <td class="ds-ltr-num">{{ $cycle->employee_evaluations_count }}</td>
                <td>
                    <x-ds-row-menu align="end">
                        @if ($cycle->status === \App\Models\EvaluationCycle::STATUS_DRAFT)
                            <button type="button" class="ds-dropdown-item" wire:click="openCycle({{ $cycle->id }})" wire:confirm="فتح الدورة ولقط بنود القالب؟">فتح الدورة</button>
                        @endif
                        @if ($cycle->status === \App\Models\EvaluationCycle::STATUS_OPEN)
                            <button type="button" class="ds-dropdown-item" wire:click="bulkOpen({{ $cycle->id }})" wire:confirm="فتح تقييمات جماعية للمؤهلين؟">فتح جماعي</button>
                        @endif
                    </x-ds-row-menu>
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><x-ds-empty-state message="لا توجد دورات تقييم" icon="fa-calendar-alt" /></td></tr>
        @endforelse
    </x-ds-table>
    {{ $cycles->links() }}

    <x-ds-modal :show="$showCreate" title="إنشاء دورة تقييم ربعية" close-action="$set('showCreate', false)" size="md">
        <x-ds-form-group label="السنة" :error="$errors->first('year')">
            <input type="number" class="ds-input ds-ltr-num" wire:model.live="year" min="2020" max="2100">
        </x-ds-form-group>
        <x-ds-form-group label="الربع" :error="$errors->first('quarter')">
            <select class="ds-input" wire:model.live="quarter">
                <option value="1">الربع الأول</option>
                <option value="2">الربع الثاني</option>
                <option value="3">الربع الثالث</option>
                <option value="4">الربع الرابع</option>
            </select>
        </x-ds-form-group>
        <x-ds-form-group label="قالب التقييم" :error="$errors->first('evaluation_template_id')">
            <select class="ds-input" wire:model="evaluation_template_id">
                <option value="">— اختر قالباً —</option>
                @foreach ($templates as $template)
                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                @endforeach
            </select>
        </x-ds-form-group>
        <x-ds-form-group label="من تاريخ" :error="$errors->first('starts_at')">
            <input type="date" class="ds-input ds-ltr-num" wire:model="starts_at">
        </x-ds-form-group>
        <x-ds-form-group label="إلى تاريخ" :error="$errors->first('ends_at')">
            <input type="date" class="ds-input ds-ltr-num" wire:model="ends_at">
        </x-ds-form-group>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="createCycle">حفظ كمسودة</button>
    </x-ds-modal>
</x-ds-page>
