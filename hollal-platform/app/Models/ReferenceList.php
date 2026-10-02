<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferenceList extends Model
{
    /** @var list<string> */
    protected $fillable = ['key', 'name_ar', 'description_ar', 'schema'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'schema' => 'array',
        ];
    }

    /** @return HasMany<ReferenceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ReferenceItem::class);
    }
}
