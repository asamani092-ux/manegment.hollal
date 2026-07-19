<x-ds-page>
    <x-ds-page-header title="محرر قوالب الخطط" />

    <section class="ds-section ds-filter-bar">
        @foreach ($templates as $option)
            <button type="button" class="ds-btn ds-btn-sm" wire:click="selectTemplate({{ $option->id }})">
                {{ $option->name }}
            </button>
        @endforeach
    </section>

    @if ($template)
        <section class="ds-section">
            <h2 class="ds-section-title">{{ $template->name }} — {{ $template->currentVersion?->version_label ?? 'بلا إصدار' }}</h2>

            @if ($template->needs_review)
                <p class="ds-badge ds-badge-warning">
                    القالب بانتظار جلسة المراجعة مع عبدالله — التوليد الحقيقي متوقف حتى الاعتماد
                </p>
                <x-ds-form-group label="ملاحظة المراجعة">
                    <input type="text" class="ds-input" wire:model="reviewNote">
                </x-ds-form-group>
                <button type="button" class="ds-btn ds-btn-primary" wire:click="markReviewed">اعتماد بعد المراجعة</button>
            @else
                <p class="ds-badge ds-badge-success">
                    معتمد — راجعه {{ $template->reviewer?->name ?? '—' }} بتاريخ {{ $template->reviewed_at?->format('Y-m-d') }}
                </p>
            @endif

            <button type="button" class="ds-btn" wire:click="openItemModal">إضافة مرحلة (مستوى 1)</button>
        </section>

        <x-ds-table>
            <x-slot:head>
                <tr>
                    <th>المستوى</th>
                    <th>العنوان</th>
                    <th>الدور</th>
                    <th>إزاحة البدء</th>
                    <th>المدة</th>
                    <th>الشاهد</th>
                    <th>النوع</th>
                    <th>إجراءات</th>
                </tr>
            </x-slot:head>
            @forelse ($items as $item)
                <tr wire:key="template-item-{{ $item->id }}">
                    <td class="ds-ltr-num">{{ $item->level }}</td>
                    <td style="padding-inline-start: {{ ($item->level - 1) * 16 }}px">{{ $item->title }}</td>
                    <td>{{ $item->role ?? '—' }}</td>
                    <td class="ds-ltr-num">+{{ $item->start_offset_days }}</td>
                    <td class="ds-ltr-num">{{ $item->duration_days }}</td>
                    <td>{{ $item->evidence_required ?? '—' }}</td>
                    <td>{{ $item->item_kind }}{{ $item->service_type ? ' — '.$item->service_type : '' }}</td>
                    <td>
                        @if ($item->level < 5)
                            <button type="button" class="ds-btn ds-btn-sm" wire:click="openItemModal({{ $item->id }})">
                                بند فرعي
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="ds-text-muted ds-table-empty">لا توجد بنود</td></tr>
            @endforelse
        </x-ds-table>
    @else
        <p class="ds-text-muted">لا توجد قوالب</p>
    @endif

    <x-ds-modal :show="$showItemModal" size="lg">
        <x-slot:header><h2>بند قالب جديد</h2></x-slot:header>

        <x-ds-form-group label="العنوان" :error="$errors->first('title')">
            <input type="text" class="ds-input" wire:model="title">
        </x-ds-form-group>

        <x-ds-form-group label="المكلّف بالدور" :error="$errors->first('role')">
            <input type="text" class="ds-input" wire:model="role">
        </x-ds-form-group>

        <x-ds-form-group label="إزاحة البدء (يوم +N)" :error="$errors->first('startOffsetDays')">
            <input type="number" class="ds-input" wire:model="startOffsetDays">
        </x-ds-form-group>

        <x-ds-form-group label="المدة (أيام)" :error="$errors->first('durationDays')">
            <input type="number" class="ds-input" wire:model="durationDays">
        </x-ds-form-group>

        <x-ds-form-group label="الشاهد المطلوب" :error="$errors->first('evidenceRequired')">
            <input type="text" class="ds-input" wire:model="evidenceRequired">
        </x-ds-form-group>

        <x-ds-form-group label="نوع البند" :error="$errors->first('itemKind')">
            <select class="ds-input" wire:model.live="itemKind">
                <option value="إلزامي">إلزامي</option>
                <option value="خدمة">مرتبط بخدمة</option>
            </select>
        </x-ds-form-group>

        <x-ds-form-group label="الخدمة المرتبطة" :error="$errors->first('serviceType')">
            <select class="ds-input" wire:model="serviceType">
                <option value="">—</option>
                <option value="تدريب">تدريب</option>
                <option value="زيارة">زيارة</option>
                <option value="استشارة">استشارة</option>
                <option value="قياس">قياس</option>
            </select>
        </x-ds-form-group>

        <x-ds-form-group label="ملاحظة إرشادية" :error="$errors->first('guidanceNote')">
            <textarea class="ds-input" wire:model="guidanceNote"></textarea>
        </x-ds-form-group>

        <x-slot:footer>
            <button type="button" class="ds-btn" wire:click="$set('showItemModal', false)">إلغاء</button>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="addItem">حفظ كإصدار جديد</button>
        </x-slot:footer>
    </x-ds-modal>
</x-ds-page>
