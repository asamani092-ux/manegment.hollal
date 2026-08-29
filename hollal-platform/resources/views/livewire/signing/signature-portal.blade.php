<div class="ds-login-card" dir="rtl">
    <h1 class="ds-section-title" style="margin-top:0;">{{ $title }}</h1>

    @if ($errorMessage)
        <p class="ds-error" role="alert">{{ $errorMessage }}</p>
    @elseif ($done)
        <p class="ds-badge ds-badge-success">تم تسجيل التوقيع بنجاح{{ $signerName ? ' — '.$signerName : '' }}</p>
        @if ($canDownload)
            <p style="margin-top:1rem;">
                <a class="ds-btn ds-btn-outline" href="{{ route('sign.portal.pdf', ['token' => $token]) }}" target="_blank" rel="noopener">تنزيل المستند</a>
            </p>
        @endif
    @elseif ($request)
        <ul class="ds-text-muted" style="padding-right:1.2rem; margin:0.75rem 0 1.25rem;">
            @foreach ($summary as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>

        @if ($canDownload)
            <p style="margin-bottom:1rem;">
                <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('sign.portal.pdf', ['token' => $token]) }}" target="_blank" rel="noopener">معاينة / تنزيل PDF</a>
            </p>
        @endif

        <x-ds-form-group label="اسم الموقّع" :error="$errors->first('signerName')">
            <input type="text" class="ds-input" wire:model="signerName" autocomplete="name">
        </x-ds-form-group>

        <x-ds-form-group label="الصفة (اختياري)" :error="$errors->first('signerPosition')">
            <input type="text" class="ds-input" wire:model="signerPosition">
        </x-ds-form-group>

        <x-ds-form-group label="التوقيع" :error="$errors->first('signaturePadData')">
            <x-signature-pad wire-model="signaturePadData" />
        </x-ds-form-group>

        <button type="button" class="ds-btn ds-btn-primary" wire:click="submitSignature" style="width:100%; margin-top:0.75rem;">
            تأكيد التوقيع
        </button>
    @else
        <p class="ds-error">الرابط غير صالح</p>
    @endif
</div>
