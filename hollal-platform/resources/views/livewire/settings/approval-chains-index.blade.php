<div>
    <x-ds-page>
        <x-ds-page-header title="سلاسل الطلبات" screen="settings.approval-chains">
            <x-slot:actions>
                <button type="button" class="ds-btn ds-btn-primary" wire:click="openCreate">تعيين سلسلة</button>
            </x-slot:actions>
        </x-ds-page-header>
        <section class="ds-card ds-mb-3">
            <h2>كيف تُعيَّن السلسلة</h2>
            <ol>
                <li>اختر نوع الطلب: مصروف، عهدة، إجازة، عمل إضافي، استئذان، أو إنابة.</li>
                <li>حدد شريحة المبلغ أو الأيام. الطلب يستخدم أول شريحة تغطي قيمته.</li>
                <li>رتّب الخطوات: المدير المباشر، ثم المالية، ثم التنفيذي، أو موظف بالاسم.</li>
                <li>احفظ. الطلب الجديد يقف عند الخطوة الأولى حتى يعتمدها صاحبها.</li>
            </ol>
            <p class="ds-text-muted">شاشة «المالية» في الإعدادات خاصة بتجاوز المدير عند غيابه. تعيين المعتمدين يتم من هنا.</p>
        </section>
        <table class="ds-table">
            <thead>
                <tr><th>النوع</th><th>من</th><th>إلى</th><th>الخطوات بالترتيب</th><th>الحالة</th></tr>
            </thead>
            <tbody>
                @forelse ($rules as $rule)
                    <tr wire:key="chain-{{ $rule->id }}">
                        <td>{{ $typeLabels[$rule->transaction_type] ?? $rule->transaction_type }}</td>
                        <td>{{ $rule->min_amount }}</td>
                        <td>{{ $rule->max_amount ?? 'بلا حد' }}</td>
                        <td>
                            @foreach ($rule->approval_steps ?? [] as $step)
                                <span class="ds-badge">
                                    @php
                                        $kind = is_array($step) ? ($step['type'] ?? $step['role'] ?? '') : $step;
                                    @endphp
                                    {{ match ($kind) {
                                        'direct_manager', 'department_manager' => 'المدير المباشر',
                                        'finance', 'finance_manager' => 'المالية',
                                        'executive', 'executive_director' => 'التنفيذي',
                                        'user' => 'موظف محدد',
                                        'role' => 'دور: '.($step['role'] ?? ''),
                                        default => $kind,
                                    } }}
                                </span>
                            @endforeach
                        </td>
                        <td>{{ $rule->is_active ? 'مفعّلة' : 'موقوفة' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">لا توجد سلسلة بعد. اضغط «تعيين سلسلة».</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($showModal)
            <section class="ds-card ds-mt-3">
                <h2>تعيين سلسلة</h2>
                <label>نوع الطلب
                    <select class="ds-input" wire:model="transaction_type">
                        @foreach ($typeLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>من <input class="ds-input" wire:model="min_amount"></label>
                <label>إلى (فارغ بلا حد) <input class="ds-input" wire:model="max_amount"></label>
                <p>الخطوات الحالية: {{ count($steps) }}</p>
                <select class="ds-input" wire:model="draftKind">
                    <option value="direct_manager">المدير المباشر</option>
                    <option value="finance">المالية</option>
                    <option value="executive">التنفيذي</option>
                    <option value="user">موظف بالاسم</option>
                    <option value="role">دور</option>
                </select>
                @if ($draftKind === 'user')
                    <select class="ds-input" wire:model="draftUserId">
                        <option value="">اختر الموظف</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                        @endforeach
                    </select>
                @endif
                @if ($draftKind === 'role')
                    <input class="ds-input" wire:model="draftRole" placeholder="اسم الدور">
                @endif
                <button type="button" class="ds-btn" wire:click="addStep">أضف الخطوة</button>
                <button type="button" class="ds-btn ds-btn-primary" wire:click="save">حفظ السلسلة</button>
            </section>
        @endif
    </x-ds-page>
</div>
