<div class="ds-guest-card ds-card">
    <div class="ds-card-header">
        <h1 class="ds-page-title">تفاصيل الشراكة</h1>
        <p class="ds-text-muted">عرض آمن عبر الرابط السحري — لا يتطلب تسجيل دخول</p>
    </div>
    <div class="ds-card-body">
        @php
            $statusLabels = ['pending_form' => 'بانتظار النموذج', 'negotiation' => 'تفاوض', 'active' => 'نشطة', 'completed' => 'مكتملة'];
        @endphp
        <div class="ds-detail-list">
            <div class="ds-detail-row">
                <span class="ds-detail-label">الجهة:</span>
                <span class="ds-detail-value">{{ $partnership->entity_name }}</span>
            </div>
            @if ($partnership->contact_person)
                <div class="ds-detail-row">
                    <span class="ds-detail-label">مسؤول التواصل:</span>
                    <span class="ds-detail-value">{{ $partnership->contact_person }}</span>
                </div>
            @endif
            @if ($partnership->contact_phone)
                <div class="ds-detail-row">
                    <span class="ds-detail-label">الجوال:</span>
                    <span class="ds-detail-value">{{ $partnership->contact_phone }}</span>
                </div>
            @endif
            @if ($partnership->project)
                <div class="ds-detail-row">
                    <span class="ds-detail-label">المشروع:</span>
                    <span class="ds-detail-value">{{ $partnership->project->name }}</span>
                </div>
            @endif
            <div class="ds-detail-row">
                <span class="ds-detail-label">الحالة:</span>
                <span class="ds-detail-value">{{ $statusLabels[$partnership->status] ?? $partnership->status }}</span>
            </div>
            @if ($partnership->type_quantity)
                <div class="ds-detail-row">
                    <span class="ds-detail-label">النوع / الكمية:</span>
                    <span class="ds-detail-value">{{ $partnership->type_quantity }}</span>
                </div>
            @endif
            @if ($partnership->pricing_amount !== null)
                <div class="ds-detail-row">
                    <span class="ds-detail-label">المبلغ:</span>
                    <span class="ds-detail-value">{{ number_format((float) $partnership->pricing_amount, 2) }}</span>
                </div>
            @endif
            @if ($partnership->halal_commitments)
                <div class="ds-detail-row">
                    <span class="ds-detail-label">التزامات حلال:</span>
                    <span class="ds-detail-value">{{ $partnership->halal_commitments }}</span>
                </div>
            @endif
            @if ($partnership->partner_commitments)
                <div class="ds-detail-row">
                    <span class="ds-detail-label">التزامات الشريك:</span>
                    <span class="ds-detail-value">{{ $partnership->partner_commitments }}</span>
                </div>
            @endif
            @if ($partnership->contract_pdf)
                <div class="ds-detail-row">
                    <span class="ds-detail-label">العقد:</span>
                    <a class="ds-link ds-detail-value" href="{{ $partnership->contract_pdf }}" target="_blank" rel="noopener">عرض العقد</a>
                </div>
            @endif
        </div>
    </div>
</div>
