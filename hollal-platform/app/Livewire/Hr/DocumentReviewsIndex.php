<?php

namespace App\Livewire\Hr;

use App\Models\EmployeeDocument;
use App\Services\DocumentReviewService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * HR queue for uploaded documents. Time: O(n) pending | Space: O(n).
 */
class DocumentReviewsIndex extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->can('hr.documents.review'), 403);
    }

    public function approve(int $id): void
    {
        abort_unless(auth()->user()->can('hr.documents.review'), 403);
        app(DocumentReviewService::class)->approve(EmployeeDocument::query()->findOrFail($id), auth()->user());
    }

    public function reject(int $id, string $reason): void
    {
        abort_unless(auth()->user()->can('hr.documents.review'), 403);
        app(DocumentReviewService::class)->reject(EmployeeDocument::query()->findOrFail($id), auth()->user(), $reason);
    }

    public function render(): View
    {
        return view('livewire.hr.document-reviews-index', [
            'rows' => EmployeeDocument::query()
                ->where('status', 'pending_review')
                ->with('user:id,name')
                ->latest('id')
                ->limit(50)
                ->get(),
        ])->layout('layouts.app', ['title' => 'وثائق بانتظار المراجعة']);
    }
}
