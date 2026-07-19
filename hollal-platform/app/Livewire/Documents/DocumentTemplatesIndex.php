<?php

namespace App\Livewire\Documents;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\DocumentTemplate;
use App\Services\DocumentLibraryService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Spec-07 — مكتبة النماذج الجاهزة (تنزيل فقط للمستخدمين؛ إدارة بالصلاحية).
 * Time: O(n) per page | Space: O(n)
 */
class DocumentTemplatesIndex extends Component
{
    use AuthorizesRequests;
    use UsesDsPagination;
    use WithFileUploads;
    use WithPagination;

    public string $title = '';

    public string $category = '';

    public string $description = '';

    public ?TemporaryUploadedFile $uploadFile = null;

    public function mount(): void
    {
        abort_unless(
            auth()->user()->can('documents.view') || auth()->user()->can('documents.templates.manage'),
            403
        );
    }

    public function save(): void
    {
        $this->authorize('documents.templates.manage');

        $this->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
            'uploadFile' => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx',
        ]);

        $path = $this->uploadFile->store('document-templates', 'local');

        app(DocumentLibraryService::class)->storeTemplate(
            $this->title,
            $path,
            $this->category !== '' ? $this->category : null,
            $this->description !== '' ? $this->description : null,
            auth()->user(),
        );

        $this->reset(['title', 'category', 'description', 'uploadFile']);
        $this->dispatch('ds-toast', message: 'تم رفع النموذج المعتمد');
    }

    public function delete(int $id): void
    {
        $this->authorize('documents.templates.manage');

        $template = DocumentTemplate::findOrFail($id);
        $template->delete();

        $this->dispatch('ds-toast', message: 'أُرشف النموذج');
    }

    public function render(): View
    {
        return view('livewire.documents.document-templates-index', [
            'templates' => DocumentTemplate::query()->orderByDesc('id')->paginate(20),
            'canManage' => auth()->user()->can('documents.templates.manage'),
        ])->layout('layouts.app', ['title' => 'مكتبة النماذج']);
    }
}
