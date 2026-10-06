<div>
    <x-ds-page>
        <x-ds-page-header title="القوائم المرجعية" screen="settings.lists" />

        <div class="ds-card" style="margin-bottom: 1rem;">
            @foreach ($lists as $list)
                <button type="button" class="ds-btn ds-btn-sm" wire:click="selectList('{{ $list->key }}')">{{ $list->name_ar }}</button>
            @endforeach
        </div>

        @if ($current && $current->key === 'violations')
            <livewire:hr.violation-catalog />
        @elseif ($current)
            <div class="ds-card">
                <h2 class="ds-page-title">{{ $current->name_ar }}</h2>
                <select class="ds-input" wire:model.live="statusFilter">
                    <option value="">الكل</option>
                    <option value="active">ساري</option>
                    <option value="draft">مسودة</option>
                    <option value="suspended">موقوف</option>
                    <option value="archived">مؤرشف</option>
                </select>

                <table class="ds-table">
                    <thead>
                        <tr>
                            <th>الرمز</th>
                            <th>الاسم</th>
                            <th>الحالة</th>
                            <th>النسخة</th>
                            <th>من</th>
                            <th>إلى</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item->code }}</td>
                                <td>{{ $item->name_ar }}</td>
                                <td>{{ \App\Support\ArabicStatus::label($item->status) }}</td>
                                <td>{{ $item->version }}</td>
                                <td>{{ $item->effective_from?->toDateString() }}</td>
                                <td>{{ $item->effective_to?->toDateString() }}</td>
                                <td>
                                    @if ($canManage)
                                        @if ($item->status === 'draft')
                                            <button type="button" class="ds-btn ds-btn-sm" wire:click="publish({{ $item->id }})">نشر</button>
                                        @endif
                                        <button type="button" class="ds-btn ds-btn-sm" wire:click="showHistory({{ $item->id }})">السجل</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($canManage)
                <div class="ds-card" style="margin-top: 1rem;">
                    <h3>عنصر جديد</h3>
                    <x-ds-form-group label="الرمز">
                        <input class="ds-input" wire:model="code">
                    </x-ds-form-group>
                    <x-ds-form-group label="الاسم">
                        <input class="ds-input" wire:model="name_ar">
                    </x-ds-form-group>
                    @foreach (($current->schema ?? []) as $field)
                        @include('livewire.settings.partials.schema-field', ['field' => $field])
                    @endforeach
                    <x-ds-form-group label="سريان من">
                        @include('livewire.settings.partials.arabic-date', ['value' => $effective_from, 'method' => 'setEffectiveFromPart'])
                    </x-ds-form-group>
                    <x-ds-form-group label="السبب" :error="$errors->first('reason')">
                        <input class="ds-input" wire:model="reason">
                    </x-ds-form-group>
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="saveDraft">حفظ مسودة</button>
                </div>

                <div class="ds-card" style="margin-top: 1rem;">
                    <h3>استيراد</h3>
                    <label class="ds-btn ds-btn-outline">
                        رفع الملف
                        <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)">
                    </label>
                    <button type="button" class="ds-btn" wire:click="previewImport">معاينة</button>
                    @if ($importPreview)
                        <p>إضافات: {{ count($importPreview['adds']) }} — تعديلات: {{ count($importPreview['changes']) }}</p>
                        <ul>
                            @foreach ($importPreview['adds'] as $add)
                                <li>إضافة {{ $add['code'] }}</li>
                            @endforeach
                            @foreach ($importPreview['changes'] as $change)
                                <li>تعديل {{ $change['code'] }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="commitImport">اعتماد الاستيراد</button>
                    @endif
                    <button type="button" class="ds-btn" wire:click="export">تصدير</button>
                </div>
            @endif

            @if ($historyId)
                <aside class="ds-card" style="margin-top: 1rem;">
                    <h3>سجل النسخ</h3>
                    @foreach ($history as $row)
                        <p>{{ $row->code }} نسخة {{ $row->version }} — {{ $row->name_ar }} — {{ \App\Support\ArabicStatus::label($row->status) }}</p>
                    @endforeach
                </aside>
            @endif
        @endif
    </x-ds-page>
</div>
