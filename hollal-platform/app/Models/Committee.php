<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 09-B1 — a committee (لجنة). Meetings of type «لجنة» link here.
 */
class Committee extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['name', 'mandate', 'chair_id', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'committee_user')
            ->withPivot('role_label')
            ->withTimestamps();
    }

    /** @return BelongsTo<User, $this> */
    public function chair(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chair_id');
    }

    /** @return HasMany<Meeting, $this> */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }
}
