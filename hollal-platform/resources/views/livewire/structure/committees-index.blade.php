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
                    <th scope="col">الأعضاء</th>
                    <th scope="col">الحالة</th>
                    <th scope="col">إجراءات</th>
                </tr>
            </x-slot:head>
            @forelse ($committees as $committee)
                <tr wire:key="com-{{ $committee->id }}">
                    <td>{{ $committee->name }}</td>
                    <td>{{ $committee->mandate }}</td>
                    <td>{{ $committee->chair?->name ?? '—' }}</td>
                    <td class="ds-ltr-num">
                        {{ $committee->members_count }}
                        @if (! empty($committee->guests))
                            + {{ count($committee->guests) }} ضيف
                        @endif
                    </td>
                    <td><x-ds-status-badge :status="$committee->is_active ? 'نشطة' : 'موقوفة'" /></td>
                    <td>
                        @if ($canManage)
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openManage({{ $committee->id }})">الأعضاء والضيوف</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6"><x-ds-empty-state message="لا توجد لجان" icon="fa-users" /></td>
                </tr>
            @endforelse
        </x-ds-table>

        {{ $committees->links() }}
    </section>

    <p class="ds-text-muted">إنشاء اللجان من <a href="{{ route('structure.org-tree') }}">الهيكل التنظيمي</a>.</p>

    <x-ds-modal :show="$managingId !== null" title="أعضاء اللجنة والضيوف" close-action="closeManage" size="lg">
        @if ($managing)
            <h3 class="ds-section-title">{{ $managing->name }}</h3>

            <h4>موظفون</h4>
            <ul>
                @forelse ($managing->members as $member)
                    <li wire:key="mem-{{ $member->id }}">
                        {{ $member->name }}
                        <span class="ds-text-muted">({{ $member->pivot->role_label ?? 'عضو' }})</span>
                        <button type="button" class="ds-link" wire:click="removeMember({{ $member->id }})">إزالة</button>
                    </li>
                @empty
                    <li class="ds-text-muted">لا أعضاء بعد</li>
                @endforelse
            </ul>
            <div class="ds-filters-row">
                <div class="ds-filter-field">
                    <label class="ds-label">موظف</label>
                    <select class="ds-input" wire:model="addUserId">
                        <option value="">—</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('addUserId') <p class="ds-field-error">{{ $message }}</p> @enderror
                </div>
                <div class="ds-filter-field">
                    <label class="ds-label">الدور في اللجنة</label>
                    <input type="text" class="ds-input" wire:model="addRoleLabel" placeholder="عضو / مقرر…">
                </div>
                <div class="ds-filter-field" style="align-self:end">
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="addMember">إضافة موظف</button>
                </div>
            </div>

            <h4 class="ds-mt-3">ضيوف غير موظفين (مستشار / أخصائي…)</h4>
            <ul>
                @forelse ($managing->guests ?? [] as $guest)
                    <li wire:key="guest-{{ $guest['id'] ?? $loop->index }}">
                        {{ $guest['name'] ?? '—' }}
                        — {{ $guest['role_label'] ?? '' }}
                        @if (! empty($guest['organization'])) ({{ $guest['organization'] }}) @endif
                        <button type="button" class="ds-link" wire:click="removeGuest('{{ $guest['id'] }}')">إزالة</button>
                    </li>
                @empty
                    <li class="ds-text-muted">لا ضيوف بعد</li>
                @endforelse
            </ul>
            <div class="ds-filters-row">
                <div class="ds-filter-field">
                    <label class="ds-label">الاسم</label>
                    <input type="text" class="ds-input" wire:model="guestName">
                    @error('guestName') <p class="ds-field-error">{{ $message }}</p> @enderror
                </div>
                <div class="ds-filter-field">
                    <label class="ds-label">الصفة</label>
                    <input type="text" class="ds-input" wire:model="guestRole" placeholder="مستشار / أخصائي">
                </div>
                <div class="ds-filter-field">
                    <label class="ds-label">الجهة (اختياري)</label>
                    <input type="text" class="ds-input" wire:model="guestOrg">
                </div>
                <div class="ds-filter-field" style="align-self:end">
                    <button type="button" class="ds-btn ds-btn-teal" wire:click="addGuest">إضافة ضيف</button>
                </div>
            </div>
        @endif
    </x-ds-modal>
</x-ds-page>
