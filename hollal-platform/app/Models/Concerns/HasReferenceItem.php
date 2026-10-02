<?php

namespace App\Models\Concerns;

use App\Models\ReferenceItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the exact reference item version row, never only the code.
 */
trait HasReferenceItem
{
    /** @return BelongsTo<ReferenceItem, $this> */
    public function referenceItem(): BelongsTo
    {
        return $this->belongsTo(ReferenceItem::class, 'reference_item_id');
    }

    /**
     * True when any registered table points at this exact item version.
     * Time: O(tables) | Space: O(1)
     */
    public function isReferenced(): bool
    {
        if (! $this instanceof ReferenceItem) {
            $id = $this->reference_item_id ?? null;

            return $id ? ReferenceItem::query()->find($id)?->isReferenced() ?? false : false;
        }

        $map = config('reference_lists.references', []);

        foreach ($map as $tables) {
            foreach ($tables as $ref) {
                $table = $ref['table'] ?? null;
                $column = $ref['column'] ?? 'reference_item_id';
                if (! is_string($table) || ! Schema::hasTable($table)) {
                    continue;
                }
                if (DB::table($table)->where($column, $this->id)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }
}
