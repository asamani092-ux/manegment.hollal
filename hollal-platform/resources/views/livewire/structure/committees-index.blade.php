<x-ds-page>
    <x-ds-page-header title="اللجان" :show-button="false" />

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label" for="committees-search">الاسم</label>
            <input id="committees-search" type="search" class="ds-input" wire:model.live.debounce.400ms="search" placeholder="ابحث بالاسم…">
        </div>
        <div class="ds-filter-field">
            <label class="ds-label" for="committees-active">الحالة</label>
            <select id="committees-active" class="ds-input" wire:model.live="activeFilter">
                <option value="">— الكل —</option>
                <option value="1">نشطة</option>
                <option value="0">موقوفة</option>
            </select>
        </div>
    </div>

    <section class="ds-section-spaced">
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th scope="col">الاسم</th>
                    <th scope="col">الاختصاص</th>
                    <th scope="col">الرئيس</th>
                    <th scope="col">الحالة</th>
                </tr>
            </x-slot:head>
            @forelse ($committees as $committee)
                <tr wire:key="com-{{ $committee->id }}">
                    <td>{{ $committee->name }}</td>
                    <td>{{ $committee->mandate }}</td>
                    <td>{{ $committee->chair?->name ?? '—' }}</td>
                    <td><x-ds-status-badge :status="$committee->is_active ? 'نشطة' : 'موقوفة'" /></td>
                </tr>
            @empty
                <tr>
                    <td colspan="4"><x-ds-empty-state message="لا توجد لجان" icon="fa-users" /></td>
                </tr>
            @endforelse
        </x-ds-table>

        {{ $committees->links() }}
    </section>

    <p class="ds-text-muted">إدارة الهيكل التفصيلية من <a href="{{ route('structure.org-tree') }}">الهيكل التنظيمي</a>.</p>
</x-ds-page>
