<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\OfficialDutiesDocument;
use App\Models\User;
use App\Notifications\PolicyReviewDue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * 07-B1 — documents: source linking, versions, and policy review dates.
 */
class DocumentLibraryService
{
    /**
     * Auto-list every document produced by one source record, e.g. all files
     * attached to a given project, contract or meeting.
     *
     * @return Collection<int, Document>
     */
    public function forSource(string $sourceType, int $sourceId): Collection
    {
        return Document::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->orderByDesc('id')
            ->get();
    }

    /** Store a new revision and point the document at it. */
    public function addVersion(Document $document, string $path, ?string $note = null, ?User $uploader = null): DocumentVersion
    {
        return DB::transaction(function () use ($document, $path, $note, $uploader) {
            $next = (int) $document->versions()->max('version') + 1;

            $version = DocumentVersion::create([
                'document_id' => $document->id,
                'version' => $next,
                'path' => $path,
                'change_note' => $note,
                'uploaded_by' => $uploader?->id,
            ]);

            $document->forceFill(['path' => $path, 'current_version' => $next])->save();

            return $version;
        });
    }

    /**
     * Policies whose review date has arrived and that have not been alerted yet.
     *
     * @return Collection<int, Document>
     */
    public function policiesDueForReview(): Collection
    {
        return Document::query()
            ->where('is_policy', true)
            ->whereNotNull('review_date')
            ->whereDate('review_date', '<=', now()->toDateString())
            ->whereNull('review_alert_sent_at')
            ->get();
    }

    /**
     * @return list<int> alerted document ids
     */
    public function firePolicyReviewAlerts(): array
    {
        $alerted = [];

        foreach ($this->policiesDueForReview() as $policy) {
            $recipients = User::permission('documents.policies.manage')->get();
            if ($recipients->isEmpty()) {
                $recipients = User::permission('documents.create')->get();
            }

            foreach ($recipients as $recipient) {
                $recipient->notify(new PolicyReviewDue($policy));
            }

            $policy->forceFill(['review_alert_sent_at' => now()])->save();
            $alerted[] = $policy->id;
        }

        return $alerted;
    }

    /** مكتبة النماذج — رفع قالب معتمد. Time: O(1) */
    public function storeTemplate(
        string $title,
        string $path,
        ?string $category = null,
        ?string $description = null,
        ?User $uploader = null,
    ): DocumentTemplate {
        return DocumentTemplate::create([
            'title' => $title,
            'category' => $category,
            'path' => $path,
            'description' => $description,
            'uploaded_by' => $uploader?->id,
        ]);
    }

    /** تعليم مستند كسياسة مع تاريخ مراجعة. Time: O(1) */
    public function markAsPolicy(Document $document, ?string $reviewDate = null): Document
    {
        $document->forceFill([
            'is_policy' => true,
            'review_date' => $reviewDate,
            'review_alert_sent_at' => null,
        ])->save();

        return $document->fresh();
    }

    /**
     * نشر ملف مهام رسمي جديد (إصدار تراكمي). Time: O(1)
     */
    public function publishDutiesFile(UploadedFile $file, User $publisher): OfficialDutiesDocument
    {
        $next = (int) OfficialDutiesDocument::query()->max('version') + 1;
        $path = $file->store('official-duties', 'local');

        return OfficialDutiesDocument::create([
            'version' => $next,
            'file_path' => $path,
            'published_at' => now(),
            'published_by' => $publisher->id,
        ]);
    }

    /** نسخة مستند جديدة على القرص الخاص. Time: O(1) */
    public function storeNewVersionFromUpload(
        Document $document,
        UploadedFile $file,
        ?string $note = null,
        ?User $uploader = null,
    ): DocumentVersion {
        $path = $file->store('documents/'.$document->id.'/versions', 'local');

        return $this->addVersion($document, $path, $note, $uploader);
    }
}
