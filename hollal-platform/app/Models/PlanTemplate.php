<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 06A-B2 — a plan template (خطة حلل / خطة الجهة / داخلي). Generation reads the
 * current version only, and only once the review flag has been cleared.
 */
class PlanTemplate extends Model
{
    use SoftDeletes;

    public const KIND_HOLLAL = 'خطة حلل';

    public const KIND_ENTITY = 'خطة الجهة';

    public const KIND_INTERNAL = 'داخلي';

    /** @var list<string> */
    protected $fillable = [
        'name', 'kind', 'program_id', 'needs_review', 'review_note',
        'reviewed_by', 'reviewed_at', 'current_version_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'needs_review' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return HasMany<TemplateVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class);
    }

    /** @return BelongsTo<TemplateVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class, 'current_version_id');
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
