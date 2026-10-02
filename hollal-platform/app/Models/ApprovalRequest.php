<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRequest extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'approvable_type', 'approvable_id', 'rule_id', 'status', 'current_step', 'submitted_by',
    ];

    /** @return MorphTo<Model, $this> */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<ApprovalRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(ApprovalRule::class, 'rule_id');
    }

    /** @return HasMany<ApprovalRequestStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalRequestStep::class)->orderBy('step_index');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
