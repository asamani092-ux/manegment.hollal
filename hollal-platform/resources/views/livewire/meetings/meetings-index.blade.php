<div>
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
        <div class="ds-meeting-list">
            @forelse ($upcomingMeetings as $meeting)
                <article class="ds-meeting-card" wire:key="upcoming-{{ $meeting->id }}">
                    <div>
                        <h3 class="ds-task-card-title">{{ $meeting->title }}</h3>
                        <p class="ds-text-muted">{{ $meeting->scheduled_at?->format('Y-m-d H:i') }}</p>
                        @if ($meeting->agenda)
                            <p class="ds-text-muted">{{ \Illuminate\Support\Str::limit($meeting->agenda, 120) }}</p>
                        @endif
                    </div>
                    <div class="ds-toolbar-actions">
                        <a class="ds-btn ds-btn-primary ds-btn-sm" href="{{ route('meetings.minutes', $meeting) }}">المحضر</a>
                        <x-ds-action-icons
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
                <p class="ds-text-muted ds-table-empty">لا توجد اجتماعات قادمة</p>
            @endforelse
        </div>
        {{ $upcomingMeetings->links() }}
    </section>

    <section class="ds-section-spaced">
        <h2 class="ds-section-heading">الاجتماعات السابقة</h2>
        <div class="ds-meeting-list">
            @forelse ($pastMeetings as $meeting)
                <article class="ds-meeting-card" wire:key="past-{{ $meeting->id }}">
                    <div>
                        <h3 class="ds-task-card-title">{{ $meeting->title }}</h3>
                        <p class="ds-text-muted">{{ $meeting->scheduled_at?->format('Y-m-d H:i') }}</p>
                    </div>
                    <div class="ds-toolbar-actions">
                        <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('meetings.minutes', $meeting) }}">المحضر</a>
                        <x-ds-action-icons
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
                <p class="ds-text-muted ds-table-empty">لا توجد اجتماعات سابقة</p>
            @endforelse
        </div>
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
                    <x-ds-form-group label="جدول الأعمال" :error="$errors->first('agenda')">
                        <textarea class="ds-input" rows="3" wire:model="agenda" @disabled($viewOnly) placeholder="نقاط جدول الأعمال..."></textarea>
                    </x-ds-form-group>
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
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="save" wire:loading.attr="disabled">
                            <i class="fas fa-save" aria-hidden="true"></i> حفظ
                        </button>
                    @endif
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="closeModal">إغلاق</button>
                </div>
            </div>
        </div>
    @endif
</div>
