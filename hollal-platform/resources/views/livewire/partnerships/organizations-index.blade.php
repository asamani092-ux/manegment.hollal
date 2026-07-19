<x-ds-page>
    <x-ds-page-header
        title="الجهات الشريكة"
        :show-button="true"
        button-label="جهة جديدة"
        button-permission="partnerships.organizations.manage"
        wire:click="openCreate"
    />

    <section class="ds-section ds-filter-bar">
        <input type="search" class="ds-input" placeholder="بحث باسم الجهة" wire:model.live="search">
        <select class="ds-input" wire:model.live="typeFilter">
            <option value="">كل الأنواع</option>
            @foreach ($types as $option)
                <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
        </select>
    </section>

    <x-ds-table>
        <x-slot:head>
            <tr>
                <th>الجهة</th>
                <th>النوع</th>
                <th>المدينة</th>
                <th>الأدوار</th>
                <th>الشراكات</th>
                <th>إجراءات</th>
            </tr>
        </x-slot:head>
        @forelse ($organizations as $organization)
            <tr wire:key="organization-{{ $organization->id }}">
                <td><a href="{{ route('organizations.show', $organization->id) }}">{{ $organization->name }}</a></td>
                <td>{{ $organization->type ?? '—' }}</td>
                <td>{{ $organization->city ?? '—' }}</td>
                <td>{{ $organization->roles ? implode('، ', $organization->roles) : '—' }}</td>
                <td class="ds-ltr-num">{{ $organization->partnerships_count }}</td>
                <td>
                    @can('partnerships.organizations.manage')
                        <button type="button" class="ds-btn ds-btn-sm" wire:click="edit({{ $organization->id }})">تعديل</button>
                        <button type="button" class="ds-btn ds-btn-sm" wire:click="archive({{ $organization->id }})">أرشفة</button>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="ds-text-muted ds-table-empty">لا توجد جهات</td></tr>
        @endforelse
    </x-ds-table>

    {{ $organizations->links() }}

    <x-ds-modal :show="$showModal" size="lg">
        <x-slot:header><h2>{{ $editingId ? 'تعديل جهة' : 'جهة جديدة' }}</h2></x-slot:header>

        <x-ds-form-group label="اسم الجهة" :error="$errors->first('name')">
            <input type="text" class="ds-input" wire:model="name">
        </x-ds-form-group>

        <x-ds-form-group label="النوع" :error="$errors->first('type')">
            <select class="ds-input" wire:model="type">
                <option value="">—</option>
                @foreach ($types as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        </x-ds-form-group>

        <x-ds-form-group label="المدينة" :error="$errors->first('city')">
            <input type="text" class="ds-input" wire:model="city">
        </x-ds-form-group>

        <x-ds-form-group label="الأدوار" :error="$errors->first('roles')">
            @foreach ($roleOptions as $option)
                <label class="ds-checkbox">
                    <input type="checkbox" value="{{ $option }}" wire:model="roles"> {{ $option }}
                </label>
            @endforeach
        </x-ds-form-group>

        <x-ds-form-group label="ملاحظات" :error="$errors->first('notes')">
            <textarea class="ds-input" wire:model="notes"></textarea>
        </x-ds-form-group>

        <x-slot:footer>
            <button type="button" class="ds-btn" wire:click="$set('showModal', false)">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="save">حفظ</button>
        </x-slot:footer>
    </x-ds-modal>
</x-ds-page>
