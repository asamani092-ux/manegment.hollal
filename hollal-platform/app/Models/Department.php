<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Organizational department — all deletes are soft.
 */
class Department extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'owner_user_id'];

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
