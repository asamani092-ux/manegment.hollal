<?php

namespace App\Livewire\Documents;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Documents — upload, filter, confidentiality-scoped listing.
 * Time: O(n) per page | Space: O(n).
 */
class DocumentsIndex extends Component
{
    use AuthorizesRequests;
    use UsesDsPagination;
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $categoryFilter = '';

    public bool $showUploadModal = false;

    public string $title = '';

    public string $category = '';

    public ?int $project_id = null;

    public string $confidentiality = 'team';

    public ?TemporaryUploadedFile $uploadFile = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'categoryFilter' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', Document::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function openUpload(): void
    {
        $this->authorize('create', Document::class);
        $this->resetUploadForm();
        $this->showUploadModal = true;
    }

    public function saveUpload(): void
    {
        $this->authorize('create', Document::class);

        $this->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'project_id' => 'nullable|exists:projects,id',
            'confidentiality' => 'required|in:team,department,managers',
            'uploadFile' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $path = $this->uploadFile->store('documents', 'local');

        Document::create([
            'title' => $this->title,
            'category' => $this->category,
            'project_id' => $this->project_id,
            'confidentiality' => $this->confidentiality,
            'uploader_id' => auth()->id(),
            'path' => $path,
        ]);

        $this->closeUploadModal();
        $this->dispatch('toast', type: 'success', message: 'تم رفع المستند');
    }

    public function delete(int $id): void
    {
        $document = Document::findOrFail($id);
        $this->authorize('delete', $document);
        $document->delete();
        $this->dispatch('toast', type: 'success', message: 'تم حذف المستند');
    }

    public function closeUploadModal(): void
    {
        $this->showUploadModal = false;
        $this->resetUploadForm();
    }

    protected function resetUploadForm(): void
    {
        $this->title = '';
        $this->category = '';
        $this->project_id = null;
        $this->confidentiality = 'team';
        $this->uploadFile = null;
        $this->resetValidation();
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.documents.documents-index', [
            'documents' => Document::query()
                ->select(['id', 'title', 'category', 'project_id', 'confidentiality', 'uploader_id', 'path', 'created_at'])
                ->with(['project:id,name', 'uploader:id,name'])
                ->visibleTo($user)
                ->when($this->search, fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
                ->when($this->categoryFilter, fn ($q) => $q->where('category', $this->categoryFilter))
                ->latest()
                ->paginate(10),
            'categories' => Document::query()
                ->visibleTo($user)
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.app', ['title' => 'المستندات']);
    }
}
