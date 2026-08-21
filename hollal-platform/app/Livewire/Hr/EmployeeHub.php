<?php

namespace App\Livewire\Hr;

use App\Models\AttendanceRecord;
use App\Models\Document;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PeriodicEvaluation;
use App\Models\Responsibility;
use App\Models\Task;
use App\Models\User;
use App\Services\EvaluationService;
use App\Services\LeaveService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * EMP-1 — employee personal hub (مساحتي).
 * Time: O(k) per section | Space: O(k)
 */
class EmployeeHub extends Component
{
    use AuthorizesRequests;

    public string $leaveType = 'سنوية';

    public string $leaveFrom = '';

    public string $leaveTo = '';

    public string $leaveReason = '';

    public string $evalComment = '';

    public ?int $evalId = null;

    public function mount(): void
    {
        $this->authorize('dashboard.view');
        $this->leaveFrom = now()->toDateString();
        $this->leaveTo = now()->addDay()->toDateString();
    }

    public function submitLeave(): void
    {
        abort_unless(auth()->user()->can('hr.leaves.request'), 403);
        $this->validate([
            'leaveType' => 'required|string|max:40',
            'leaveFrom' => 'required|date',
            'leaveTo' => 'required|date|after_or_equal:leaveFrom',
            'leaveReason' => 'nullable|string|max:500',
        ]);

        try {
            app(LeaveService::class)->submit(
                auth()->user(),
                $this->leaveType,
                $this->leaveFrom,
                $this->leaveTo,
                $this->leaveReason ?: null,
            );
            $this->reset('leaveReason');
            $this->dispatch('toast', type: 'success', message: 'قُدّم طلب الإجازة');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function saveEvalComment(int $evaluationId): void
    {
        $this->evalId = $evaluationId;
        $this->validate([
            'evalId' => 'required|exists:periodic_evaluations,id',
            'evalComment' => 'required|string|max:2000',
        ]);
        $eval = PeriodicEvaluation::query()->findOrFail($this->evalId);
        abort_unless((int) $eval->employee_id === (int) auth()->id(), 403);
        try {
            app(EvaluationService::class)->addEmployeeComment($eval, $this->evalComment);
            $this->reset('evalComment', 'evalId');
            $this->dispatch('toast', type: 'success', message: 'حُفظ تعليق التقييم');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $id = $user->id;

        $tasks = Task::query()
            ->select(['id', 'title', 'due_date', 'status', 'priority'])
            ->where('assigned_to', $id)
            ->whereNotIn('status', ['مكتملة', 'ملغاة', 'completed'])
            ->orderBy('due_date')
            ->limit(8)
            ->get();

        $leaves = LeaveRequest::query()
            ->select(['id', 'type', 'from_date', 'to_date', 'status', 'created_at'])
            ->where('employee_id', $id)
            ->latest('id')
            ->limit(8)
            ->get();

        $payslips = PayrollRunItem::query()
            ->select(['id', 'payroll_run_id', 'base', 'allowances', 'deductions', 'gross', 'net'])
            ->where('employee_id', $id)
            ->whereHas('run', fn ($q) => $q->where('status', PayrollRun::STATUS_EXECUTED))
            ->with(['run:id,month,status'])
            ->latest('id')
            ->limit(6)
            ->get();

        $evals = PeriodicEvaluation::query()
            ->select(['id', 'period', 'status', 'employee_comment'])
            ->where('employee_id', $id)
            ->latest('id')
            ->limit(6)
            ->get();

        $attendance = AttendanceRecord::query()
            ->select(['id', 'date', 'check_in_at', 'check_out_at', 'type', 'source', 'late_minutes'])
            ->where('employee_id', $id)
            ->latest('date')
            ->limit(10)
            ->get();

        $docs = Document::query()
            ->select(['id', 'title', 'created_at'])
            ->visibleTo($user)
            ->where('uploader_id', $id)
            ->latest('id')
            ->limit(8)
            ->get();

        $responsibilities = Responsibility::query()
            ->select(['id', 'body', 'order'])
            ->where('employee_id', $id)
            ->active()
            ->orderBy('order')
            ->get();

        $user->loadMissing('profile');

        return view('livewire.hr.employee-hub', [
            'tasks' => $tasks,
            'leaves' => $leaves,
            'payslips' => $payslips,
            'evals' => $evals,
            'attendance' => $attendance,
            'docs' => $docs,
            'responsibilities' => $responsibilities,
            'leaveBalance' => (float) ($user->profile?->annual_leave_balance ?? 0),
        ])->layout('layouts.app', ['title' => 'مساحتي']);
    }
}
