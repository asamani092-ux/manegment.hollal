<div>
  @php
    $itemStatusLabels = ['open' => 'مفتوح', 'in_progress' => 'قيد التنفيذ', 'done' => 'منجز'];
  @endphp

  <div class="ds-page-toolbar">
    <div>
      <a href="{{ route('meetings.index') }}" class="ds-link">العودة للاجتماعات</a>
      <h1 class="ds-page-title">{{ $meeting->title }}</h1>
      <p class="ds-text-muted">{{ $meeting->scheduled_at?->format('Y-m-d H:i') }}</p>
      @if ($meeting->agenda)
        <p class="ds-text-muted">{{ $meeting->agenda }}</p>
      @endif
    </div>
    <div class="ds-toolbar-actions">
      @can('downloadPdf', $meeting)
        <a href="{{ route('meetings.minutes.pdf', $meeting) }}" class="ds-btn ds-btn-outline" target="_blank" rel="noopener">
          <svg class="ds-icon ds-icon-sm" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
          </svg>
          طباعة المحضر PDF
        </a>
        <button type="button" class="ds-btn ds-btn-outline" wire:click="sendMinutesByEmail" wire:loading.attr="disabled">
          <svg class="ds-icon ds-icon-sm" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
          </svg>
          إرسال بالإيميل للحضور
        </button>
      @endcan
      @can('update', $meeting)
        <button type="button" class="ds-btn ds-btn-primary" wire:click="openItemCreate">
          <i class="fas fa-plus" aria-hidden="true"></i> بند جديد
        </button>
      @endcan
    </div>
  </div>

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
      <div class="ds-task-card-actions">
        @if ($item->decision && ! $item->task_id && auth()->user()->can('update', $meeting) && auth()->user()->can('tasks.create'))
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
            <button type="button" class="ds-btn ds-btn-primary" wire:click="saveItem">
              <i class="fas fa-save" aria-hidden="true"></i> حفظ
            </button>
          @endif
          <button type="button" class="ds-btn ds-btn-outline" wire:click="closeItemModal">إغلاق</button>
        </div>
      </div>
    </div>
  @endif
</div>
