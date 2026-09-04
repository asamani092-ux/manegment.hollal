<?php

namespace App\Livewire\Expenses;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\ExpenseRequest;
use App\Models\Project;
use App\Notifications\ExpenseRejected;
use App\Services\AuditLogService;
use App\Services\ExpenseApprovalService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Expenses — CRUD, approval workflow, pagination.
 * Time: O(n) per page | Space: O(n).
 */
class ExpensesIndex extends Component
{
    use AuthorizesRequests;
    use UsesDsPagination;
    use WithFileUploads;
    use WithPagination;

    public string $activeTab = 'my';

    public string $statusFilter = '';

    public string $projectFilter = '';

    public bool $showExpenseModal = false;

    public bool $showRejectModal = false;

    public bool $showReturnModal = false;

    public bool $showPayModal = false;

    public bool $expenseViewOnly = false;

    public ?int $expenseId = null;

    public ?int $rejectExpenseId = null;

    public ?int $returnExpenseId = null;

    public ?int $payExpenseId = null;

    public string $type = 'operational';

    public string $amount = '';

    public string $reason = '';

    public string $priority = 'normal';

    public string $payment_method = 'transfer';

    public ?int $project_id = null;

    public ?int $category_id = null;

    public ?TemporaryUploadedFile $officialDocument = null;

    public ?string $existingOfficialDocPath = null;

    public ?TemporaryUploadedFile $attachment = null;

    public ?TemporaryUploadedFile $cameraAttachment = null;

    public ?string $existingAttachmentPath = null;

    public ?TemporaryUploadedFile $paymentProof = null;

    public string $rejectionReason = '';

    public string $returnReason = '';

    public ?int $open = null;

    protected $queryString = [
        'activeTab' => ['except' => 'my'],
        'statusFilter' => ['except' => ''],
        'projectFilter' => ['except' => ''],
        'open' => ['except' => null],
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', ExpenseRequest::class);

        // المراجعون يفتحون «جميع الطلبات» افتراضياً حتى لا تبدو الشاشة فارغة بلا مبرر.
        if ($this->activeTab === 'my'
            && ! request()->query->has('activeTab')
            && auth()->user()->can('finance.expenses.view')) {
            $this->activeTab = 'all';
        }

        if ($this->activeTab === 'all' && ! auth()->user()->can('finance.expenses.view')) {
            $this->activeTab = 'my';
        }

        if ($this->open) {
            $this->openExpenseView($this->open);
        }
    }

    public function setTab(string $tab): void
    {
        if ($tab === 'all') {
            $this->authorize('finance.expenses.view');
        }

        $this->activeTab = $tab;
        $this->resetPage('myExpensesPage');
        $this->resetPage('allExpensesPage');
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage('myExpensesPage');
        $this->resetPage('allExpensesPage');
    }

    public function updatingProjectFilter(): void
    {
        $this->resetPage('myExpensesPage');
        $this->resetPage('allExpensesPage');
    }

    public function updatedAttachment(): void
    {
        $this->validateAttachment('attachment');
    }

    public function updatedCameraAttachment(): void
    {
        $this->validateAttachment('cameraAttachment');
        if ($this->cameraAttachment) {
            $this->attachment = $this->cameraAttachment;
        }
    }

    public function openExpenseCreate(): void
    {
        $this->authorize('create', ExpenseRequest::class);
        $this->resetExpenseForm();
        $this->showExpenseModal = true;
    }

    public function openExpenseEdit(int $id): void
    {
        $expense = ExpenseRequest::findOrFail($id);
        $this->authorize('update', $expense);
        $this->fillExpenseForm($expense);
        $this->expenseViewOnly = false;
        $this->showExpenseModal = true;
    }

    public function openExpenseView(int $id): void
    {
        $expense = ExpenseRequest::findOrFail($id);
        $this->authorize('view', $expense);
        $this->fillExpenseForm($expense);
        $this->expenseViewOnly = true;
        $this->showExpenseModal = true;
    }

