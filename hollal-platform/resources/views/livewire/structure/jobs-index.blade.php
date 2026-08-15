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
                <td><span class="org-node__badge org-node__badge--job">{{ $job->level }}</span></td>
                <td>{{ $job->parent?->name ?? '—' }}</td>
                <td>{{ $job->manager?->name ?? '—' }}</td>
                <td>
                    @if ($canManage)
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openEdit({{ $job->id }})">تعديل البطاقة</button>
                    @endif
                    <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('structure.org-tree') }}">الهيكل</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5"><x-ds-empty-state message="لا توجد بطاقات وظيفية" icon="fa-id-badge" /></td>
            </tr>
        @endforelse
    </x-ds-table>

    {{ $jobs->links() }}

    <x-ds-modal :show="$editingId !== null" title="تعديل بطاقة الوظيفة" close-action="closeEdit" size="lg">
        <x-ds-form-group label="المسمى" :error="$errors->first('editName')">
            <input type="text" class="ds-input" wire:model="editName">
        </x-ds-form-group>
        <x-ds-form-group label="الوحدة الأب" :error="$errors->first('editParentId')">
            <select class="ds-input" wire:model="editParentId">
                <option value="">—</option>
                @foreach ($parentUnits as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                @endforeach
            </select>
        </x-ds-form-group>
        <x-ds-form-group label="المسؤول المباشر" :error="$errors->first('editManagerId')">
            <select class="ds-input" wire:model="editManagerId">
                <option value="">—</option>
                @foreach ($managers as $manager)
                    <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                @endforeach
            </select>
        </x-ds-form-group>
        <x-ds-form-group label="الغرض من الوظيفة" :error="$errors->first('editPurpose')">
            <textarea class="ds-input" rows="3" wire:model="editPurpose"></textarea>
        </x-ds-form-group>
        <x-ds-form-group label="المسؤوليات (سطر لكل بند)" :error="$errors->first('editResponsibilities')">
            <textarea class="ds-input" rows="5" wire:model="editResponsibilities"></textarea>
        </x-ds-form-group>
        <x-ds-form-group label="المتطلبات (سطر لكل بند)" :error="$errors->first('editRequirements')">
            <textarea class="ds-input" rows="4" wire:model="editRequirements"></textarea>
        </x-ds-form-group>
        <div class="ds-toolbar-actions">
            <button type="button" class="ds-btn ds-btn-primary" wire:click="saveEdit">حفظ</button>
            <button type="button" class="ds-btn ds-btn-outline" wire:click="closeEdit">إلغاء</button>
        </div>
    </x-ds-modal>
</x-ds-page>
