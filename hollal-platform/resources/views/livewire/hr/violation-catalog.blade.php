<div>
    <h2>جدول المخالفات</h2>
    @if ($items->isEmpty() && $drafts->isEmpty())
        <p class="ds-text-muted">لا توجد مخالفات بعد. أضف أول بند من النموذج.</p>
    @endif
    <table class="ds-table">
        <thead>
            <tr>
                <th>الرمز</th>
                <th>الاسم</th>
                <th>التصنيف</th>
                <th>الحالة</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr wire:key="cat-{{ $item->id }}">
                    <td>{{ $item->code }}</td>
                    <td>{{ $item->name_ar }}</td>
                    <td>{{ $item->attributes['category'] ?? '—' }}</td>
                    <td>{{ \App\Support\ArabicStatus::label($item->status) }}</td>
                    <td>@if ($canEdit)<button type="button" class="ds-btn ds-btn-sm" wire:click="editItem({{ $item->id }})">تعديل</button>@endif</td>
                </tr>
            @endforeach
            @foreach ($drafts as $item)
                <tr wire:key="draft-{{ $item->id }}">
                    <td>{{ $item->code }}</td>
                    <td>{{ $item->name_ar }}</td>
                    <td>{{ $item->attributes['category'] ?? '—' }}</td>
                    <td>{{ \App\Support\ArabicStatus::label($item->status) }}</td>
                    <td>@if ($canEdit)<button type="button" class="ds-btn ds-btn-sm" wire:click="editItem({{ $item->id }})">تعديل</button>@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($canEdit)
        <div class="ds-card" style="margin-top:1rem;padding:0.75rem">
            <h3>{{ $editingId ? 'تعديل مخالفة' : 'مخالفة جديدة' }}</h3>
            @if ($editingReferenced)
                <p class="ds-text-muted">هذه المخالفة مستخدمة. الحفظ ينشئ نسخة جديدة.</p>
            @endif
            <label>الاسم
                <input class="ds-input" wire:model="nameAr">
            </label>
            @error('nameAr') <p style="color:#b42318">{{ $message }}</p> @enderror
            <label>التصنيف
                <select class="ds-input" wire:model="category">
                    @foreach ($catalog::CATEGORIES as $label)
                        <option value="{{ $label }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>الوصف
                <textarea class="ds-input" wire:model="description"></textarea>
            </label>
            <h4>الجزاءات</h4>
            @error('penalties') <p style="color:#b42318">{{ $message }}</p> @enderror
            @foreach ($catalog::OCCURRENCES as $index => $occurrence)
                <div style="display:flex;gap:0.4rem;flex-wrap:wrap;align-items:center;margin-bottom:0.4rem">
                    <span>{{ $occurrence }}</span>
                    <select class="ds-input" wire:model.live="penalties.{{ $index }}.type">
                        @foreach ($catalog::PENALTY_TYPES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @if (isset($catalog::VALUE_UNITS[$penalties[$index]['type'] ?? '']))
                        <input class="ds-input" style="max-width:6rem" inputmode="decimal" wire:model="penalties.{{ $index }}.value">
                        <span>{{ $catalog::VALUE_UNITS[$penalties[$index]['type']] }}</span>
                    @endif
                </div>
            @endforeach
            <label>الاكتشاف الآلي
                <select class="ds-input" wire:model.live="autoDetect">
                    @foreach ($catalog::DETECTION as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            @if ($autoDetect === 'late')
                <label>الدقائق
                    <input class="ds-input" inputmode="numeric" wire:model="lateMinutes">
                </label>
                @error('lateMinutes') <p style="color:#b42318">{{ $message }}</p> @enderror
            @endif
            <label>تاريخ السريان
                @php
                    $chunks = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $effectiveFrom, $match) ? $match : null;
                @endphp
                <span style="display:flex;gap:0.35rem;flex-wrap:wrap">
                    <select class="ds-input" wire:change="setEffectivePart('day', $event.target.value)">
                        <option value="">اليوم</option>
                        @for ($d = 1; $d <= 31; $d++)
                            <option value="{{ sprintf('%02d', $d) }}" @selected(($chunks[3] ?? '') === sprintf('%02d', $d))>{{ $d }}</option>
                        @endfor
                    </select>
                    <select class="ds-input" wire:change="setEffectivePart('month', $event.target.value)">
                        <option value="">الشهر</option>
                        @foreach (\App\Support\ArabicStatus::MONTHS as $number => $name)
                            <option value="{{ sprintf('%02d', $number) }}" @selected(($chunks[2] ?? '') === sprintf('%02d', $number))>{{ $name }}</option>
                        @endforeach
                    </select>
                    <select class="ds-input" wire:change="setEffectivePart('year', $event.target.value)">
                        <option value="">السنة</option>
                        @for ($y = (int) now()->year - 1; $y <= (int) now()->year + 2; $y++)
                            <option value="{{ $y }}" @selected(($chunks[1] ?? '') === (string) $y)>{{ $y }}</option>
                        @endfor
                    </select>
                </span>
            </label>
            @if ($baselineRows !== [])
                <p>أساس الوزارة:
                    @foreach ($baselineRows as $row)
                        {{ $catalog->labelPenalty((string) ($row['type'] ?? '')) }}
                        @if (isset($catalog::VALUE_UNITS[$row['type'] ?? '']))
                            {{ $row['value'] ?? '' }} {{ $catalog::VALUE_UNITS[$row['type']] }}
                        @endif
                    @endforeach
                </p>
            @endif
            @if ($editingReferenced)
                <label>سبب النسخة الجديدة
                    <input class="ds-input" wire:model="revisionReason">
                </label>
                @error('revisionReason') <p style="color:#b42318">{{ $message }}</p> @enderror
            @endif
            <div style="display:flex;gap:0.4rem;margin-top:0.6rem">
                <button type="button" class="ds-btn ds-btn-primary" wire:click="save">حفظ</button>
                <button type="button" class="ds-btn" wire:click="saveDraft">حفظ كمسودة</button>
            </div>
        </div>

        <div class="ds-card" style="margin-top:1rem;padding:0.75rem">
            <h3>ملف إكسل</h3>
            <button type="button" class="ds-btn" wire:click="downloadTemplate">تحميل نموذج Excel</button>
            <label class="ds-btn ds-btn-outline">
                رفع الملف
                <input type="file" wire:model="importFile" accept=".xlsx,.xls" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)">
            </label>
            <button type="button" class="ds-btn" wire:click="previewExcel">معاينة</button>
            @error('importFile') <p style="color:#b42318">{{ $message }}</p> @enderror
            @if ($importRows !== [])
                <table class="ds-table">
                    <thead>
                        <tr><th>الاسم</th><th>الحالة</th><th>السبب</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($importRows as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['state'] }}</td>
                                <td>{{ $row['reason'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <button type="button" class="ds-btn ds-btn-primary" wire:click="confirmExcel">اعتماد</button>
            @endif
        </div>
    @endif
</div>
