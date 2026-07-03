<?php

namespace App\Livewire\Expenses;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\ExpenseRequest;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ExpenseApproved;
use App\Notifications\ExpenseAwaitingApproval;
use App\Notifications\ExpenseRejected;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Notification;
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

    public string $payment_method = 'bank_transfer';

    public ?int $project_id = null;

    public ?TemporaryUploadedFile $attachment = null;

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
            'payment_method' => 'required|in:cash,bank_transfer,card,other',
            'project_id' => 'nullable|exists:projects,id',
            'attachment' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx',
        ]);

        $data = [
            'requester_id' => auth()->id(),
            'type' => $this->type,
            'amount' => $this->amount,
            'reason' => $this->reason,
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

        $expense->update([
            'status' => 'pending',
            'rejection_reason' => null,
            'approver_id' => null,
            'approved_at' => null,
        ]);

        $expense->load(['requester:id,name', 'project:id,name']);

        $approvers = User::permission('expenses.approve')
            ->where('is_active', true)
            ->where('id', '!=', auth()->id())
            ->get();

        Notification::send($approvers, new ExpenseAwaitingApproval($expense));

        $this->closeExpenseModal();
        $this->dispatch('toast', type: 'success', message: 'تم إرسال الطلب للموافقة');
    }

    public function approveExpense(int $id): void
    {
        $expense = ExpenseRequest::findOrFail($id);
        $this->authorize('approve', $expense);

        $expense->update([
            'status' => 'approved',
            'approver_id' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        $expense->load(['requester:id,name', 'approver:id,name']);

        $expense->requester?->notify(new ExpenseApproved($expense));

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

        $expense->update([
            'status' => 'rejected',
            'approver_id' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => $this->rejectionReason,
        ]);

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

    protected function fillExpenseForm(ExpenseRequest $expense): void
    {
        $this->expenseId = $expense->id;
        $this->type = $expense->type;
        $this->amount = (string) $expense->amount;
        $this->reason = $expense->reason;
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
        $this->payment_method = 'bank_transfer';
        $this->project_id = null;
        $this->attachment = null;
        $this->existingAttachmentPath = null;
        $this->resetValidation();
    }

    protected function expenseQuery(int $userId, string $scope)
    {
        $query = ExpenseRequest::query()
            ->select([
                'id', 'requester_id', 'project_id', 'type', 'amount', 'reason',
                'payment_method', 'attachment', 'status', 'approver_id', 'approved_at',
                'rejection_reason', 'created_at',
            ])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter));

        if ($scope === 'my') {
            $query->where('requester_id', $userId)
                ->with(['project:id,name']);
        } else {
            $query->with(['project:id,name', 'requester:id,name', 'approver:id,name']);
        }

        return $query->latest();
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
        ])->layout('layouts.app', ['title' => 'المصروفات']);
    }
}
