<x-ds-page>
    <x-ds-page-header title="عبء عمل الفريق" />

    <nav class="ds-tabs" role="tablist">
        <button type="button" class="ds-tab {{ $tab === 'loads' ? 'ds-tab-active' : '' }}" wire:click="$set('tab','loads')">أحمال الفريق</button>
        <button type="button" class="ds-tab {{ $tab === 'recurring' ? 'ds-tab-active' : '' }}" wire:click="$set('tab','recurring')">المهام المتكررة</button>
        <button type="button" class="ds-tab {{ $tab === 'reminders' ? 'ds-tab-active' : '' }}" wire:click="$set('tab','reminders')">التذكيرات</button>
    </nav>

    @if ($tab === 'loads')
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
                    <td class="ds-ltr-num">{{ $template->starts_on?->format('Y-m-d') ?? '—' }}</td>
                    <td class="ds-ltr-num">{{ $template->ends_on?->format('Y-m-d') ?? '—' }}</td>
                    <td>{{ $template->open_instances_count }}</td>
                    <td>{{ $template->completed_instances_count }}</td>
                    <td>{{ $template->is_active ? 'مفعّل' : 'موقوف' }}</td>
                    <td>
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="sendReminder({{ $template->assigned_to_id }}, {{ $template->id }})">تذكير</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="ds-text-muted">لا قوالب متكررة للفريق</td></tr>
            @endforelse
        </x-ds-table>
    @else
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
    @endif
</x-ds-page>
