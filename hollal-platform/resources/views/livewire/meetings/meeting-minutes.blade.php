<x-ds-page class="ds-printable-minutes">
  @php
    $itemStatusLabels = ['open' => 'مفتوح', 'in_progress' => 'قيد التنفيذ', 'done' => 'منجز'];
  @endphp

  <div class="ds-page-toolbar ds-no-print">
    <div>
      <a href="{{ route('meetings.index') }}" class="ds-link">العودة للاجتماعات</a>
      <h1 class="ds-page-title">{{ $meeting->title }}</h1>
      <p class="ds-text-muted ds-ltr-num">{{ $meeting->scheduled_at?->format('Y-m-d H:i') }}</p>
      @if ($meeting->location)
        <p class="ds-text-muted">المكان: {{ $meeting->location }}</p>
      @endif
      @if ($meeting->link)
        <p class="ds-text-muted">رابط عن بُعد: <a class="ds-link" href="{{ $meeting->link }}" target="_blank" rel="noopener">{{ $meeting->link }}</a></p>
      @endif
      @if ($meeting->isApproved())
        <span class="ds-badge ds-badge-success">محضر معتمد — {{ $meeting->approved_at?->format('Y-m-d') }}</span>
      @endif
    </div>
    <div class="ds-toolbar-actions">
      @if (! $meeting->isApproved())
        @can('update', $meeting)
          <button type="button" class="ds-btn ds-btn-teal" wire:click="approveMinutes" wire:loading.attr="disabled" wire:confirm="اعتماد المحضر؟ لن يُسمح بالتعديل المباشر بعد الاعتماد.">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <span wire:loading.remove wire:target="approveMinutes">اعتماد المحضر</span>
            <span wire:loading wire:target="approveMinutes">جاري الاعتماد…</span>
          </button>
        @endcan
      @endif
      @can('downloadPdf', $meeting)
        <button type="button" class="ds-btn ds-btn-outline" onclick="window.print()">
          <i class="fas fa-print" aria-hidden="true"></i>
          طباعة
        </button>
        <a href="{{ route('meetings.minutes.pdf', $meeting) }}" class="ds-btn ds-btn-outline" target="_blank" rel="noopener">
          <svg class="ds-icon ds-icon-sm" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
          </svg>
          حفظ PDF
        </a>
        <button type="button" class="ds-btn ds-btn-outline" wire:click="sendMinutesByEmail" wire:loading.attr="disabled">
          <svg class="ds-icon ds-icon-sm" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
          </svg>
          إرسال بالإيميل للحضور
        </button>
      @endcan
      @can('update', $meeting)
        @if (! $meeting->isApproved())
          <button type="button" class="ds-btn ds-btn-primary" wire:click="openItemCreate">
            <i class="fas fa-plus" aria-hidden="true"></i> بند جديد
          </button>
        @endif
      @endcan
    </div>
  </div>

  <p class="ds-minutes-approval-help ds-no-print">
    مسار الاعتماد الحالي: بعد اكتمال البنود يضغط رئيس الجلسة أو السكرتير (بصلاحية تعديل الاجتماع) زر «اعتماد المحضر». بعد الاعتماد يُجمَّد التعديل المباشر ويُحفظ PDF في المستندات، ويمكن تحويل القرارات إلى مهام في إسناد.
  </p>

  <div class="ds-minutes-print-block ds-minutes-agenda">
    <h2>جدول الأعمال</h2>
    @if ($meeting->agenda)
      <p style="white-space:pre-wrap">{{ $meeting->agenda }}</p>
    @else
      <p class="ds-text-muted">لم يُحدَّد جدول أعمال بعد — يمكن إضافته من شاشة تعديل الاجتماع.</p>
    @endif
  </div>

  <div class="ds-minutes-print-block">
    <h2 class="ds-section-heading">تفاصيل الاجتماع</h2>
    <div class="ds-detail-row"><span class="ds-detail-label">العنوان:</span> {{ $meeting->title }}</div>
    <div class="ds-detail-row"><span class="ds-detail-label">الوقت:</span> <span class="ds-ltr-num">{{ $meeting->scheduled_at?->format('Y-m-d H:i') ?? '—' }}</span></div>
    <div class="ds-detail-row"><span class="ds-detail-label">المكان:</span> {{ $meeting->location ?? '—' }}</div>
    <div class="ds-detail-row"><span class="ds-detail-label">رابط عن بُعد:</span> {{ $meeting->link ?? '—' }}</div>
    <div class="ds-detail-row"><span class="ds-detail-label">الرئيس:</span> {{ $meeting->chair?->name ?? '—' }}</div>
    <div class="ds-detail-row"><span class="ds-detail-label">السكرتير:</span> {{ $meeting->secretary?->name ?? '—' }}</div>
  </div>

  <div class="ds-minutes-print-block">
    <h2 class="ds-section-heading">بنود المحضر</h2>
    @forelse ($items as $item)
      <article class="ds-minute-item-card" wire:key="item-{{ $item->id }}">
        <h3 class="ds-task-card-title">{{ $item->topic }}</h3>
        @if ($item->discussion_summary)
          <p><strong>النقاش:</strong> {{ $item->discussion_summary }}</p>
        @endif
        @if ($item->decision)
          <p><strong>القرار:</strong> {{ $item->decision }}</p>
        @endif
        <div class="ds-task-card-meta">
          <span>المسؤول: {{ $item->responsible?->name ?? '—' }}</span>
          <span>الاستحقاق: {{ $item->due_date?->format('Y-m-d') ?? '—' }}</span>
          <span>الحالة: {{ $itemStatusLabels[$item->status] ?? $item->status }}</span>
        </div>
        <div class="ds-task-card-actions ds-no-print">
          @if ($item->decision && ! $item->task_id && $meeting->isApproved() && auth()->user()->can('update', $meeting) && auth()->user()->can('esnad.tasks.create'))
            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="convertToTask({{ $item->id }})">
              تحويل إلى مهمة
            </button>
          @endif
          @if ($item->task_id)
            <span class="ds-badge ds-badge-success">مرتبط بمهمة: {{ $item->task?->title }}</span>
          @endif
          <x-ds-action-icons
            :show-view="true"
            :show-edit="auth()->user()->can('update', $meeting)"
            :show-delete="auth()->user()->can('update', $meeting)"
            :view-action="'openItemView('.$item->id.')'"
            :edit-action="'openItemEdit('.$item->id.')'"
            :delete-action="'deleteItem('.$item->id.')'"
            delete-confirm="حذف هذا البند؟"
          />
        </div>
      </article>
    @empty
      <p class="ds-text-muted ds-table-empty">لا توجد بنود في المحضر — أضف بنداً لتوثيق النقاش والقرارات.</p>
    @endforelse
  </div>

  <div class="ds-minutes-print-block">
    <h2 class="ds-section-heading">الحضور والتوقيع</h2>
    <table class="ds-attendance-sign-table">
      <thead>
        <tr>
          <th>الاسم</th>
          <th>المسمى الوظيفي</th>
          <th>التوقيع</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($attendees as $attendee)
          <tr wire:key="att-{{ $attendee->id }}">
            <td>{{ $attendee->name }}</td>
            <td>{{ $attendee->profile?->job_title ?? '—' }}</td>
            <td><span class="ds-sig-line"></span></td>
          </tr>
        @empty
          <tr>
            <td colspan="3" class="ds-text-muted">لا يوجد حضور مسجّل</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if ($showItemModal)
    <div class="ds-modal-overlay" wire:click.self="closeItemModal">
      <div class="ds-modal ds-modal-lg" role="dialog" dir="rtl">
        <div class="ds-modal-header">
          <h3>
            @if ($itemViewOnly)
              عرض بند
            @elseif ($itemId)
              تعديل بند
            @else
              بند جديد
            @endif
          </h3>
          <button type="button" class="ds-modal-close" wire:click="closeItemModal">&times;</button>
        </div>
        <div class="ds-modal-body">
          @if ($meeting->agenda)
            <div class="ds-minutes-agenda" style="margin-bottom:1rem">
              <h2>مرجع جدول الأعمال</h2>
              <p style="white-space:pre-wrap;margin:0">{{ $meeting->agenda }}</p>
            </div>
          @endif
          <x-ds-form-group label="الموضوع" :error="$errors->first('topic')">
            <input type="text" class="ds-input" wire:model="topic" @disabled($itemViewOnly)>
          </x-ds-form-group>
          <x-ds-form-group label="ملخص النقاش" :error="$errors->first('discussion_summary')">
            <textarea class="ds-input" rows="3" wire:model="discussion_summary" @disabled($itemViewOnly)></textarea>
          </x-ds-form-group>
          <x-ds-form-group label="القرار" :error="$errors->first('decision')">
            <textarea class="ds-input" rows="2" wire:model="decision" @disabled($itemViewOnly)></textarea>
          </x-ds-form-group>
          <div class="ds-grid-2">
            <x-ds-form-group label="المسؤول">
              <select class="ds-input" wire:model="responsible_id" @disabled($itemViewOnly)>
                <option value="">— بدون —</option>
                @foreach ($users as $user)
                  <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
              </select>
            </x-ds-form-group>
            <x-ds-form-group label="تاريخ الاستحقاق" :error="$errors->first('due_date')">
              <input type="date" class="ds-input" wire:model="due_date" @disabled($itemViewOnly)>
            </x-ds-form-group>
          </div>
        </div>
        <div class="ds-modal-footer">
          @if (! $itemViewOnly)
            <button type="button" class="ds-btn ds-btn-primary" wire:click="saveItem" wire:loading.attr="disabled" wire:target="saveItem">
              <span wire:loading.remove wire:target="saveItem"><i class="fas fa-save" aria-hidden="true"></i> حفظ</span>
              <span wire:loading wire:target="saveItem" class="ds-btn-loading">جاري الحفظ…</span>
            </button>
          @endif
          <button type="button" class="ds-btn ds-btn-outline" wire:click="closeItemModal">إغلاق</button>
        </div>
      </div>
    </div>
  @endif
</x-ds-page>
