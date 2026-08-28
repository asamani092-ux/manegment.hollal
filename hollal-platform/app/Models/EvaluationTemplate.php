<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationTemplate extends Model
{
    /** @var list<string> */
    protected $fillable = ['name', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<EvaluationTemplateItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(EvaluationTemplateItem::class)->orderBy('sort_order');
    }

    /** @return HasMany<EvaluationCycle, $this> */
    public function cycles(): HasMany
    {
        return $this->hasMany(EvaluationCycle::class);
    }

    public function weightsSum(): int
    {
        return (int) $this->items()->sum('weight');
    }
}
