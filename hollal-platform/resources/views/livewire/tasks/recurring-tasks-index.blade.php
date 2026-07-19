<x-ds-page>
    <x-ds-page-header title="المهام المتكررة" :show-button="true" button-label="قالب جديد" button-icon="fa-repeat" wire:click="openCreate" />

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>العنوان</th>
                <th>المكلَّف</th>
                <th>النمط</th>
                <th>الحالة</th>
                <th>إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($templates as $template)
            <tr wire:key="tpl-{{ $template->id }}">
                <td>{{ $template->title }}</td>
                <td>{{ $template->assignee?->name ?? '—' }}</td>
                <td>{{ $template->pattern }}</td>
                <td>
                    <span class="ds-badge {{ $template->is_active ? 'ds-badge-success' : 'ds-badge-pending' }}">
                        {{ $template->is_active ? 'مفعّل' : 'موقوف' }}
                    </span>
                </td>
                <td>
                    <button type="button" class="ds-link" wire:click="toggleActive({{ $template->id }})">
                        {{ $template->is_active ? 'إيقاف' : 'تفعيل' }}
                    </button>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="ds-text-muted ds-table-empty">لا توجد قوالب متكررة</td></tr>
        @endforelse
    </x-ds-table>

    @if ($showModal)
        <div class="ds-modal-overlay" wire:click.self="$set('showModal', false)">
            <div class="ds-modal" role="dialog">
                <div class="ds-modal-header">
                    <h3>قالب متكرر جديد</h3>
                    <button type="button" class="ds-modal-close" wire:click="$set('showModal', false)">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <x-ds-form-group label="العنوان" :error="$errors->first('title')">
                        <input type="text" class="ds-input" wire:model="title">
                    </x-ds-form-group>
                    <x-ds-form-group label="المكلَّف" :error="$errors->first('assigned_to_id')">
                        <select class="ds-input" wire:model="assigned_to_id">
                            <option value="">— اختر —</option>
                            @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                        </select>
                    </x-ds-form-group>
                    <x-ds-form-group label="النمط">
                        <select class="ds-input" wire:model.live="pattern">
                            <option value="أسبوعي">أسبوعي</option>
                            <option value="شهري">شهري</option>
                        </select>
                    </x-ds-form-group>
                    @if ($pattern === 'أسبوعي')
                        <x-ds-form-group label="يوم الأسبوع (0=الأحد)" :error="$errors->first('day_of_week')">
                            <input type="number" min="0" max="6" class="ds-input" wire:model="day_of_week" dir="ltr">
                        </x-ds-form-group>
                    @else
                        <x-ds-form-group label="يوم الشهر" :error="$errors->first('day_of_month')">
                            <input type="number" min="1" max="31" class="ds-input" wire:model="day_of_month" dir="ltr">
                        </x-ds-form-group>
                    @endif
                    <x-ds-form-group label="الدليل المطلوب (اختياري)">
                        <input type="text" class="ds-input" wire:model="required_evidence">
                    </x-ds-form-group>
                </div>
                <div class="ds-modal-footer">
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="save">حفظ</button>
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="$set('showModal', false)">إلغاء</button>
                </div>
            </div>
        </div>
    @endif
</x-ds-page>
