<x-ds-page>
    <x-ds-page-header title="الزيارات" :show-button="false" />

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label" for="visits-project">المشروع</label>
            <select id="visits-project" class="ds-input" wire:model.live="projectFilter">
                <option value="">— الكل —</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="ds-filter-field">
            <label class="ds-label" for="visits-status">الحالة</label>
            <select id="visits-status" class="ds-input" wire:model.live="statusFilter">
                <option value="">— الكل —</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status }}">{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div class="ds-filter-field">
            <label class="ds-label" for="visits-from">من تاريخ</label>
            <input id="visits-from" type="date" class="ds-input" wire:model.live="dateFrom">
        </div>
        <div class="ds-filter-field">
            <label class="ds-label" for="visits-to">إلى تاريخ</label>
            <input id="visits-to" type="date" class="ds-input" wire:model.live="dateTo">
        </div>
    </div>

    <div class="ds-task-cards ds-list-cards-mobile">
        @forelse ($visits as $visit)
            <article class="ds-task-card" wire:key="visit-card-{{ $visit->id }}">
                <h3 class="ds-task-card-title">{{ $visit->project?->name ?? '—' }}</h3>
                <div class="ds-task-card-meta">
                    <span>{{ $visit->visitor?->name ?? '—' }}</span>
                    <span class="ds-ltr-num">{{ $visit->scheduled_on?->format('Y-m-d') ?? '—' }}</span>
                </div>
                <x-ds-status-badge :status="$visit->status" />
                <p class="ds-text-muted">{{ $visit->purpose ?? '—' }}</p>
                @if ($visit->project_id)
                    <div class="ds-task-card-actions">
                        <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('projects.execution', $visit->project_id) }}?tab=visits">فتح</a>
                    </div>
                @endif
            </article>
        @empty
            <x-ds-empty-state message="لا توجد زيارات" icon="fa-route" />
        @endforelse
    </div>

    <div class="ds-list-table-desktop">
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th scope="col">المشروع</th>
                    <th scope="col">الزائر</th>
                    <th scope="col">التاريخ</th>
                    <th scope="col">الغرض</th>
                    <th scope="col">الحالة</th>
                    <th scope="col">إجراءات</th>
                </tr>
            </x-slot:head>
            @forelse ($visits as $visit)
                <tr wire:key="visit-{{ $visit->id }}">
                    <td>{{ $visit->project?->name ?? '—' }}</td>
                    <td>{{ $visit->visitor?->name ?? '—' }}</td>
                    <td class="ds-ltr-num">{{ $visit->scheduled_on?->format('Y-m-d') }}</td>
                    <td>{{ $visit->purpose }}</td>
                    <td><x-ds-status-badge :status="$visit->status" /></td>
                    <td>
                        @if ($visit->project_id)
                            <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('projects.execution', $visit->project_id) }}?tab=visits">فتح</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6"><x-ds-empty-state message="لا توجد زيارات" icon="fa-route" /></td>
                </tr>
            @endforelse
        </x-ds-table>
    </div>

    {{ $visits->links() }}
</x-ds-page>
