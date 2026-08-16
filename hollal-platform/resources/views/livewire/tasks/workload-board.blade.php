<x-ds-page>
    <x-ds-page-header
        title="لوحة الأحمال"
        :show-button="auth()->user()->can('esnad.tasks.create')"
        button-label="قالب متكرر"
        button-icon="fa-repeat"
        wire:click="openCreate"
    />

    <nav class="ds-tabs" role="tablist">
        @can('esnad.tasks.team.view')
            <button type="button" class="ds-tab {{ $tab === 'loads' ? 'ds-tab-active' : '' }}" wire:click="$set('tab','loads')">أحمال الفريق</button>
        @endcan
        <button type="button" class="ds-tab {{ $tab === 'recurring' ? 'ds-tab-active' : '' }}" wire:click="$set('tab','recurring')">قوالب متكررة</button>
        <button type="button" class="ds-tab {{ $tab === 'reminders' ? 'ds-tab-active' : '' }}" wire:click="$set('tab','reminders')">متابعة / تذكيرات</button>
    </nav>

    @if ($tab === 'loads' && auth()->user()->can('esnad.tasks.team.view'))
        <p class="ds-text-muted">حد التنبيه: أكثر من {{ $threshold }} مهمة مفتوحة</p>
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>الموظف</th>
                    <th>مفتوحة</th>
                    <th>متأخرة</th>
                    <th>مستحقة هذا الأسبوع</th>
                    <th>تقييمات 30 يومًا</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr wire:key="workload-{{ $row['user']->id }}">
                    <td>{{ $row['user']->name }}</td>
                    <td>
                        {{ $row['open'] }}
                        @if ($row['overloaded'])
                            <span class="ds-badge ds-badge-warning">عبء مرتفع</span>
                        @endif
                    </td>
                    <td>{{ $row['overdue'] }}</td>
                    <td>{{ $row['due_this_week'] }}</td>
                    <td>
                        @forelse ($row['ratings'] as $label => $count)
                            <span class="ds-badge">{{ $label }}: {{ $count }}</span>
                        @empty
                            —
                        @endforelse
                    </td>
                    <td>
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="sendReminder({{ $row['user']->id }})">تذكير</button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="ds-text-muted ds-table-empty">لا يوجد أعضاء فريق</td>
                </tr>
            @endforelse
        </x-ds-table>
    @elseif ($tab === 'recurring')
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>القالب</th>
                    <th>الموظف</th>
                    <th>النمط</th>
                    <th>من</th>
                    <th>إلى</th>
                    <th>مفتوحة</th>
                    <th>مكتملة</th>
                    <th>الحالة</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($recurringTemplates as $template)
                <tr wire:key="wl-tpl-{{ $template->id }}">
                    <td>{{ $template->title }}</td>
                    <td>{{ $template->assignee?->name ?? '—' }}</td>
                    <td>{{ $template->pattern }}</td>
                    <td class="ds-ltr-num">{{ $template->starts_on?->format('Y-m-d') ?? '—' }}</td>
                    <td class="ds-ltr-num">{{ $template->ends_on?->format('Y-m-d') ?? '—' }}</td>
                    <td>{{ $template->open_instances_count }}</td>
                    <td>{{ $template->completed_instances_count }}</td>
                    <td>
                        <span class="ds-badge {{ $template->is_active ? 'ds-badge-success' : 'ds-badge-danger' }}">
                            {{ $template->is_active ? 'مفعّل' : 'موقوف' }}
                        </span>
                    </td>
                    <td class="ds-toolbar-actions" style="gap:.35rem">
                        @can('esnad.tasks.update')
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="toggleActive({{ $template->id }})">
                                {{ $template->is_active ? 'إيقاف' : 'تفعيل' }}
                            </button>
                        @endcan
                        @can('esnad.tasks.team.view')
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="sendReminder({{ $template->assigned_to_id }}, {{ $template->id }})">تذكير</button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="ds-text-muted">لا قوالب متكررة</td></tr>
            @endforelse
        </x-ds-table>
    @else
        <section class="ds-section-spaced">
            <h3 class="ds-section-heading">تقرير متابعة موظف (متكرر)</h3>
            <div class="ds-filters-row">
                <div class="ds-filter-field">
                    <label class="ds-label">الموظف</label>
                    <select class="ds-input" wire:model.live="followUpUserId">
                        <option value="">— اختر —</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            @if ($followUpUserId)
                <x-ds-table>
                    <x-slot:head>
                        <tr>
                            <th>المهمة</th>
                            <th>الحالة</th>
                            <th>الاستحقاق</th>
                            <th>الإكمال</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($followUp as $task)
                        <tr wire:key="fu-{{ $task->id }}">
                            <td>{{ $task->title }}</td>
                            <td>{{ $statusLabels[$task->status] ?? $task->status }}</td>
                            <td class="ds-ltr-num">{{ $task->due_date?->format('Y-m-d') ?? '—' }}</td>
                            <td class="ds-ltr-num">{{ $task->completed_at?->format('Y-m-d') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="ds-text-muted">لا دورات مولّدة لهذا الموظف</td></tr>
                    @endforelse
                </x-ds-table>
            @endif
        </section>

        @can('esnad.tasks.team.view')
            <h3 class="ds-section-heading">مهام متأخرة للفريق</h3>
            @forelse ($overdueForTeam as $task)
                <div class="ds-stat-mini" wire:key="wl-od-{{ $task->id }}">
                    <strong>{{ $task->title }}</strong>
                    <span class="ds-text-muted">{{ $task->assignee?->name }} — {{ $task->due_date?->format('Y-m-d') }}</span>
                    <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="sendReminder({{ $task->assigned_to }})">تذكير</button>
                </div>
            @empty
                <p class="ds-text-muted">لا مهام متأخرة</p>
            @endforelse
        @endcan
    @endif

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
                    <x-ds-form-group label="المشروع">
                        <select class="ds-input" wire:model="project_id">
                            <option value="">— بدون —</option>
                            @foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
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
                    <x-ds-form-group label="تاريخ البداية" :error="$errors->first('starts_on')">
                        <input type="date" class="ds-input" wire:model="starts_on" dir="ltr">
                    </x-ds-form-group>
                    <x-ds-form-group label="تاريخ النهاية" :error="$errors->first('ends_on')">
                        <input type="date" class="ds-input" wire:model="ends_on" dir="ltr">
                    </x-ds-form-group>
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
