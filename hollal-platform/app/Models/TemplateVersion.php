<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateVersion extends Model
{
    /** @var list<string> */
    protected $fillable = ['plan_template_id', 'version_label', 'is_current', 'change_reason', 'created_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_current' => 'boolean'];
    }

    /** @return BelongsTo<PlanTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(PlanTemplate::class, 'plan_template_id');
    }

    /** @return HasMany<TemplateItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(TemplateItem::class);
    }

    /** @return HasMany<TemplateItem, $this> */
    public function rootItems(): HasMany
    {
        return $this->items()->whereNull('parent_id')->orderBy('position');
    }
}
