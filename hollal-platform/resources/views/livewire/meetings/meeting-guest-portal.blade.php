<div dir="rtl" class="ds-page-rtl ds-portal-steps">
    <h1 class="ds-page-title">{{ $meeting->title }}</h1>
    <p class="ds-text-muted ds-ltr-num">{{ hollal_dt($meeting->scheduled_at) }}</p>

    <section class="ds-section">
        <h2 class="ds-section-title">تفاصيل الاجتماع</h2>
        <x-ds-table>
            <tr><th>الرئيس</th><td>{{ $meeting->chair?->name ?? '—' }}</td></tr>
            @if ($meeting->location)
                <tr><th>المكان</th><td>{{ $meeting->location }}</td></tr>
            @endif
            @if ($meeting->link)
                <tr><th>رابط عن بُعد</th><td><a href="{{ $meeting->link }}" target="_blank" rel="noopener">{{ $meeting->link }}</a></td></tr>
            @endif
        </x-ds-table>
    </section>

    @if ($meeting->agenda)
        <section class="ds-section">
            <h2 class="ds-section-title">جدول الأعمال</h2>
            <p style="white-space:pre-line">{{ $meeting->agenda }}</p>
        </section>
    @endif

    @if ($meeting->items->isNotEmpty())
        <section class="ds-section">
            <h2 class="ds-section-title">بنود المحضر</h2>
            <x-ds-table>
                <x-slot:head><tr><th>البند</th><th>القرار</th></tr></x-slot:head>
                @foreach ($meeting->items as $item)
                    <tr wire:key="guest-item-{{ $item->id }}">
                        <td>{{ $item->topic }}</td>
                        <td>{{ $item->decision ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-ds-table>
        </section>
    @endif

    <section class="ds-section">
        <h2 class="ds-section-title">تأكيد الاطلاع</h2>

        @if ($guest->confirmed_at)
            <p class="ds-badge ds-badge-success">أكّدتم الاطلاع بتاريخ {{ hollal_dt($guest->confirmed_at) }}</p>
            @if ($guest->signature_image_path)
                <p><x-signature-cell :path="$guest->signature_image_path" /></p>
            @endif
        @else
            <x-ds-form-group label="صورة التوقيع (اختياري)" :error="$errors->first('signatureFile')">
                <input type="file" class="ds-input" wire:model="signatureFile" accept="image/*">
            </x-ds-form-group>
            <button type="button" class="ds-btn ds-btn-primary" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm,signatureFile">
                تأكيد الاطلاع
            </button>
        @endif
    </section>
</div>