    public function saveExpense(bool $submit = false): void
    {
        if ($this->expenseViewOnly) {
            return;
        }

        $isEdit = (bool) $this->expenseId;

        if ($isEdit) {
            $expense = ExpenseRequest::findOrFail($this->expenseId);
            $this->authorize('update', $expense);
        } else {
            $this->authorize('create', ExpenseRequest::class);
        }

        $this->validate([
            'type' => 'required|in:operational,travel,supplies,other',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string',
            'category_id' => 'required|exists:expense_categories,id',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'payment_method' => 'nullable|in:transfer,pos,cheque,cash,other',
            'project_id' => 'nullable|exists:projects,id',
            'officialDocument' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
            'attachment' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx',
            'cameraAttachment' => 'nullable|file|max:5120|mimes:jpg,jpeg,png',
        ], [
            'category_id.required' => 'يجب اختيار تصنيف الصرف',
        ]);

        $data = [
            'requester_id' => auth()->id(),
            'type' => $this->type,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'priority' => $this->priority ?: 'normal',
            'payment_method' => $this->payment_method ?: 'transfer',
            'project_id' => $this->project_id,
            'category_id' => $this->category_id,
            'status' => 'draft',
            'rejection_reason' => null,
        ];

        if ($this->attachment) {
            $data['attachment'] = $this->attachment->store('expenses', 'local');
        }

        if ($this->officialDocument) {
            $data['official_document_path'] = $this->officialDocument->store('expenses/official', 'local');
        }

        if ($isEdit) {
            $expense->update($data);
        } else {
            $expense = ExpenseRequest::create($data);
            $this->expenseId = $expense->id;
        }

        if ($submit) {
            $this->submitExpense($expense->id);

            return;
        }

        $this->closeExpenseModal();
        $this->dispatch('toast', type: 'success', message: $isEdit ? 'تم حفظ الطلب' : 'تم إنشاء الطلب');
    }

    public function submitExpense(int $id): void
    {
        $expense = ExpenseRequest::findOrFail($id);
        $this->authorize('submit', $expense);

        app(ExpenseApprovalService::class)->initializeChain($expense);

        $this->closeExpenseModal();
        $this->dispatch('toast', type: 'success', message: 'تم إرسال الطلب للموافقة');
    }

    public function approveExpense(int $id): void
    {
        $expense = ExpenseRequest::findOrFail($id);
        $this->authorize('approve', $expense);

        app(ExpenseApprovalService::class)->approve(auth()->user(), $expense);

        $this->dispatch('toast', type: 'success', message: 'تمت الموافقة على الطلب');
    }

    public function openRejectModal(int $id): void
    {
        $expense = ExpenseRequest::findOrFail($id);
        $this->authorize('reject', $expense);
        $this->rejectExpenseId = $id;
        $this->rejectionReason = '';
        $this->showRejectModal = true;
    }

    public function confirmRejectExpense(): void
    {
        $expense = ExpenseRequest::findOrFail($this->rejectExpenseId);
        $this->authorize('reject', $expense);

        $this->validate([
            'rejectionReason' => 'required|string|min:3',
        ]);

        app(ExpenseApprovalService::class)->reject(auth()->user(), $expense, $this->rejectionReason);

        $expense->load(['requester:id,name', 'approver:id,name']);
        $expense->requester?->notify(new ExpenseRejected($expense));

        $this->closeRejectModal();
        $this->dispatch('toast', type: 'success', message: 'تم رفض الطلب');
    }

    public function openReturnModal(int $id): void
    {
        $expense = ExpenseRequest::findOrFail($id);
        $this->authorize('reject', $expense);
        $this->returnExpenseId = $id;
        $this->returnReason = '';
        $this->showReturnModal = true;
    }

    public function confirmReturnExpense(): void
    {
        $expense = ExpenseRequest::findOrFail($this->returnExpenseId);
        $this->authorize('reject', $expense);

        $this->validate([
            'returnReason' => 'required|string|min:3',
        ]);

        app(ExpenseApprovalService::class)->returnForRevision(auth()->user(), $expense, $this->returnReason);

        $this->closeReturnModal();
        $this->dispatch('toast', type: 'success', message: 'أُعيد الطلب للمراجعة');
    }

    public function openPayModal(int $id): void
    {
        $expense = ExpenseRequest::findOrFail($id);
        $this->authorize('pay', $expense);
        $this->payExpenseId = $id;
        $this->paymentProof = null;
        $this->showPayModal = true;
    }

