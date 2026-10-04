<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeOnboardingItem extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'reference_item_id', 'status', 'task_id', 'acted_by', 'acted_at'];

    /** @return BelongsTo<ReferenceItem, $this> */
    public function referenceItem(): BelongsTo
    {
        return $this->belongsTo(ReferenceItem::class);
    }
}
