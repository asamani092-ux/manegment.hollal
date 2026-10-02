<div>
    <x-ds-page>
        <x-ds-page-header title="سلاسل الطلبات" screen="settings.approval-chains" />
        <p class="ds-text-muted">الخطوة الافتراضية موظف بالاسم. أنواع أخرى تظهر كخيارات متقدمة.</p>
        <table class="ds-table">
            <thead>
                <tr><th>النوع</th><th>المقياس</th><th>من</th><th>إلى</th><th>الخطوات</th></tr>
            </thead>
            <tbody>
                @foreach ($rules as $rule)
                    <tr>
                        <td>{{ $rule->transaction_type }}</td>
                        <td>{{ $rule->metric ?? 'amount' }}</td>
                        <td>{{ $rule->min_amount }}</td>
                        <td>{{ $rule->max_amount ?? '—' }}</td>
                        <td>{{ count($rule->approval_steps ?? []) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ds-page>
</div>
