<x-ds-page class="ds-printable-minutes">
  @php
    $itemStatusLabels = ['open' => 'مفتوح', 'in_progress' => 'قيد التنفيذ', 'done' => 'منجز'];
    $myId = auth()->id();
    $isParticipant = $meeting->attendees->contains('id', $myId)
      || in_array($myId, [$meeting->chair_id, $meeting->secretary_id], true);
    $myConfirmedAtRaw = optional($meeting->attendees->firstWhere('id', $myId))->pivot->confirmed_at ?? null;
    $myConfirmedAt = $myConfirmedAtRaw ? \Illuminate\Support\Carbon::parse($myConfirmedAtRaw) : null;
  @endphp

  <div class="ds-page-toolbar ds-no-print">
    <div>
      <a href="{{ route('meetings.index') }}" class="ds-link">العودة للاجتماعات</a>
      <h1 class="ds-page-title">{{ $meeting->title }}</h1>
      <p class="ds-text-muted">{{ $meeting->scheduled_at?->format('Y-m-d H:i') }}</p>
      @if ($meeting->isApproved())
        <span class="ds-badge ds-badge-success">محضر معتمد — {{ $meeting->approved_at?->format('Y-m-d') }}</span>
      @endif
    </div>
    <div class="ds-toolbar-actions">
      @if (! $meeting->isApproved())
        @can('update', $meeting)
          <button type="button" class="ds-btn ds-btn-teal" wire:click="openApproveModal">
            <i class="fas fa-check-circle" aria-hidden="true"></i> اعتماد المحضر
          </button>
        @endcan
        @if ($meeting->hasEnded() && $isParticipant)
          @if ($myConfirmedAt)
            <span class="ds-badge ds-badge-success">أكّدتَ الاطلاع — {{ $myConfirmedAt->format('Y-m-d H:i') }}</span>
          @else
            <button type="button" class="ds-btn ds-btn-outline" wire:click="confirmMyAttendance">اطّلعت على المحضر وأؤكّد</button>
          @endif
        @endif
      @endif
      @can('downloadPdf', $meeting)
        <button type="button" class="ds-btn ds-btn-outline" onclick="window.print()">
          <i class="fas fa-print" aria-hidden="true"></i> طباعة القالب
        </button>
        @if ($meeting->signed_document_id)
          <a class="ds-btn ds-btn-outline" href="{{ route('meetings.minutes.signed', $meeting) }}">
            <i class="fas fa-file-signature" aria-hidden="true"></i> النسخة الموقعة
          </a>
        @endif
      @endcan
      @can('update', $meeting)
        <button type="button" class="ds-btn ds-btn-outline" wire:click="openSignedUploadModal">
          <i class="fas fa-upload" aria-hidden="true"></i> رفع نسخة موقعة
        </button>
        @if (! $meeting->isApproved())
          <button type="button" class="ds-btn ds-btn-primary" wire:click="openItemCreate">
            <i class="fas fa-plus" aria-hidden="true"></i> بند جديد
          </button>
        @endif
      @endcan
    </div>
  </div>

  {{-- Printable 5-zone template (screen + print) --}}
  <div class="ds-minutes-print-template">
    <section class="ds-minutes-zone">
      <div class="ds-filters-row">
        <strong>منصة حلّل</strong>
        <span class="ds-text-muted ds-ltr-num">اجتماع: {{ $meeting->scheduled_at?->format('Y-m-d') }} · طباعة: {{ now()->format('Y-m-d H:i') }}</span>
      </div>
      <h2 class="ds-section-heading">محضر: {{ $meeting->title }}</h2>
    </section>

    <section class="ds-minutes-zone">
      <h3 class="ds-section-heading">تفاصيل الاجتماع</h3>
      <x-ds-table>
        <tr><th>العنوان</th><td>{{ $meeting->title }}</td></tr>
        <tr><th>الوقت</th><td class="ds-ltr-num">{{ $meeting->scheduled_at?->format('Y-m-d H:i') }}</td></tr>
        <tr><th>المكان</th><td>{{ $meeting->link ? 'عن بعد' : ($meeting->location ?: '—') }}</td></tr>
        <tr><th>الرئيس</th><td>{{ $meeting->chair?->name ?? '—' }}</td></tr>
        <tr><th>محرر الاجتماع</th><td>{{ $meeting->secretary?->name ?? '—' }}</td></tr>
        <tr><th>الحضور</th><td>{{ $meeting->attendees->pluck('name')->implode('، ') ?: '—' }}</td></tr>
      </x-ds-table>
    </section>

    <section class="ds-minutes-zone">
      <h3 class="ds-section-heading">جدول الأعمال</h3>
      <x-ds-table>
        <x-slot:head><tr><th>#</th><th>البند</th></tr></x-slot:head>
        @php $agendaLines = collect(preg_split('/\r\n|\r|\n/', (string) $meeting->agenda))->map(fn ($l) => trim($l))->filter()->values(); @endphp
        @forelse ($agendaLines as $i => $line)
          <tr><td>{{ $i + 1 }}</td><td>{{ $line }}</td></tr>
        @empty
          <tr><td colspan="2">—</td></tr>
        @endforelse
      </x-ds-table>
    </section>

    <section class="ds-minutes-zone">
      <h3 class="ds-section-heading">بنود المحضر</h3>
      <x-ds-table>
        <x-slot:head><tr><th>البند</th><th>القرار أو التوصية</th></tr></x-slot:head>
        @forelse ($items as $item)
          <tr wire:key="print-item-{{ $item->id }}">
            <td>{{ $item->topic }}</td>
            <td>{{ $item->decision ?? '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="2">لا توجد بنود</td></tr>
        @endforelse
      </x-ds-table>
    </section>

    <section class="ds-minutes-zone">
      <h3 class="ds-section-heading">الحضور والتوقيع</h3>
      <x-ds-table>
        <x-slot:head><tr><th>الاسم</th><th>التوقيع</th></tr></x-slot:head>
        @forelse ($meeting->attendees as $attendee)
          <tr wire:key="sig-{{ $attendee->id }}">
            <td>{{ $attendee->name }}</td>
            <td style="min-height:2rem">
              <x-signature-cell :path="$attendee->pivot->signature_image_path ?? null" :text="$attendee->pivot->signature_text ?? null" />
            </td>
          </tr>
        @empty
          <tr><td colspan="2">—</td></tr>
        @endforelse
      </x-ds-table>
      @if ($meeting->minutes_missing_signatures_reason)
        <p class="ds-text-muted">سبب نقص التوقيع: {{ $meeting->minutes_missing_signatures_reason }}</p>
      @endif
    </section>

    @if ($meeting->guests->isNotEmpty())
      <section class="ds-minutes-zone">
        <h3 class="ds-section-heading">الضيوف الخارجيون</h3>
        <x-ds-table>
          <x-slot:head><tr><th>الاسم</th><th>البريد</th><th>التأكيد</th><th>التوقيع</th></tr></x-slot:head>
          @foreach ($meeting->guests as $guest)
            <tr wire:key="guest-sig-{{ $guest->id }}">
              <td>{{ $guest->name }}</td>
              <td class="ds-ltr-num">{{ $guest->email }}</td>
              <td>{{ $guest->confirmed_at?->format('Y-m-d H:i') ?? '—' }}</td>
              <td style="min-height:2rem">
                <x-signature-cell :path="$guest->signature_image_path" />
              </td>
            </tr>
          @endforeach
        </x-ds-table>
      </section>
    @endif
  </div>

  <div class="ds-no-print ds-section-spaced">
    <h2 class="ds-section-heading">تحرير البنود</h2>
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
          <span>الحالة: {{ $itemStatusLabels[$item->status] ?? $item->status }}</span>
        </div>
        <div class="ds-task-card-actions">
          @if ($item->decision && ! $item->task_id && $meeting->isApproved() && auth()->user()->can('update', $meeting) && auth()->user()->can('esnad.tasks.create'))
            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="convertToTask({{ $item->id }})">تحويل إلى مهمة</button>
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
      <p class="ds-text-muted">لا توجد بنود</p>
    @endforelse
  </div>

  @if ($showApproveModal)
    <div class="ds-modal-overlay ds-no-print" wire:click.self="$set('showApproveModal', false)" style="z-index:1300">
      <div class="ds-modal" role="dialog" dir="rtl" wire:click.stop>
        <div class="ds-modal-header">
          <h3>اعتماد المحضر</h3>
          <button type="button" class="ds-modal-close" wire:click="$set('showApproveModal', false)">&times;</button>
        </div>
        <div class="ds-modal-body">
          <p>عدد الحضور بلا توقيع: {{ $unsignedCount }}</p>
          <label class="ds-label">
            <input type="checkbox" wire:model.live="allowMissingSignatures"> السماح بالاعتماد مع نقص توقيع
          </label>
          @if ($allowMissingSignatures)
            <x-ds-form-group label="سبب النقص" :error="$errors->first('missingSignaturesReason')">
              <input type="text" class="ds-input" wire:model="missingSignaturesReason" placeholder="غائب عن الاجتماع / لا يلزم توقيع…">
            </x-ds-form-group>
          @endif
        </div>
        <div class="ds-modal-footer">
          <button type="button" class="ds-btn ds-btn-primary" wire:click="approveMinutes">تأكيد الاعتماد</button>
          <button type="button" class="ds-btn ds-btn-outline" wire:click="$set('showApproveModal', false)">إلغاء</button>
        </div>
      </div>
    </div>
  @endif

  @if ($showItemModal)
    <div class="ds-modal-overlay ds-no-print" wire:click.self="closeItemModal">
      <div class="ds-modal ds-modal-lg" role="dialog" dir="rtl">
        <div class="ds-modal-header">
          <h3>{{ $itemViewOnly ? 'عرض بند' : ($itemId ? 'تعديل بند' : 'بند جديد') }}</h3>
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
            <button type="button" class="ds-btn ds-btn-primary" wire:click="saveItem">حفظ</button>
          @endif
          <button type="button" class="ds-btn ds-btn-outline" wire:click="closeItemModal">إغلاق</button>
        </div>
      </div>
    </div>
  @endif

  @if ($showSignatureModal)
    <div class="ds-modal-overlay ds-no-print" wire:click.self="$set('showSignatureModal', false)" style="z-index:1300">
      <div class="ds-modal" role="dialog" dir="rtl" wire:click.stop>
        <div class="ds-modal-header">
          <h3>حفظ توقيعك الإلكتروني</h3>
          <button type="button" class="ds-modal-close" wire:click="$set('showSignatureModal', false)">&times;</button>
        </div>
        <div class="ds-modal-body">
          <p class="ds-text-muted">لم تُسجّل صورة توقيعك بعد. ارفعها لتُحفظ في ملفك الشخصي وتُدرج بجانب اسمك في محاضر الاجتماعات.</p>
          <x-ds-form-group label="صورة التوقيع" :error="$errors->first('signatureFile')">
            <input type="file" class="ds-input" wire:model="signatureFile" accept="image/*">
          </x-ds-form-group>
          <div wire:loading wire:target="signatureFile" class="ds-text-muted">جارٍ التحميل…</div>
        </div>
        <div class="ds-modal-footer">
          <button type="button" class="ds-btn ds-btn-primary" wire:click="saveSignatureAndConfirm">حفظ التوقيع والتأكيد</button>
          <button type="button" class="ds-btn ds-btn-outline" wire:click="$set('showSignatureModal', false)">إلغاء</button>
        </div>
      </div>
    </div>
  @endif

  @if ($showSignedUploadModal)
    <div class="ds-modal-overlay ds-no-print" wire:click.self="$set('showSignedUploadModal', false)" style="z-index:1300">
      <div class="ds-modal" role="dialog" dir="rtl" wire:click.stop>
        <div class="ds-modal-header">
          <h3>رفع نسخة موقعة يدويًا</h3>
          <button type="button" class="ds-modal-close" wire:click="$set('showSignedUploadModal', false)">&times;</button>
        </div>
        <div class="ds-modal-body">
          <p class="ds-text-muted">ارفع ملف PDF لنسخة المحضر الموقعة يدويًا (طباعة + توقيع) لربطها بالأرشيف إلى جانب النسخة الإلكترونية.</p>
          <x-ds-form-group label="ملف PDF" :error="$errors->first('signedPdfFile')">
            <input type="file" class="ds-input" wire:model="signedPdfFile" accept="application/pdf">
          </x-ds-form-group>
          <div wire:loading wire:target="signedPdfFile" class="ds-text-muted">جارٍ التحميل…</div>
        </div>
        <div class="ds-modal-footer">
          <button type="button" class="ds-btn ds-btn-primary" wire:click="uploadSignedMinutes">رفع وربط بالأرشيف</button>
          <button type="button" class="ds-btn ds-btn-outline" wire:click="$set('showSignedUploadModal', false)">إلغاء</button>
        </div>
      </div>
    </div>
  @endif
</x-ds-page>
