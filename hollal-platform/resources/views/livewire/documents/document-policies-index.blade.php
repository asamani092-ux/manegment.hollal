<x-ds-page>
    <x-ds-page-header title="السياسات وملف المهام الرسمي" />

    <section class="ds-section">
        <h2 class="ds-section-title">إضافة سياسة / لائحة</h2>
        <x-ds-form-group label="العنوان" :error="$errors->first('policyTitle')">
            <input type="text" class="ds-input" wire:model="policyTitle">
        </x-ds-form-group>
        <x-ds-form-group label="تاريخ المراجعة القادم" :error="$errors->first('reviewDate')">
            <input type="date" class="ds-input" wire:model="reviewDate">
        </x-ds-form-group>
        <x-ds-form-group label="الملف" :error="$errors->first('policyFile')">
            <input type="file" class="ds-input" wire:model="policyFile">
        </x-ds-form-group>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="savePolicy">حفظ السياسة</button>
    </section>

    <section class="ds-section">
        <h2 class="ds-section-title">السياسات المسجّلة</h2>
        <p class="ds-text-muted">تظهر أيضاً في المستودع ضمن تصنيف «سياسة» حسب مستوى السرية.</p>
        <x-ds-table>
            <x-slot:head>
                <tr><th>العنوان</th><th>مراجعة</th><th>تنبيه أُرسل</th><th>إجراءات</th></tr>
            </x-slot:head>
            @forelse ($policies as $policy)
                <tr wire:key="policy-{{ $policy->id }}">
                    <td>{{ $policy->title }}</td>
                    <td dir="ltr">{{ $policy->review_date?->format('Y-m-d') ?? '—' }}</td>
                    <td dir="ltr">{{ $policy->review_alert_sent_at?->format('Y-m-d') ?? '—' }}</td>
                    <td>
                        @can('download', $policy)
                            <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('documents.files.download', ['document' => $policy, 'inline' => 1]) }}" target="_blank" rel="noopener">معاينة</a>
                            <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('documents.files.download', $policy) }}">تنزيل</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="ds-text-muted ds-table-empty">لا توجد سياسات</td></tr>
            @endforelse
        </x-ds-table>
        {{ $policies->links() }}
    </section>

    <section class="ds-section">
        <h2 class="ds-section-title">ملف المهام الرسمي للعاملين</h2>
        <p class="ds-text-muted">
            ملف واحد معتمد للمنصة يظهر لجميع الموظفين من الرابط في الصفحة الرئيسية.
            ليس ملفاً لكل إدارة — التحديث سنوياً أو عند الحاجة بإصدار تراكمي.
        </p>
        <x-ds-form-group label="رفع إصدار جديد (PDF)" :error="$errors->first('dutiesFile')">
            <input type="file" class="ds-input" wire:model="dutiesFile" accept="application/pdf">
        </x-ds-form-group>
        <button type="button" class="ds-btn ds-btn-primary" wire:click="publishDuties">نشر الإصدار</button>

        <x-ds-table>
            <x-slot:head>
                <tr><th>الإصدار</th><th>تاريخ النشر</th><th></th></tr>
            </x-slot:head>
            @forelse ($dutiesVersions as $version)
                <tr wire:key="duties-{{ $version->id }}">
                    <td class="ds-ltr-num">{{ $version->version }}</td>
                    <td dir="ltr">{{ hollal_dt($version->published_at) }}</td>
                    <td>
                        @if ($loop->first)
                            <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('duties.download') }}">تنزيل الأحدث</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="ds-text-muted ds-table-empty">لم يُنشر ملف بعد</td></tr>
            @endforelse
        </x-ds-table>
    </section>
</x-ds-page>
