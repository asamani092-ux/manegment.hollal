<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Partnership with magic link token — soft deletes only.
 */
class Partnership extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'stage',
        'entity_name',
        'contact_person',
        'contact_phone',
        'magic_link_token',
        'token_expires_at',
        'type_quantity',
        'halal_commitments',
        'partner_commitments',
        'pricing_amount',
        'contract_pdf',
        'project_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'pricing_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
