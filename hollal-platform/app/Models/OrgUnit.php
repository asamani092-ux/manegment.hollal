<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 09-B1 — a node of the org tree: إدارة ← قسم ← وظيفة. A وظيفة node doubles
 * as the job card (purpose, responsibilities, requirements).
 */
class OrgUnit extends Model
{
    use SoftDeletes;

    public const LEVEL_TOP = 'الإدارة العليا';

    public const LEVEL_ADMINISTRATION = 'إدارة';

    /** Structural mid-level (Arabic label «قسم»; constant kept for callers). */
    public const LEVEL_UNIT = 'قسم';

    public const LEVEL_JOB = 'وظيفة';

    /** @var array<string, ?string> level => the level allowed beneath it */
    public const CHILD_LEVEL = [
        self::LEVEL_TOP => self::LEVEL_ADMINISTRATION,
        self::LEVEL_ADMINISTRATION => self::LEVEL_UNIT,
        self::LEVEL_UNIT => self::LEVEL_JOB,
        self::LEVEL_JOB => null,
    ];

    /** @var list<string> */
    protected $fillable = [
        'name', 'level', 'parent_id', 'manager_id', 'default_role',
        'job_purpose', 'job_responsibilities', 'job_requirements', 'position',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'job_responsibilities' => 'array',
            'job_requirements' => 'array',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<OrgUnit, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<OrgUnit, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    /** @return HasMany<User, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'org_unit_id');
    }

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function isJobCard(): bool
    {
        return $this->level === self::LEVEL_JOB;
    }
}
