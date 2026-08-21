<x-ds-page>
    <x-ds-page-header title="عبء عمل الفريق" />

    <p class="ds-text-muted">حد التنبيه: أكثر من {{ $threshold }} مهمة مفتوحة</p>
    <div class="ds-mb-3">
        <button type="button" class="ds-btn ds-btn-primary" wire:click="sendTeamReminder" wire:confirm="إرسال تذكير جماعي لكل من لديه مهام مفتوحة؟">تذكير جماعي</button>
    </div>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>الموظف</th>
                <th>مفتوحة</th>
                <th>متأخرة</th>
                <th>مستحقة هذا الأسبوع</th>
                <th>تقييمات 30 يومًا</th>
                <th>تذكير</th>
            </tr>
        </x-slot:head>
        @forelse ($rows as $row)
            <tr wire:key="workload-{{ $row['user']->id }}">
                <td>{{ $row['user']->name }}</td>
                <td>
                    <a href="{{ route('team-tasks.index', ['tab' => 'team', 'assigneeId' => $row['user']->id]) }}">{{ $row['open'] }}</a>
                    @if ($row['overloaded'])
                        <span class="ds-badge ds-badge-warning">عبء مرتفع</span>
                    @endif
                </td>
                <td>
                    <a href="{{ route('team-tasks.index', ['tab' => 'overdue', 'assigneeId' => $row['user']->id]) }}">{{ $row['overdue'] }}</a>
                </td>
                <td>{{ $row['due_this_week'] }}</td>
                <td>
                    @forelse ($row['ratings'] as $label => $count)
                        <span class="ds-badge">{{ $label }}: {{ $count }}</span>
                    @empty
                        —
                    @endforelse
                </td>
                <td>
                    <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="sendReminder({{ $row['user']->id }})">
                        تذكير
                    </button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="ds-text-muted ds-table-empty">لا يوجد أعضاء فريق</td>
            </tr>
        @endforelse
    </x-ds-table>

    <section class="ds-section-spaced">
        <h2 class="ds-section-heading">القوالب المتكررة للفريق</h2>
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>القالب</th>
                    <th>المكلَّف</th>
                    <th>النمط</th>
                    <th>إجراء</th>
                </tr>
            </x-slot:head>
            @forelse ($recurringTemplates as $template)
                <tr wire:key="wl-tpl-{{ $template->id }}">
                    <td>{{ $template->title }}</td>
                    <td>{{ $template->assignee?->name ?? '—' }}</td>
                    <td>{{ $template->pattern }}</td>
                    <td>
                        <button
                            type="button"
                            class="ds-btn ds-btn-outline ds-btn-sm"
                            wire:click="sendReminder({{ $template->assigned_to_id }}, {{ $template->id }})"
                        >
                            تذكير
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="ds-text-muted ds-table-empty">لا قوالب متكررة نشطة لأعضاء الفريق</td>
                </tr>
            @endforelse
        </x-ds-table>
    </section>
</x-ds-page>
