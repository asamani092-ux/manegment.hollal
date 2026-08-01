<?php

namespace App\Livewire\Hr;

use App\Models\PeriodicEvaluation;
use App\Models\User;
use App\Services\EvaluationService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Periodic evaluations index + create/publish.
 * Time: O(n) | Space: O(page).
 */
class EvaluationsIndex extends Component
{
    use WithPagination;

    public bool $showCreate = false;

    public ?int $employee_id = null;

    public string $period = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('hr.employees.view'), 403);
        $this->period = now()->format('Y').'-Q'.ceil(now()->month / 3);
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $this->showCreate = true;
    }

    public function createEvaluation(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        $this->validate([
            'employee_id' => 'required|exists:users,id',
            'period' => 'required|string|max:20|regex:/^\d{4}-Q[1-4]$/',
        ], [
            'period.regex' => 'صيغة الفترة يجب أن تكون مثل 2026-Q3.',
        ]);

        $exists = PeriodicEvaluation::query()
            ->where('employee_id', $this->employee_id)
            ->where('period', $this->period)
            ->exists();

        if ($exists) {
            $this->addError('period', 'يوجد تقييم لهذا الموظف في الفترة نفسها.');

            return;
        }

        app(EvaluationService::class)->create(
            User::findOrFail($this->employee_id),
            $this->period,
            auth()->user()
        );

        $this->showCreate = false;
        $this->dispatch('toast', type: 'success', message: 'تم إنشاء التقييم');
    }

    public function publish(int $id): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $evaluation = PeriodicEvaluation::findOrFail($id);
        app(EvaluationService::class)->publish($evaluation);
        $this->dispatch('toast', type: 'success', message: 'تم نشر التقييم');
    }

    public function render(): View
    {
        $query = PeriodicEvaluation::query()
            ->select(['id', 'employee_id', 'period', 'evaluator_id', 'status', 'created_at'])
            ->with(['employee:id,name', 'evaluator:id,name'])
            ->latest();

        if (! auth()->user()->can('hr.employees.update')) {
            $query->where(function ($q) {
                $q->where('employee_id', auth()->id())
                    ->orWhere('evaluator_id', auth()->id());
            });
        }

        return view('livewire.hr.evaluations-index', [
            'evaluations' => $query->paginate(15),
            'employees' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canManage' => auth()->user()->can('hr.employees.update'),
        ]);
    }
}
