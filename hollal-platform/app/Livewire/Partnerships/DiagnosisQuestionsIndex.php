<?php

namespace App\Livewire\Partnerships;

use App\Models\DiagnosisQuestion;
use App\Services\DiagnosisQuestionService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * Admin CRUD for partner-portal diagnosis questions.
 * Time: O(q) | Space: O(q)
 */
class DiagnosisQuestionsIndex extends Component
{
    use AuthorizesRequests;

    public string $label = '';

    public string $type = 'text';

    public bool $required = true;

    public int $sort_order = 0;

    public ?int $editingId = null;

    public function mount(): void
    {
        $this->authorize('partnerships.organizations.view');
    }

    public function save(DiagnosisQuestionService $questions): void
    {
        $this->authorize('partnerships.organizations.manage');

        $this->validate([
            'label' => 'required|string|max:255',
            'type' => 'required|in:text,number,textarea',
            'required' => 'boolean',
            'sort_order' => 'integer|min:0|max:999',
        ], [], ['label' => 'نص السؤال']);

        $payload = [
            'label' => $this->label,
            'type' => $this->type,
            'required' => $this->required,
            'sort_order' => $this->sort_order,
            'is_active' => true,
        ];

        if ($this->editingId) {
            $questions->update(DiagnosisQuestion::query()->findOrFail($this->editingId), $payload);
            $this->dispatch('ds-toast', message: 'تم تحديث السؤال');
        } else {
            $questions->create($payload);
            $this->dispatch('ds-toast', message: 'أُضيف سؤال جديد');
        }

        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $question = DiagnosisQuestion::query()->findOrFail($id);
        $this->editingId = $question->id;
        $this->label = $question->label;
        $this->type = $question->type;
        $this->required = $question->required;
        $this->sort_order = $question->sort_order;
    }

    public function toggle(int $id, DiagnosisQuestionService $questions): void
    {
        $this->authorize('partnerships.organizations.manage');
        $question = DiagnosisQuestion::query()->findOrFail($id);
        $questions->update($question, ['is_active' => ! $question->is_active]);
        $this->dispatch('ds-toast', message: $question->fresh()->is_active ? 'السؤال ظاهر في البوابة' : 'السؤال مخفي من البوابة');
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->label = '';
        $this->type = 'text';
        $this->required = true;
        $this->sort_order = 0;
    }

    public function render(): View
    {
        return view('livewire.partnerships.diagnosis-questions-index', [
            'questions' => DiagnosisQuestion::query()->orderBy('sort_order')->orderBy('id')->get(),
        ])->layout('layouts.app', ['title' => 'استبانة التشخيص']);
    }
}
