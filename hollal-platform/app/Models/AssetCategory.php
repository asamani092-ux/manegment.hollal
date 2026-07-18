<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetCategory extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['name_ar', 'can_be_custody', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'can_be_custody' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @param Builder<AssetCategory> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
