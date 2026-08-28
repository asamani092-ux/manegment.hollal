<?php

namespace App\Livewire\Hr;

use App\Models\EvaluationTemplate;
use App\Models\EvaluationTemplateItem;
use App\Services\QuarterlyEvaluationService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use App\Livewire\Concerns\UsesDsPagination;

/**
 * HR Round 4 batch 2أ — evaluation templates CRUD (weights must sum to 100).
 */
class EvaluationTemplatesIndex extends Component
{
    use WithPagination;
    use UsesDsPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public bool $is_active = true;

    /** @var list<array{section: string, question_text: string, weight: string, sort_order: string}> */
    public array $items = [];

    public string $search = '';

    /** @var array<string, array<string, string>> */
    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $this->resetValidation();
        $this->editingId = null;
        $this->name = '';
        $this->is_active = true;
        $this->items = [
            ['section' => EvaluationTemplateItem::SECTION_MANAGER, 'question_text' => '', 'weight' => '50', 'sort_order' => '1'],
            ['section' => EvaluationTemplateItem::SECTION_HR, 'question_text' => '', 'weight' => '50', 'sort_order' => '2'],
        ];
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $this->resetValidation();
        $template = EvaluationTemplate::with('items')->findOrFail($id);
        $this->editingId = $template->id;
        $this->name = $template->name;
        $this->is_active = (bool) $template->is_active;
        $this->items = $template->items->map(fn (EvaluationTemplateItem $item) => [
            'section' => $item->section,
            'question_text' => $item->question_text,
            'weight' => (string) $item->weight,
            'sort_order' => (string) $item->sort_order,
        ])->values()->all();
        if ($this->items === []) {
            $this->items = [
                ['section' => EvaluationTemplateItem::SECTION_MANAGER, 'question_text' => '', 'weight' => '100', 'sort_order' => '1'],
            ];
        }
        $this->showForm = true;
    }

    public function addItemRow(): void
    {
        $next = count($this->items) + 1;
        $this->items[] = [
            'section' => EvaluationTemplateItem::SECTION_MANAGER,
            'question_text' => '',
            'weight' => '0',
            'sort_order' => (string) $next,
        ];
    }

    public function removeItemRow(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        $this->validate([
            'name' => 'required|string|min:2|max:120',
            'is_active' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.section' => 'required|in:مدير,موارد',
            'items.*.question_text' => 'required|string|min:2|max:500',
            'items.*.weight' => 'required|integer|min:1|max:100',
            'items.*.sort_order' => 'nullable|integer|min:1|max:100',
        ], [
            'name.required' => 'اسم القالب مطلوب.',
            'items.required' => 'أضف بنداً واحداً على الأقل.',
            'items.*.question_text.required' => 'نص السؤال مطلوب.',
            'items.*.weight.required' => 'الوزن مطلوب.',
        ]);

        $payload = collect($this->items)->map(fn (array $row, int $i) => [
            'section' => $row['section'],
            'question_text' => trim($row['question_text']),
            'weight' => (int) $row['weight'],
            'sort_order' => (int) ($row['sort_order'] ?: ($i + 1)),
        ])->values()->all();

        $service = app(QuarterlyEvaluationService::class);

        try {
            if ($this->editingId) {
                $service->updateTemplate(
                    EvaluationTemplate::findOrFail($this->editingId),
                    $this->name,
                    $payload,
                    $this->is_active,
                );
                $message = 'تم تحديث قالب التقييم';
            } else {
                $service->createTemplate($this->name, $payload, $this->is_active);
                $message = 'تم إنشاء قالب التقييم';
            }
        } catch (\InvalidArgumentException $e) {
            $this->addError('items', $e->getMessage());

            return;
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function toggleActive(int $id): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $template = EvaluationTemplate::findOrFail($id);
        $template->update(['is_active' => ! $template->is_active]);
        $this->dispatch('toast', type: 'success', message: $template->is_active ? 'فُعّل القالب' : 'أُوقف القالب');
    }

    public function render(): View
    {
        $templates = EvaluationTemplate::query()
            ->withCount('items')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.hr.evaluation-templates-index', [
            'templates' => $templates,
            'sections' => EvaluationTemplateItem::SECTIONS,
            'weightsTotal' => collect($this->items)->sum(fn ($r) => (int) ($r['weight'] ?? 0)),
        ]);
    }
}
