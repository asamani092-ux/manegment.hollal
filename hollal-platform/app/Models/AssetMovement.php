<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMovement extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'asset_id', 'from_holder_id', 'to_holder_id', 'moved_at', 'reason',
        'handover_document_path', 'movement_type',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['moved_at' => 'datetime'];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
