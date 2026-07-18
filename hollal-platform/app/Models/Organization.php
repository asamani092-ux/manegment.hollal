<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['name', 'type', 'city', 'roles', 'notes'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'roles' => 'array',
        ];
    }

    /** @return HasMany<OrganizationContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(OrganizationContact::class);
    }

    /** @return HasMany<Partnership, $this> */
    public function partnerships(): HasMany
    {
        return $this->hasMany(Partnership::class);
    }
}
