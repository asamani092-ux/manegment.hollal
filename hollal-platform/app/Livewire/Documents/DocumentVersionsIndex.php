<?php

namespace App\Livewire\Documents;

use App\Models\DocumentVersion;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/** Document versions inventory. Time: O(n) | Space: O(page). */
class DocumentVersionsIndex extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(
            auth()->user()->can('documents.manage-versions')
            || auth()->user()->can('documents.view'),
            403
        );
    }

    public function render(): View
    {
        return view('livewire.documents.document-versions-index', [
            'versions' => DocumentVersion::query()
                ->select(['id', 'document_id', 'version', 'change_note', 'uploaded_by', 'created_at'])
                ->with(['document:id,title'])
                ->latest()
                ->paginate(20),
        ]);
    }
}
