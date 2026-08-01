<x-ds-page>
    <x-ds-page-header title="الوظائف" :show-button="false" />

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label" for="jobs-search">المسمى</label>
            <input id="jobs-search" type="search" class="ds-input" wire:model.live.debounce.400ms="search" placeholder="ابحث بالمسمى…">
        </div>
        <div class="ds-filter-field">
            <label class="ds-label" for="jobs-parent">الوحدة الأب</label>
            <select id="jobs-parent" class="ds-input" wire:model.live="parentFilter">
                <option value="">— الكل —</option>
                @foreach ($parentUnits as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th scope="col">المسمى</th>
                <th scope="col">المستوى</th>
                <th scope="col">الوحدة الأب</th>
                <th scope="col">المسؤول المباشر</th>
                <th scope="col">إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($jobs as $job)
            <tr wire:key="job-{{ $job->id }}">
                <td>{{ $job->name }}</td>
                <td>{{ $job->level }}</td>
                <td>{{ $job->parent?->name ?? '—' }}</td>
                <td>{{ $job->manager?->name ?? '—' }}</td>
                <td><a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('structure.org-tree') }}">الهيكل</a></td>
            </tr>
        @empty
            <tr>
                <td colspan="5"><x-ds-empty-state message="لا توجد بطاقات وظيفية" icon="fa-id-badge" /></td>
            </tr>
        @endforelse
    </x-ds-table>

    {{ $jobs->links() }}
</x-ds-page>