    public function markExpensePaid(?int $id = null): void
    {
        $expenseId = $id ?? $this->payExpenseId;
        $expense = ExpenseRequest::findOrFail($expenseId);
        $this->authorize('pay', $expense);

        $rules = [];
        if ($expense->requiresPaymentProof()) {
            $rules['paymentProof'] = 'required|file|max:5120|mimes:pdf,jpg,jpeg,png';
        } else {
            $rules['paymentProof'] = 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png';
        }

        $this->validate($rules, [
            'paymentProof.required' => 'إثبات الدفع إلزامي لغير النقد',
        ]);

        $data = ['status' => 'paid'];
        if ($this->paymentProof) {
            $data['payment_proof_path'] = $this->paymentProof->store('expenses/proofs', 'local');
        }

        $expense->update($data);

        app(AuditLogService::class)->record('expense.paid', $expense);
        try {
            app(\App\Services\JournalService::class)->postExpensePaid($expense->fresh(['category.account']), auth()->user());
        } catch (\Throwable $e) {
            report($e);
        }

        $this->closePayModal();
        $this->dispatch('toast', type: 'success', message: 'تم تسجيل الدفع');
    }

    public function deleteExpense(int $id): void
    {
        $expense = ExpenseRequest::findOrFail($id);
        $this->authorize('delete', $expense);
        $expense->delete();
        $this->dispatch('toast', type: 'success', message: 'تم حذف الطلب');
    }

    public function closeExpenseModal(): void
    {
        $this->showExpenseModal = false;
        $this->resetExpenseForm();
    }

    public function closeRejectModal(): void
    {
        $this->showRejectModal = false;
        $this->rejectExpenseId = null;
        $this->rejectionReason = '';
        $this->resetValidation();
    }

    public function closeReturnModal(): void
    {
        $this->showReturnModal = false;
        $this->returnExpenseId = null;
        $this->returnReason = '';
        $this->resetValidation();
    }

    public function closePayModal(): void
    {
        $this->showPayModal = false;
        $this->payExpenseId = null;
        $this->paymentProof = null;
        $this->resetValidation();
    }

    protected function validateAttachment(string $field): void
    {
        $this->validate([
            $field => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf,doc,docx',
        ]);
    }

    protected function fillExpenseForm(ExpenseRequest $expense): void
    {
        $this->expenseId = $expense->id;
        $this->type = $expense->type;
        $this->amount = (string) $expense->amount;
        $this->reason = $expense->reason;
        $this->priority = $expense->priority ?? 'normal';
        $this->payment_method = $expense->payment_method;
        $this->project_id = $expense->project_id;
        $this->category_id = $expense->category_id;
        $this->existingAttachmentPath = $expense->attachment;
        $this->existingOfficialDocPath = $expense->official_document_path;
    }

    protected function resetExpenseForm(): void
    {
        $this->expenseId = null;
        $this->expenseViewOnly = false;
        $this->type = 'operational';
        $this->amount = '';
        $this->reason = '';
        $this->priority = 'normal';
        $this->payment_method = 'transfer';
        $this->project_id = null;
        $this->category_id = null;
        $this->officialDocument = null;
        $this->existingOfficialDocPath = null;
        $this->attachment = null;
        $this->cameraAttachment = null;
        $this->existingAttachmentPath = null;
        $this->resetValidation();
    }

    protected function expenseQuery(int $userId, string $scope)
    {
        $query = ExpenseRequest::query()
            ->select([
                'id', 'requester_id', 'project_id', 'type', 'amount', 'reason', 'priority',
                'payment_method', 'attachment', 'status', 'current_approval_stage',
                'approver_id', 'approved_at', 'paid_ready_at', 'rejection_reason', 'created_at',
            ])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter));

        if ($scope === 'my') {
            $query->where('requester_id', $userId)
                ->with(['project:id,name']);
        } else {
            $query->with(['project:id,name', 'requester:id,name', 'approver:id,name']);
        }

        return $query->orderByPriority()->latest();
    }

    public function render(): View
    {
        $userId = auth()->id();
        $canViewAll = auth()->user()->can('finance.expenses.view');
        $approval = app(ExpenseApprovalService::class);

        return view('livewire.expenses.expenses-index', [
            'myExpenses' => $this->expenseQuery($userId, 'my')->paginate(8, pageName: 'myExpensesPage'),
            'allExpenses' => $canViewAll
                ? $this->expenseQuery($userId, 'all')->paginate(8, pageName: 'allExpensesPage')
                : null,
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'categories' => \App\Models\ExpenseCategory::active()->orderBy('name_ar')->get(['id', 'name_ar']),
            'companyTaxNumberMissing' => blank(\App\Support\Setting::get('company.tax_number')),
            'statusOptions' => ExpenseRequest::STATUSES,
            'canViewAll' => $canViewAll,
            'approvalService' => $approval,
            'payExpense' => $this->payExpenseId ? ExpenseRequest::find($this->payExpenseId) : null,
        ])->layout('layouts.app', ['title' => 'طلبات الصرف المالي']);
    }
}
