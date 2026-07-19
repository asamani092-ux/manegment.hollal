<?php

namespace App\Livewire\Documents;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\Document;
use App\Models\OfficialDutiesDocument;
use App\Services\DocumentLibraryService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Spec-07 + amendments HR-5 — السياسات + نشر ملف المهام الرسمي.
 * Time: O(n) | Space: O(n)
 */
class DocumentPoliciesIndex extends Component
{
    use AuthorizesRequests;
    use UsesDsPagination;
    use WithFileUploads;
    use WithPagination;

    public string $policyTitle = '';

    public string $reviewDate = '';

    public ?TemporaryUploadedFile $policyFile = null;

    public ?TemporaryUploadedFile $dutiesFile = null;

    public function mount(): void
    {
        $this->authorize('documents.policies.manage');
    }

    public function savePolicy(): void
    {
        $this->authorize('documents.policies.manage');

        $this->validate([
            'policyTitle' => 'required|string|max:255',
            'reviewDate' => 'nullable|date',
            'policyFile' => 'required|file|max:10240|mimes:pdf,doc,docx',
        ]);

        $path = $this->policyFile->store('documents/policies', 'local');

        $document = Document::create([
            'title' => $this->policyTitle,
            'category' => 'سياسة',
            'confidentiality' => 'department',
            'uploader_id' => auth()->id(),
            'path' => $path,
            'is_policy' => true,
            'review_date' => $this->reviewDate !== '' ? $this->reviewDate : null,
        ]);

        app(DocumentLibraryService::class)->markAsPolicy(
            $document,
            $this->reviewDate !== '' ? $this->reviewDate : null,
        );

        $this->reset(['policyTitle', 'reviewDate', 'policyFile']);
        $this->dispatch('ds-toast', message: 'تم حفظ السياسة');
    }

    public function publishDuties(): void
    {
        $this->authorize('documents.policies.manage');

        $this->validate([
            'dutiesFile' => 'required|file|max:10240|mimes:pdf',
        ]);

        app(DocumentLibraryService::class)->publishDutiesFile($this->dutiesFile, auth()->user());

        $this->dutiesFile = null;
        $this->dispatch('ds-toast', message: 'نُشر ملف المهام الرسمي — يظهر رابط في الرئيسية');
    }

    public function render(): View
    {
        return view('livewire.documents.document-policies-index', [
            'policies' => Document::query()
                ->where('is_policy', true)
                ->orderByDesc('id')
                ->paginate(15),
            'dutiesVersions' => OfficialDutiesDocument::query()
                ->orderByDesc('version')
                ->limit(10)
                ->get(),
        ])->layout('layouts.app', ['title' => 'السياسات وملف المهام']);
    }
}
