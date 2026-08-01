<?php

namespace App\Livewire\Documents;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\DocumentLibraryService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Document versions inventory + upload new revision (keeps old rows).
 * Time: O(n) | Space: O(page).
 */
class DocumentVersionsIndex extends Component
{
    use WithFileUploads;
    use WithPagination;

    public bool $showUpload = false;

    public ?int $document_id = null;

    public string $change_note = '';

    public ?TemporaryUploadedFile $uploadFile = null;

    public function mount(): void
    {
        abort_unless(
            auth()->user()->can('documents.manage-versions')
            || auth()->user()->can('documents.view'),
            403
        );
    }

    public function openUpload(): void
    {
        abort_unless(
            auth()->user()->can('documents.manage-versions')
            || auth()->user()->can('documents.create'),
            403
        );
        $this->reset(['document_id', 'change_note', 'uploadFile']);
        $this->showUpload = true;
    }

    public function saveVersion(): void
    {
        abort_unless(
            auth()->user()->can('documents.manage-versions')
            || auth()->user()->can('documents.create'),
            403
        );

        $this->validate([
            'document_id' => 'required|exists:documents,id',
            'change_note' => 'nullable|string|max:500',
            'uploadFile' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $user = auth()->user();
        $document = Document::query()
            ->visibleTo($user)
            ->whereKey($this->document_id)
            ->firstOrFail();

        $previousCount = $document->versions()->count();

        app(DocumentLibraryService::class)->storeNewVersionFromUpload(
            $document,
            $this->uploadFile,
            $this->change_note ?: null,
            auth()->user()
        );

        $this->showUpload = false;
        $this->dispatch(
            'toast',
            type: 'success',
            message: 'أُضيفت نسخة جديدة — النسخ السابقة محفوظة ('.$previousCount.' سابقة)'
        );
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.documents.document-versions-index', [
            'versions' => DocumentVersion::query()
                ->select(['id', 'document_id', 'version', 'change_note', 'uploaded_by', 'created_at'])
                ->with(['document:id,title'])
                ->whereHas('document', fn ($q) => $q->visibleTo($user))
                ->latest()
                ->paginate(20),
            'documents' => Document::query()
                ->visibleTo($user)
                ->orderBy('title')
                ->limit(200)
                ->get(['id', 'title']),
            'canUpload' => $user->can('documents.manage-versions')
                || $user->can('documents.create'),
        ]);
    }
}
