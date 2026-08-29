<x-ds-page>
    <x-ds-page-header
        title="الاجتماعات"
        :show-button="auth()->user()->can('meetings.create')"
        button-label="اجتماع جديد"
        button-icon="fa-plus"
        wire:click="openCreate"
    />

    <div class="ds-page-toolbar">
        <a href="{{ route('meetings.open-decisions') }}" class="ds-btn ds-btn-outline">
            <svg class="ds-icon ds-icon-sm" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0V4.5c0-1.036.84-1.875 1.875-1.875h.375"/>
            </svg>
            قرارات مفتوحة
        </a>
    </div>

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label">بحث</label>
            <input type="search" class="ds-input" wire:model.live.debounce.300ms="search" placeholder="عنوان الاجتماع...">
        </div>
    </div>

    <section class="ds-section-spaced">
        <h2 class="ds-section-heading">الاجتماعات القادمة</h2>
        <div class="ds-meeting-cards-grid">
            @forelse ($upcomingMeetings as $meeting)
                <article class="ds-meeting-card is-square" wire:key="upcoming-{{ $meeting->id }}">
                    <div>
                        <h3 class="ds-task-card-title">{{ $meeting->title }}</h3>
                        <p class="ds-text-muted ds-ltr-num">{{ hollal_dt($meeting->scheduled_at) }}</p>
                        @if ($meeting->agenda)
                            <p class="ds-text-muted">{{ \Illuminate\Support\Str::limit($meeting->agenda, 80) }}</p>
                        @endif
                    </div>
                    <div class="ds-actions">
                        <a class="ds-btn-icon" href="{{ route('meetings.minutes', $meeting) }}" title="المحضر" aria-label="المحضر">
                            <svg class="ds-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                            </svg>
                        </a>
                        <x-ds-action-icons
                            class="ds-actions--inline"
                            :show-view="true"
                            :show-edit="auth()->user()->can('update', $meeting)"
                            :show-delete="auth()->user()->can('delete', $meeting)"
                            :view-action="'openView('.$meeting->id.')'"
                            :edit-action="'openEdit('.$meeting->id.')'"
                            :delete-action="'delete('.$meeting->id.')'"
                            delete-confirm="حذف هذا الاجتماع؟"
                        />
                    </div>
                </article>
            @empty
                <x-ds-empty-state message="لا توجد اجتماعات قادمة" icon="fa-calendar-alt" />
            @endforelse
        </div>
        {{ $upcomingMeetings->links() }}
    </section>

    <section class="ds-section-spaced">
        <h2 class="ds-section-heading">الاجتماعات السابقة</h2>
        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>العنوان</th>
                    <th>التاريخ</th>
                    <th>الحالة</th>
                    <th>إجراءات</th>
                </tr>
            </x-slot:head>
            @forelse ($pastMeetings as $meeting)
                <tr wire:key="past-{{ $meeting->id }}">
                    <td>{{ $meeting->title }}</td>
                    <td class="ds-ltr-num">{{ hollal_dt($meeting->scheduled_at) }}</td>
                    <td>{{ $meeting->statusLabel() }}</td>
                    <td>
                        <div class="ds-actions">
                            <a class="ds-btn-icon" href="{{ route('meetings.minutes', $meeting) }}" title="المحضر" aria-label="المحضر">
                                <svg class="ds-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                </svg>
                            </a>
                            <x-ds-action-icons
                                class="ds-actions--inline"
                                :show-view="true"
                                :show-edit="auth()->user()->can('update', $meeting)"
                                :show-delete="auth()->user()->can('delete', $meeting)"
                                :view-action="'openView('.$meeting->id.')'"
                                :edit-action="'openEdit('.$meeting->id.')'"
                                :delete-action="'delete('.$meeting->id.')'"
                                delete-confirm="حذف هذا الاجتماع؟"
                            />
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4"><x-ds-empty-state message="لا توجد اجتماعات سابقة" icon="fa-calendar-alt" /></td></tr>
            @endforelse
        </x-ds-table>
        {{ $pastMeetings->links() }}
    </section>

    @if ($showModal)
        <div class="ds-modal-overlay" wire:click.self="closeModal">
            <div class="ds-modal ds-modal-lg" role="dialog" dir="rtl">
                <div class="ds-modal-header">
                    <h3>
                        @if ($viewOnly)
                            عرض اجتماع
                        @elseif ($meetingId)
                            تعديل اجتماع
                        @else
                            اجتماع جديد
                        @endif
                    </h3>
                    <button type="button" class="ds-modal-close" wire:click="closeModal">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <x-ds-form-group label="العنوان" :error="$errors->first('title')">
                        <input type="text" class="ds-input" wire:model="title" @disabled($viewOnly)>
                    </x-ds-form-group>
                    <x-ds-form-group label="التاريخ والوقت" :error="$errors->first('scheduled_at')">
                        <input type="datetime-local" class="ds-input" wire:model="scheduled_at" @disabled($viewOnly)>
                    </x-ds-form-group>
                    <x-ds-form-group label="رئيس الاجتماع" :error="$errors->first('chairId')">
                        <select class="ds-input" wire:model="chairId" @disabled($viewOnly)>
                            <option value="">اختر رئيس الاجتماع…</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </x-ds-form-group>
                    <x-ds-form-group label="جدول الأعمال" :error="$errors->first('agenda')">
                        <textarea class="ds-input" rows="3" wire:model="agenda" @disabled($viewOnly) placeholder="نقاط جدول الأعمال..."></textarea>
                    </x-ds-form-group>

                    <div class="ds-form-group">
                        <label class="ds-label">الحضور من الموظفين</label>

                        @unless ($viewOnly)
                            <div class="ds-grid-2">
                                <div wire:key="employee-picker-{{ count($attendeeIds) }}">
                                    <x-ds-search-select
                                        :options="$pickableUsers"
                                        wire-model="pickEmployeeId"
                                        label-key="name"
                                        placeholder="ابحث عن موظف بالاسم للإضافة…"
                                    />
                                </div>
                                <select class="ds-input" wire:model="pickCommitteeId">
                                    <option value="">إضافة أعضاء لجنة بالكامل…</option>
                                    @foreach ($committees as $committee)
                                        <option value="{{ $committee->id }}">{{ $committee->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endunless

                        <div class="ds-chip-list ds-mt-2">
                            @forelse ($attendeeUsers as $user)
                                <span class="ds-chip" wire:key="attendee-chip-{{ $user->id }}">
                                    {{ $user->name }}
                                    @unless ($viewOnly)
                                        <button type="button" class="ds-chip-remove" wire:click="removeAttendee({{ $user->id }})" aria-label="إزالة {{ $user->name }}">&times;</button>
                                    @endunless
                                </span>
                            @empty
                                <span class="ds-text-muted">لا يوجد حضور مضاف بعد</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="ds-form-group">
                        <label class="ds-label">ضيوف خارجيون (بدون حساب موظف)</label>

                        @if ($existingGuests->isNotEmpty())
                            <div class="ds-chip-list ds-mb-2">
                                @foreach ($existingGuests as $guest)
                                    <span class="ds-chip" wire:key="existing-guest-{{ $guest->id }}">
                                        {{ $guest->name }} — {{ $guest->email }}
                                        @if ($guest->confirmed_at)
                                            <i class="fas fa-check-circle" title="أكّد الاطلاع" aria-hidden="true"></i>
                                        @elseif (! $viewOnly)
                                            <button type="button" class="ds-chip-remove" wire:click="removeGuest({{ $guest->id }})" aria-label="إزالة {{ $guest->name }}">&times;</button>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        @unless ($viewOnly)
                            @foreach ($guestRows as $i => $row)
                                <div class="ds-grid-2 ds-mb-2" wire:key="guest-row-{{ $i }}">
                                    <input type="text" class="ds-input" wire:model="guestRows.{{ $i }}.name" placeholder="اسم الضيف">
                                    <div style="display:flex; gap:0.5rem;">
                                        <input type="email" class="ds-input" wire:model="guestRows.{{ $i }}.email" placeholder="بريد الضيف الإلكتروني">
                                        <button type="button" class="ds-btn-icon" wire:click="removeGuestRow({{ $i }})" aria-label="حذف السطر" title="حذف السطر">&times;</button>
                                    </div>
                                </div>
                                @error('guestRows.'.$i.'.name') <p class="ds-field-error">{{ $message }}</p> @enderror
                                @error('guestRows.'.$i.'.email') <p class="ds-field-error">{{ $message }}</p> @enderror
                            @endforeach
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="addGuestRow">
                                <i class="fas fa-plus" aria-hidden="true"></i> إضافة ضيف
                            </button>
                        @endunless
                    </div>
                </div>
                <div class="ds-modal-footer">
                    @if (! $viewOnly)
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save"><i class="fas fa-save" aria-hidden="true"></i> حفظ</span>
                            <span wire:loading wire:target="save" class="ds-btn-loading">جاري الحفظ…</span>
                        </button>
                    @endif
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="closeModal">إغلاق</button>
                </div>
            </div>
        </div>
    @endif
</x-ds-page>
