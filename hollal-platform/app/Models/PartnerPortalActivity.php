<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 05-B5 — every portal action, attributed to the partner organization.
 */
class PartnerPortalActivity extends Model
{
    /** @var list<string> */
    protected $fillable = ['partner_link_id', 'partnership_id', 'action', 'metadata', 'ip_address'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /** @return BelongsTo<PartnerLink, $this> */
    public function link(): BelongsTo
    {
        return $this->belongsTo(PartnerLink::class, 'partner_link_id');
    }
}
