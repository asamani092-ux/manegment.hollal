<x-ds-page>
    @php
        $itemStatusLabels = ['open' => 'مفتوح', 'in_progress' => 'قيد التنفيذ', 'done' => 'منجز'];
    @endphp

    <x-ds-page-header title="القرارات" :back-url="route('meetings.index')" back-label="رجوع" />

    <p class="ds-text-muted" style="max-width:40rem;margin-bottom:1rem">
        اختر اجتماعاً لعرض قراراته المفتوحة، ثم أغلق القرار أو انتقل للمهمة/المحضر.
    </p>

    <nav class="ds-tabs" role="tablist">
        <button type="button" class="ds-tab {{ $tab === 'open' ? 'ds-tab-active' : '' }}" wire:click="$set('tab','open')">
            مفتوحة
        </button>
        <button type="button" class="ds-tab {{ $tab === 'archived' ? 'ds-tab-active' : '' }}" wire:click="$set('tab','archived')">
            مؤرشفة (مغلقة)
        </button>
    </nav>

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label">بحث</label>
            <input type="search" class="ds-input" wire:model.live.debounce.300ms="search" placeholder="اجتماع أو موضوع أو قرار...">
        </div>
    </div>

    @if ($selectedMeeting)
        <div class="ds-page-toolbar" style="margin-bottom:1rem">
            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="clearMeeting">← كل الاجتماعات</button>
            <h2 class="ds-section-heading" style="margin:0">
                {{ $selectedMeeting->title }}
                <span class="ds-text-muted ds-ltr-num">{{ hollal_dt($selectedMeeting->scheduled_at) }}</span>
            </h2>
            <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('meetings.minutes', $selectedMeeting) }}">فتح المحضر</a>
        </div>

        <div class="ds-table-wrap">
            <x-ds-table>
                <x-slot:head>
                    <tr>
                        <th>الموضوع</th>
                        <th>القرار</th>
                        <th>المسؤول</th>
                        <th>تاريخ الاستحقاق</th>
                        <th>الحالة</th>
                        <th>المهمة</th>
                        @if ($archived)
                            <th>سبب الإغلاق</th>
                        @else
                            <th>إجراءات</th>
                        @endif
                    </tr>
                </x-slot:head>
                @forelse ($decisions as $item)
                    <tr wire:key="decision-{{ $item->id }}">
                        <td>{{ $item->topic }}</td>
                        <td>{{ $item->decision }}</td>
                        <td>{{ $item->responsible?->name ?? '—' }}</td>
                        <td class="ds-ltr-num">{{ $item->due_date?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $itemStatusLabels[$item->status] ?? $item->status }}</td>
                        <td>
                            @if ($item->task)
                                <a class="ds-link" href="{{ route('tasks.index', ['open' => $item->task_id]) }}">{{ $item->task->title }}</a>
                            @else
                                <span class="ds-text-muted">لم تُحوَّل بعد</span>
                            @endif
                        </td>
                        @if ($archived)
                            <td>{{ $item->close_reason ?? '—' }}</td>
                        @else
                            <td>
                                @can('meetings.update')
                                    <button type="button" class="ds-btn ds-btn-sm" wire:click="openClose({{ $item->id }})">إغلاق</button>
                                @endcan
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="ds-text-muted ds-table-empty">
                            {{ $archived ? 'لا قرارات مؤرشفة لهذا الاجتماع' : 'لا قرارات مفتوحة لهذا الاجتماع' }}
                        </td>
                    </tr>
                @endforelse
            </x-ds-table>
        </div>
        {{ $decisions->links() }}
    @else
        <div class="ds-table-wrap">
            <x-ds-table>
                <x-slot:head>
                    <tr>
                        <th>الاجتماع</th>
                        <th>التاريخ</th>
                        <th>{{ $archived ? 'قرارات مغلقة' : 'قرارات مفتوحة' }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @forelse ($meetingGroups as $row)
                    <tr wire:key="meet-group-{{ $row->id }}">
                        <td>{{ $row->title }}</td>
                        <td class="ds-ltr-num">{{ hollal_dt($row->scheduled_at) }}</td>
                        <td class="ds-ltr-num">{{ $row->open_count }}</td>
                        <td>
                            <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" wire:click="selectMeeting({{ $row->id }})">
                                عرض القرارات
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="ds-text-muted ds-table-empty">
                            {{ $archived ? 'لا اجتماعات بقرارات مؤرشفة' : 'لا اجتماعات بقرارات مفتوحة' }}
                        </td>
                    </tr>
                @endforelse
            </x-ds-table>
        </div>
        {{ $meetingGroups->links() }}
    @endif

    <x-ds-modal :show="$showCloseModal">
        <x-slot:header><h2>إغلاق القرار</h2></x-slot:header>
        <x-ds-form-group label="سبب الإغلاق" :error="$errors->first('closeReason')">
            <textarea class="ds-input" wire:model="closeReason" rows="3"></textarea>
        </x-ds-form-group>
        <x-slot:footer>
            <button type="button" class="ds-btn" wire:click="$set('showCloseModal', false)">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="closeDecision">إغلاق</button>
        </x-slot:footer>
    </x-ds-modal>
</x-ds-page>
