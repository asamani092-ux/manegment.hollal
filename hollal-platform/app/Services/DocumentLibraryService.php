<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Notifications\PolicyReviewDue;
use Illuminate\Database\Eloquent\Collection;
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
            $recipients = User::permission('documents.create')->get();

            foreach ($recipients as $recipient) {
                $recipient->notify(new PolicyReviewDue($policy));
            }

            $policy->forceFill(['review_alert_sent_at' => now()])->save();
            $alerted[] = $policy->id;
        }

        return $alerted;
    }
}
