<div>
    @php
        $statusLabels = ['scheduled' => 'مجدول', 'in_progress' => 'جاري', 'completed' => 'مكتمل', 'cancelled' => 'ملغى'];
    @endphp

    <x-ds-page-header
        title="الاجتماعات"
        :show-button="auth()->user()->can('meetings.create')"
        button-label="اجتماع جديد"
        button-icon="fa-plus"
        wire:click="openCreate"
    />

    <div class="ds-page-toolbar">
        <a href="{{ route('meetings.open-decisions') }}" class="ds-btn ds-btn-outline">
            <i class="fas fa-clipboard-list"></i> قرارات مفتوحة
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
        <div class="ds-table-wrap">
            <x-ds-table>
                <x-slot:head>
                    <tr>
                        <th>العنوان</th>
                        <th>التاريخ</th>
                        <th>المكان / الرابط</th>
                        <th>رئيس الجلسة</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </x-slot:head>
                @forelse ($upcomingMeetings as $meeting)
                    <tr wire:key="upcoming-{{ $meeting->id }}">
                        <td>{{ $meeting->title }}</td>
                        <td>{{ $meeting->scheduled_at?->format('Y-m-d H:i') }}</td>
                        <td>
                            @if ($meeting->link)
                                <a class="ds-link" href="{{ $meeting->link }}" target="_blank" rel="noopener">رابط</a>
                            @elseif ($meeting->location)
                                {{ $meeting->location }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $meeting->chair?->name ?? '—' }}</td>
                        <td>{{ $statusLabels[$meeting->status] ?? $meeting->status }}</td>
                        <td>
                            <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('meetings.minutes', $meeting) }}">
                                <i class="fas fa-file-alt"></i> المحضر
                            </a>
                            <x-ds-action-icons
                                :show-view="auth()->user()->can('meetings.view')"
                                :show-edit="auth()->user()->can('meetings.update')"
                                :show-delete="auth()->user()->can('meetings.delete')"
                                :view-action="'openView('.$meeting->id.')'"
                                :edit-action="'openEdit('.$meeting->id.')'"
                                :delete-action="'delete('.$meeting->id.')'"
                                delete-confirm="حذف هذا الاجتماع؟"
                            />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="ds-text-muted ds-table-empty">لا توجد اجتماعات قادمة</td>
                    </tr>
                @endforelse
            </x-ds-table>
        </div>
        {{ $upcomingMeetings->links() }}
    </section>

    <section class="ds-section-spaced">
        <h2 class="ds-section-heading">الاجتماعات السابقة</h2>
        <div class="ds-table-wrap">
            <x-ds-table>
                <x-slot:head>
                    <tr>
                        <th>العنوان</th>
                        <th>التاريخ</th>
                        <th>المكان / الرابط</th>
                        <th>رئيس الجلسة</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </x-slot:head>
                @forelse ($pastMeetings as $meeting)
                    <tr wire:key="past-{{ $meeting->id }}">
                        <td>{{ $meeting->title }}</td>
                        <td>{{ $meeting->scheduled_at?->format('Y-m-d H:i') }}</td>
                        <td>
                            @if ($meeting->link)
                                <a class="ds-link" href="{{ $meeting->link }}" target="_blank" rel="noopener">رابط</a>
                            @elseif ($meeting->location)
                                {{ $meeting->location }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $meeting->chair?->name ?? '—' }}</td>
                        <td>{{ $statusLabels[$meeting->status] ?? $meeting->status }}</td>
                        <td>
                            <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('meetings.minutes', $meeting) }}">
                                <i class="fas fa-file-alt"></i> المحضر
                            </a>
                            <x-ds-action-icons
                                :show-view="auth()->user()->can('meetings.view')"
                                :show-edit="auth()->user()->can('meetings.update')"
                                :show-delete="auth()->user()->can('meetings.delete')"
                                :view-action="'openView('.$meeting->id.')'"
                                :edit-action="'openEdit('.$meeting->id.')'"
                                :delete-action="'delete('.$meeting->id.')'"
                                delete-confirm="حذف هذا الاجتماع؟"
                            />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="ds-text-muted ds-table-empty">لا توجد اجتماعات سابقة</td>
                    </tr>
                @endforelse
            </x-ds-table>
        </div>
        {{ $pastMeetings->links() }}
    </section>

    @if ($showModal)
        <div class="ds-modal-overlay" wire:click.self="closeModal">
            <div class="ds-modal ds-modal-lg" role="dialog">
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
                    <div class="ds-grid-2">
                        <x-ds-form-group label="العنوان" :error="$errors->first('title')">
                            <input type="text" class="ds-input" wire:model="title" @disabled($viewOnly)>
                        </x-ds-form-group>
                        <x-ds-form-group label="التاريخ والوقت" :error="$errors->first('scheduled_at')">
                            <input type="datetime-local" class="ds-input" wire:model="scheduled_at" @disabled($viewOnly)>
                        </x-ds-form-group>
                        <x-ds-form-group label="المكان" :error="$errors->first('location')">
                            <input type="text" class="ds-input" wire:model="location" @disabled($viewOnly)>
                        </x-ds-form-group>
                        <x-ds-form-group label="رابط الاجتماع" :error="$errors->first('link')">
                            <input type="url" class="ds-input" wire:model="link" @disabled($viewOnly)>
                        </x-ds-form-group>
                        <x-ds-form-group label="رئيس الجلسة">
                            <select class="ds-input" wire:model="chair_id" @disabled($viewOnly)>
                                <option value="">— بدون —</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="أمين السر">
                            <select class="ds-input" wire:model="secretary_id" @disabled($viewOnly)>
                                <option value="">— بدون —</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="الحالة" :error="$errors->first('status')">
                            <select class="ds-input" wire:model="status" @disabled($viewOnly)>
                                @foreach ($statusLabels as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                    </div>
                    <div class="ds-form-group">
                        <label class="ds-label">الحضور</label>
                        <div class="ds-permissions-grid">
                            @foreach ($users as $user)
                                <label class="ds-checkbox-label" wire:key="attendee-{{ $user->id }}">
                                    <input type="checkbox" value="{{ $user->id }}" wire:model="attendeeIds" @disabled($viewOnly)>
                                    <span>{{ $user->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="ds-modal-footer">
                    @if (! $viewOnly)
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="save">
                            <i class="fas fa-save"></i> حفظ
                        </button>
                    @endif
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="closeModal">إغلاق</button>
                </div>
            </div>
        </div>
    @endif
</div>
