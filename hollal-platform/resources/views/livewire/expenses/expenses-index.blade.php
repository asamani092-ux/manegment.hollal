<div>
    @php
        $statusLabels = [
            'draft' => 'مسودة',
            'pending' => 'بانتظار الموافقة',
            'approved' => 'موافق عليه',
            'paid' => 'مدفوع',
            'rejected' => 'مرفوض',
        ];
        $typeLabels = [
            'operational' => 'تشغيلي',
            'travel' => 'سفر',
            'supplies' => 'مستلزمات',
            'other' => 'أخرى',
        ];
        $paymentLabels = [
            'transfer' => 'تحويل',
            'pos' => 'نقاط بيع',
            'cheque' => 'شيك',
            'other' => 'أخرى',
        ];
        $priorityLabels = [
            'low' => 'منخفضة',
            'normal' => 'عادية',
            'high' => 'عالية',
            'urgent' => 'عاجلة',
        ];
    @endphp

    <x-ds-page-header
        title="المصروفات"
        :show-button="auth()->user()->can('expenses.create')"
        button-label="طلب مصروف"
        button-icon="fa-plus"
        wire:click="openExpenseCreate"
    />

    @if ($canManageSettings)
        <div class="ds-page-toolbar">
            <a href="{{ route('settings.expenses') }}" class="ds-btn ds-btn-outline ds-btn-sm">
                <i class="fas fa-cog" aria-hidden="true"></i>
                إعدادات سلسلة الاعتماد
            </a>
        </div>
    @endif

    <div class="ds-page-toolbar">
        <div class="ds-toolbar-actions">
            <button type="button" class="ds-btn @if ($activeTab === 'my') ds-btn-primary @else ds-btn-outline @endif" wire:click="setTab('my')">
                طلباتي
            </button>
            @if ($canViewAll)
                <button type="button" class="ds-btn @if ($activeTab === 'all') ds-btn-primary @else ds-btn-outline @endif" wire:click="setTab('all')">
                    جميع الطلبات
                </button>
            @endif
        </div>
    </div>

    <div class="ds-filters-row">
        <div class="ds-filter-field">
            <label class="ds-label">الحالة</label>
            <select class="ds-input" wire:model.live="statusFilter">
                <option value="">— الكل —</option>
                @foreach ($statusOptions as $opt)
                    <option value="{{ $opt }}">{{ $statusLabels[$opt] ?? $opt }}</option>
                @endforeach
            </select>
        </div>
        <div class="ds-filter-field">
            <label class="ds-label">المشروع</label>
            <select class="ds-input" wire:model.live="projectFilter">
                <option value="">— الكل —</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($activeTab === 'my')
        <section class="ds-section-spaced">
            <h2 class="ds-section-heading">طلباتي</h2>
            <x-ds-table>
                <x-slot:head>
                    <tr>
                        <th>النوع</th>
                        <th>المبلغ</th>
                        <th>الأولوية</th>
                        <th>المشروع</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th>إجراءات</th>
                    </tr>
                </x-slot:head>
                @forelse ($myExpenses as $expense)
                    <tr wire:key="my-expense-{{ $expense->id }}">
                        <td>{{ $typeLabels[$expense->type] ?? $expense->type }}</td>
                        <td>{{ number_format((float) $expense->amount, 2) }}</td>
                        <td>{{ $priorityLabels[$expense->priority] ?? $expense->priority }}</td>
                        <td>{{ $expense->project?->name ?? '—' }}</td>
                        <td>{{ $statusLabels[$expense->status] ?? $expense->status }}</td>
                        <td>{{ $expense->created_at?->format('Y-m-d') ?? '—' }}</td>
                        <td>
                            <div class="ds-toolbar-actions">
                                <x-ds-action-icons
                                    :show-view="true"
                                    :show-edit="auth()->user()->can('update', $expense)"
                                    :show-delete="auth()->user()->can('delete', $expense)"
                                    :view-action="'openExpenseView('.$expense->id.')'"
                                    :edit-action="'openExpenseEdit('.$expense->id.')'"
                                    :delete-action="'deleteExpense('.$expense->id.')'"
                                    delete-confirm="حذف هذا الطلب؟"
                                />
                                @can('submit', $expense)
                                    <button type="button" class="ds-btn ds-btn-sm ds-btn-primary" wire:click="submitExpense({{ $expense->id }})">
                                        إرسال
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="ds-text-muted ds-table-empty">لا توجد طلبات</td>
                    </tr>
                @endforelse
            </x-ds-table>

            {{ $myExpenses->links() }}
        </section>
    @elseif ($canViewAll && $allExpenses)
        <section class="ds-section-spaced">
            <h2 class="ds-section-heading">جميع الطلبات</h2>
            <x-ds-table>
                <x-slot:head>
                    <tr>
                        <th>مقدم الطلب</th>
                        <th>النوع</th>
                        <th>المبلغ</th>
                        <th>الأولوية</th>
                        <th>المشروع</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </x-slot:head>
                @forelse ($allExpenses as $expense)
                    <tr wire:key="all-expense-{{ $expense->id }}">
                        <td>{{ $expense->requester?->name ?? '—' }}</td>
                        <td>{{ $typeLabels[$expense->type] ?? $expense->type }}</td>
                        <td>{{ number_format((float) $expense->amount, 2) }}</td>
                        <td>{{ $priorityLabels[$expense->priority] ?? $expense->priority }}</td>
                        <td>{{ $expense->project?->name ?? '—' }}</td>
                        <td>{{ $statusLabels[$expense->status] ?? $expense->status }}</td>
                        <td>
                            <div class="ds-toolbar-actions">
                                <x-ds-action-icons
                                    :show-view="true"
                                    :show-edit="false"
                                    :show-delete="false"
                                    :view-action="'openExpenseView('.$expense->id.')'"
                                />
                                @can('approve', $expense)
                                    <button type="button" class="ds-btn ds-btn-sm ds-btn-primary" wire:click="approveExpense({{ $expense->id }})">
                                        موافقة
                                    </button>
                                    <button type="button" class="ds-btn ds-btn-sm ds-btn-outline" wire:click="openRejectModal({{ $expense->id }})">
                                        رفض
                                    </button>
                                @endcan
                                @can('pay', $expense)
                                    <button type="button" class="ds-btn ds-btn-sm ds-btn-primary" wire:click="markExpensePaid({{ $expense->id }})">
                                        تسجيل الدفع
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="ds-text-muted ds-table-empty">لا توجد طلبات</td>
                    </tr>
                @endforelse
            </x-ds-table>

            {{ $allExpenses->links() }}
        </section>
    @endif

    @if ($showExpenseModal)
        <div class="ds-modal-overlay" wire:click.self="closeExpenseModal">
            <div class="ds-modal ds-modal-lg" role="dialog">
                <div class="ds-modal-header">
                    <h3>
                        @if ($expenseViewOnly)
                            عرض طلب مصروف
                        @elseif ($expenseId)
                            تعديل طلب مصروف
                        @else
                            طلب مصروف جديد
                        @endif
                    </h3>
                    <button type="button" class="ds-modal-close" wire:click="closeExpenseModal">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <div class="ds-grid-2">
                        <x-ds-form-group label="النوع" :error="$errors->first('type')">
                            <select class="ds-input" wire:model="type" @disabled($expenseViewOnly)>
                                @foreach ($typeLabels as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="المبلغ" :error="$errors->first('amount')">
                            <input type="number" step="0.01" class="ds-input" wire:model="amount" @disabled($expenseViewOnly)>
                        </x-ds-form-group>
                        <x-ds-form-group label="الأولوية" :error="$errors->first('priority')">
                            <select class="ds-input" wire:model="priority" @disabled($expenseViewOnly)>
                                @foreach ($priorityLabels as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="طريقة الدفع" :error="$errors->first('payment_method')">
                            <select class="ds-input" wire:model="payment_method" @disabled($expenseViewOnly)>
                                @foreach ($paymentLabels as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="المشروع" :error="$errors->first('project_id')">
                            <select class="ds-input" wire:model="project_id" @disabled($expenseViewOnly)>
                                <option value="">— بدون —</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                    </div>
                    <x-ds-form-group label="السبب" :error="$errors->first('reason')">
                        <textarea class="ds-input" rows="3" wire:model="reason" @disabled($expenseViewOnly)></textarea>
                    </x-ds-form-group>

                    @if (! $expenseViewOnly)
                        <x-ds-form-group label="المرفق" :error="$errors->first('attachment')">
                            <input type="file" class="ds-input" wire:model="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <div wire:loading wire:target="attachment" class="ds-text-muted">جاري الرفع...</div>
                        </x-ds-form-group>
                        <x-ds-form-group label="التقاط بالكاميرا" :error="$errors->first('cameraAttachment')">
                            <input type="file" class="ds-input" wire:model="cameraAttachment" accept="image/*" capture="environment">
                            <div wire:loading wire:target="cameraAttachment" class="ds-text-muted">جاري الرفع...</div>
                        </x-ds-form-group>
                    @endif

                    @if ($expenseViewOnly && $existingAttachmentPath)
                        <div class="ds-detail-row">
                            <span class="ds-detail-label">المرفق:</span>
                            <a class="ds-link" href="{{ route('expenses.files.download', $expenseId) }}">تحميل</a>
                        </div>
                    @endif
                </div>
                <div class="ds-modal-footer">
                    @if (! $expenseViewOnly)
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="saveExpense(false)" wire:loading.attr="disabled">
                            <i class="fas fa-save"></i> حفظ مسودة
                        </button>
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="saveExpense(true)" wire:loading.attr="disabled">
                            <i class="fas fa-paper-plane"></i> إرسال للموافقة
                        </button>
                    @endif
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="closeExpenseModal">إغلاق</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showRejectModal)
        <div class="ds-modal-overlay" wire:click.self="closeRejectModal">
            <div class="ds-modal" role="dialog">
                <div class="ds-modal-header">
                    <h3>رفض طلب المصروف</h3>
                    <button type="button" class="ds-modal-close" wire:click="closeRejectModal">&times;</button>
                </div>
                <div class="ds-modal-body">
                    <x-ds-form-group label="سبب الرفض" :error="$errors->first('rejectionReason')">
                        <textarea class="ds-input" rows="3" wire:model="rejectionReason" placeholder="أدخل سبب الرفض..."></textarea>
                    </x-ds-form-group>
                </div>
                <div class="ds-modal-footer">
                    <button type="button" class="ds-btn ds-btn-primary" wire:click="confirmRejectExpense">
                        تأكيد الرفض
                    </button>
                    <button type="button" class="ds-btn ds-btn-outline" wire:click="closeRejectModal">إلغاء</button>
                </div>
            </div>
        </div>
    @endif
</div>
