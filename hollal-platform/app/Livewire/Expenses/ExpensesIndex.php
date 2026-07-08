<?php

namespace App\Livewire\Expenses;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\ExpenseRequest;
use App\Models\Project;
use App\Notifications\ExpenseRejected;
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

    public bool $expenseViewOnly = false;

    public ?int $expenseId = null;

    public ?int $rejectExpenseId = null;

    public string $type = 'operational';

    public string $amount = '';

    public string $reason = '';

    public string $priority = 'normal';

    public string $payment_method = 'transfer';

    public ?int $project_id = null;

    public ?TemporaryUploadedFile $attachment = null;

    public ?TemporaryUploadedFile $cameraAttachment = null;

    public ?string $existingAttachmentPath = null;

    public string $rejectionReason = '';

    protected $queryString = [
        'activeTab' => ['except' => 'my'],
        'statusFilter' => ['except' => ''],
        'projectFilter' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', ExpenseRequest::class);

        if ($this->activeTab === 'all' && ! auth()->user()->can('expenses.view')) {
            $this->activeTab = 'my';
        }
    }

    public function setTab(string $tab): void
    {
        if ($tab === 'all') {
            $this->authorize('expenses.view');
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
            'priority' => 'required|in:low,normal,high,urgent',
            'payment_method' => 'required|in:transfer,pos,cheque,other',
            'project_id' => 'nullable|exists:projects,id',
            'attachment' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx',
            'cameraAttachment' => 'nullable|file|max:5120|mimes:jpg,jpeg,png',
        ]);

        $data = [
            'requester_id' => auth()->id(),
            'type' => $this->type,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'priority' => $this->priority,
            'payment_method' => $this->payment_method,
            'project_id' => $this->project_id,
            'status' => 'draft',
        ];

        if ($this->attachment) {
            $data['attachment'] = $this->attachment->store('expenses', 'local');
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

    public function markExpensePaid(int $id): void
    {
        $expense = ExpenseRequest::findOrFail($id);
        $this->authorize('pay', $expense);

        $expense->update(['status' => 'paid']);

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
        $this->existingAttachmentPath = $expense->attachment;
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
        $canViewAll = auth()->user()->can('expenses.view');

        return view('livewire.expenses.expenses-index', [
            'myExpenses' => $this->expenseQuery($userId, 'my')->paginate(8, pageName: 'myExpensesPage'),
            'allExpenses' => $canViewAll
                ? $this->expenseQuery($userId, 'all')->paginate(8, pageName: 'allExpensesPage')
                : null,
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'statusOptions' => ExpenseRequest::STATUSES,
            'canViewAll' => $canViewAll,
            'canManageSettings' => auth()->user()->can('settings.manage'),
        ])->layout('layouts.app', ['title' => 'المصروفات']);
    }
}
