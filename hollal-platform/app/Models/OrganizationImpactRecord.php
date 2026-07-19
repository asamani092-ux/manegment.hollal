<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 05-B1 — cumulative impact record; rows are pushed up from project
 * measurement (06B-B4), never typed in by hand.
 */
class OrganizationImpactRecord extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'organization_id', 'project_id', 'program_id', 'beneficiaries',
        'improvement_percent', 'satisfaction_percent', 'summary',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'beneficiaries' => 'integer',
            'improvement_percent' => 'decimal:2',
            'satisfaction_percent' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
