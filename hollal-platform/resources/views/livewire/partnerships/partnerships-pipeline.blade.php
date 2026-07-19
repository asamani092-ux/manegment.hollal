<x-ds-page>
    <x-ds-page-header title="رحلة الشراكات السباعية" />

    <section class="ds-section ds-filter-bar">
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('view', 'kanban')">لوحة الأعمدة</button>
        <button type="button" class="ds-btn ds-btn-sm" wire:click="$set('view', 'list')">قائمة</button>
        <span class="ds-text-muted">الشراكة الراكدة: أكثر من {{ $staleThreshold }} يومًا في المرحلة</span>
    </section>

    @if ($view === 'kanban')
        <section class="ds-section ds-kanban">
            @foreach ($pipelineStages as $stage)
                <div class="ds-kanban-column" wire:key="stage-col-{{ $stage }}">
                    <h3 class="ds-kanban-title">{{ $stageLabels[$stage] }}</h3>
                    @forelse ($board[$stage] as $partnership)
                        <div class="ds-kanban-card" wire:key="pipeline-card-{{ $partnership->id }}">
                            <p><strong>{{ $partnership->organization?->name ?? $partnership->entity_name ?? '—' }}</strong></p>
                            <p class="ds-text-muted">
                                القيمة المتوقعة:
                                <span class="ds-ltr-num">{{ $partnership->expected_value !== null ? number_format((float) $partnership->expected_value, 2) : '—' }}</span>
                            </p>
                            <p class="ds-text-muted">المتابع: {{ $partnership->owner?->name ?? '—' }}</p>
                            @if ($partnership->stageAgeDays() >= $staleThreshold)
                                <span class="ds-badge ds-badge-warning">راكدة {{ $partnership->stageAgeDays() }} يومًا</span>
                            @else
                                <span class="ds-badge ds-badge-info">{{ $partnership->stageAgeDays() }} يومًا</span>
                            @endif
                            @can('partnerships.pipeline.manage')
                                <button type="button" class="ds-btn ds-btn-sm" wire:click="openStageModal({{ $partnership->id }})">
                                    نقل المرحلة
                                </button>
                            @endcan
                        </div>
                    @empty
                        <p class="ds-text-muted">—</p>
                    @endforelse
                </div>
            @endforeach
        </section>
    @else
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>الجهة</th>
                    <th>المرحلة</th>
                    <th>عمر المرحلة</th>
                    <th>المتابع</th>
                    <th>القيمة المتوقعة</th>
                    <th>إجراءات</th>
                </tr>
            </x-slot:head>
            @forelse ($list as $partnership)
                <tr wire:key="pipeline-row-{{ $partnership->id }}">
                    <td>{{ $partnership->organization?->name ?? $partnership->entity_name ?? '—' }}</td>
                    <td>{{ $partnership->stageLabel() }}</td>
                    <td class="ds-ltr-num">{{ $partnership->stageAgeDays() }}</td>
                    <td>{{ $partnership->owner?->name ?? '—' }}</td>
                    <td class="ds-ltr-num">
                        {{ $partnership->expected_value !== null ? number_format((float) $partnership->expected_value, 2) : '—' }}
                    </td>
                    <td>
                        @can('partnerships.pipeline.manage')
                            <button type="button" class="ds-btn ds-btn-sm" wire:click="openStageModal({{ $partnership->id }})">
                                نقل المرحلة
                            </button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="ds-text-muted ds-table-empty">لا توجد شراكات</td></tr>
            @endforelse
        </x-ds-table>
    @endif

    <x-ds-modal :show="$showStageModal">
        <x-slot:header><h2>نقل مرحلة الشراكة</h2></x-slot:header>

        <x-ds-form-group label="المرحلة الجديدة" :error="$errors->first('targetStage')">
            <select class="ds-input" wire:model="targetStage">
                @foreach ($stageLabels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </x-ds-form-group>

        <x-ds-form-group label="ملاحظة الانتقال" :error="$errors->first('stageNote')">
            <input type="text" class="ds-input" wire:model="stageNote">
        </x-ds-form-group>

        <x-slot:footer>
            <button type="button" class="ds-btn" wire:click="$set('showStageModal', false)">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="moveStage">نقل</button>
        </x-slot:footer>
    </x-ds-modal>
</x-ds-page>
