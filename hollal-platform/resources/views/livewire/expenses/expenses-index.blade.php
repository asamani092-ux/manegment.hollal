<x-ds-page>
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
        :show-button="auth()->user()->can('finance.expenses.create')"
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

            <div class="ds-task-cards ds-list-cards-mobile">
                @forelse ($myExpenses as $expense)
                    <article class="ds-task-card" wire:key="my-exp-card-{{ $expense->id }}">
                        <h3 class="ds-task-card-title">{{ $typeLabels[$expense->type] ?? $expense->type }}</h3>
                        <div class="ds-task-card-meta">
                            <span class="ds-ltr-num">{{ number_format((float) $expense->amount, 2) }}</span>
                            <span>{{ $priorityLabels[$expense->priority] ?? $expense->priority }}</span>
                            <span class="ds-ltr-num">{{ $expense->created_at?->format('Y-m-d') ?? '—' }}</span>
                        </div>
                        <span class="ds-badge ds-badge-pending">{{ $statusLabels[$expense->status] ?? $expense->status }}</span>
                        <p class="ds-text-muted">{{ $expense->project?->name ?? 'بدون مشروع' }}</p>
                        <div class="ds-task-card-actions">
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openExpenseView({{ $expense->id }})">عرض</button>
                            @can('update', $expense)
                                <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" wire:click="openExpenseEdit({{ $expense->id }})">تعديل</button>
                            @endcan
                            @can('submit', $expense)
                                <button type="button" class="ds-btn ds-btn-sm ds-btn-primary" wire:click="submitExpense({{ $expense->id }})">إرسال</button>
                            @endcan
                        </div>
                    </article>
                @empty
                    <x-ds-empty-state message="لا توجد طلبات" icon="fa-money-bill-wave" />
                @endforelse
            </div>

            <div class="ds-list-table-desktop">
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
                        <td colspan="7"><x-ds-empty-state message="لا توجد طلبات" icon="fa-money-bill-wave" /></td>
                    </tr>
                @endforelse
            </x-ds-table>
            </div>

            {{ $myExpenses->links() }}
        </section>
    @elseif ($canViewAll && $allExpenses)
        <section class="ds-section-spaced">
            <h2 class="ds-section-heading">جميع الطلبات</h2>

            <div class="ds-task-cards ds-list-cards-mobile">
                @forelse ($allExpenses as $expense)
                    <article class="ds-task-card" wire:key="all-exp-card-{{ $expense->id }}">
                        <h3 class="ds-task-card-title">{{ $expense->requester?->name ?? '—' }}</h3>
                        <div class="ds-task-card-meta">
                            <span>{{ $typeLabels[$expense->type] ?? $expense->type }}</span>
                            <span class="ds-ltr-num">{{ number_format((float) $expense->amount, 2) }}</span>
                        </div>
                        <span class="ds-badge ds-badge-pending">{{ $statusLabels[$expense->status] ?? $expense->status }}</span>
                        <div class="ds-task-card-actions">
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="openExpenseView({{ $expense->id }})">عرض</button>
                            @can('approve', $expense)
                                <button type="button" class="ds-btn ds-btn-sm ds-btn-primary" wire:click="approveExpense({{ $expense->id }})">موافقة</button>
                            @endcan
                        </div>
                    </article>
                @empty
                    <x-ds-empty-state message="لا توجد طلبات" icon="fa-money-bill-wave" />
                @endforelse
            </div>

            <div class="ds-list-table-desktop">
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
                        <td colspan="7"><x-ds-empty-state message="لا توجد طلبات" icon="fa-money-bill-wave" /></td>
                    </tr>
                @endforelse
            </x-ds-table>
            </div>

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
                        <x-ds-form-group label="القسم" :error="$errors->first('department_id')">
                            <select class="ds-input" wire:model="department_id" @disabled($expenseViewOnly)>
                                <option value="">— بدون —</option>
                                @foreach ($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                        <x-ds-form-group label="تصنيف المصروف" :error="$errors->first('category_id')">
                            <select class="ds-input" wire:model="category_id" @disabled($expenseViewOnly)>
                                <option value="">— اختر التصنيف —</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name_ar }}</option>
                                @endforeach
                            </select>
                        </x-ds-form-group>
                    </div>

                    @if ($companyTaxNumberMissing)
                        <div class="ds-alert-warning">
                            تذكير: يجب إدخال الرقم الضريبي في بيانات الشركة قبل إصدار المستندات الرسمية
                        </div>
                    @endif
                    <x-ds-form-group label="السبب" :error="$errors->first('reason')">
                        <textarea class="ds-input" rows="3" wire:model="reason" @disabled($expenseViewOnly)></textarea>
                    </x-ds-form-group>

                    @if (! $expenseViewOnly)
                        <x-ds-form-group label="الفاتورة / المستند الرسمي" :error="$errors->first('officialDocument')">
                            <input type="file" class="ds-input" wire:model="officialDocument" accept=".pdf,.jpg,.jpeg,.png">
                            <div wire:loading wire:target="officialDocument" class="ds-text-muted">جاري الرفع...</div>
                        </x-ds-form-group>
                        <x-ds-form-group label="المرفق (صورة الإثبات)" :error="$errors->first('attachment')">
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
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="saveExpense(false)" wire:loading.attr="disabled" wire:target="saveExpense">
                            <span wire:loading.remove wire:target="saveExpense"><i class="fas fa-save"></i> حفظ مسودة</span>
                            <span wire:loading wire:target="saveExpense" class="ds-btn-loading">جاري الحفظ…</span>
                        </button>
                        <button type="button" class="ds-btn ds-btn-primary" wire:click="saveExpense(true)" wire:loading.attr="disabled" wire:target="saveExpense">
                            <span wire:loading.remove wire:target="saveExpense"><i class="fas fa-paper-plane"></i> إرسال للموافقة</span>
                            <span wire:loading wire:target="saveExpense" class="ds-btn-loading">جاري الإرسال…</span>
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
</x-ds-page>
