<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 07-B1 — one stored revision of a document. Older versions are never
 * overwritten; the document points at the current one.
 */
class DocumentVersion extends Model
{
    /** @var list<string> */
    protected $fillable = ['document_id', 'version', 'path', 'change_note', 'uploaded_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['version' => 'integer'];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
