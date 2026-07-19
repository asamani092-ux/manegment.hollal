<?php

namespace App\Livewire\Hr;

use App\Models\PayScale;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * 01-B2 — pay scale editor for the HR manager (hr.salaries.manage). Each scale
 * holds a list of grades {label, base_amount}.
 */
class PayScalesIndex extends Component
{
    use AuthorizesRequests;

    public bool $showModal = false;

    public ?int $scaleId = null;

    public string $name_ar = '';

    public bool $is_active = true;

    /** @var list<array{label: string, base_amount: string}> */
    public array $grades = [];

    public function mount(): void
    {
        $this->authorize('hr.salaries.manage');
    }

    public function openCreate(): void
    {
        $this->authorize('hr.salaries.manage');
        $this->reset(['scaleId', 'name_ar', 'grades']);
        $this->is_active = true;
        $this->grades = [['label' => '', 'base_amount' => '']];
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorize('hr.salaries.manage');
        $scale = PayScale::findOrFail($id);
        $this->scaleId = $scale->id;
        $this->name_ar = $scale->name_ar;
        $this->is_active = $scale->is_active;
        $this->grades = array_map(
            fn ($g) => ['label' => $g['label'] ?? '', 'base_amount' => (string) ($g['base_amount'] ?? '')],
            $scale->grades ?? [],
        ) ?: [['label' => '', 'base_amount' => '']];
        $this->showModal = true;
    }

    public function addGrade(): void
    {
        $this->grades[] = ['label' => '', 'base_amount' => ''];
    }

    public function removeGrade(int $index): void
    {
        unset($this->grades[$index]);
        $this->grades = array_values($this->grades);
    }

    public function save(): void
    {
        $this->authorize('hr.salaries.manage');

        $this->validate([
            'name_ar' => 'required|string|max:255',
            'is_active' => 'boolean',
            'grades' => 'array|min:1',
            'grades.*.label' => 'required|string|max:255',
            'grades.*.base_amount' => 'required|numeric|min:0',
        ]);

        $grades = array_map(
            fn ($g) => ['label' => $g['label'], 'base_amount' => (float) $g['base_amount']],
            $this->grades,
        );

        PayScale::updateOrCreate(
            ['id' => $this->scaleId],
            ['name_ar' => $this->name_ar, 'is_active' => $this->is_active, 'grades' => $grades],
        );

        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: 'تم حفظ سلم الرواتب');
    }

    public function render(): View
    {
        return view('livewire.hr.pay-scales-index', [
            'scales' => PayScale::orderBy('name_ar')->get(),
        ])->layout('layouts.app', ['title' => 'سلم الرواتب']);
    }
}
