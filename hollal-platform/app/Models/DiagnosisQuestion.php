<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiagnosisQuestion extends Model
{
    public const TYPES = ['text', 'number', 'textarea'];

    /** @var list<string> */
    protected $fillable = ['key', 'label', 'type', 'required', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<DiagnosisAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(DiagnosisAnswer::class, 'question_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
