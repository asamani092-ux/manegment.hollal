<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 05-B7 — the handoff record produced by «توليد مشروع». 06B-B1 consumes it.
 */
class ProjectGenerationRequest extends Model
{
    public const STATUS_PENDING = 'معلق';

    public const STATUS_GENERATED = 'مولّد';

    public const STATUS_FAILED = 'فشل';

    /** @var list<string> */
    protected $fillable = [
        'partnership_id', 'program_id', 'quote_id', 'included_services', 'launch_date',
        'project_manager_id', 'status', 'project_id', 'failure_reason', 'requested_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'included_services' => 'array',
            'launch_date' => 'date',
        ];
    }

    /** @return BelongsTo<Partnership, $this> */
    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class);
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
