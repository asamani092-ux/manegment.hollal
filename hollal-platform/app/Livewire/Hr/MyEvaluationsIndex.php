<?php

namespace App\Livewire\Hr;

use App\Models\EmployeeEvaluation;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use App\Livewire\Concerns\UsesDsPagination;

/**
 * Employee archive of approved/archived quarterly evaluations (no employee comment).
 */
class MyEvaluationsIndex extends Component
{
    use WithPagination;
    use UsesDsPagination;

    public ?int $viewId = null;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function openView(int $id): void
    {
        $evaluation = EmployeeEvaluation::findOrFail($id);
        abort_unless(
            (int) $evaluation->employee_id === (int) auth()->id()
            || auth()->user()->can('hr.employees.view'),
            403
        );
        abort_unless($evaluation->isVisibleToEmployee() || auth()->user()->can('hr.employees.view'), 403);
        $this->viewId = $id;
    }

    public function closeView(): void
    {
        $this->viewId = null;
    }

    public function render(): View
    {
        $rows = EmployeeEvaluation::query()
            ->where('employee_id', auth()->id())
            ->whereIn('status', [
                EmployeeEvaluation::STATUS_APPROVED,
                EmployeeEvaluation::STATUS_ARCHIVED,
            ])
            ->with(['cycle', 'scores.cycleItem', 'approver:id,name'])
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->paginate(15);

        $viewEvaluation = $this->viewId
            ? EmployeeEvaluation::with(['cycle.items', 'scores.cycleItem', 'approver:id,name'])
                ->find($this->viewId)
            : null;

        return view('livewire.hr.my-evaluations-index', [
            'rows' => $rows,
            'viewEvaluation' => $viewEvaluation,
        ]);
    }
}
