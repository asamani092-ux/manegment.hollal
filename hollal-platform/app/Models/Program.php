<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends Model
{
    use SoftDeletes;

    public const STAGE_DEVELOPMENT = 'تطوير';

    public const STAGE_ACTIVE = 'نشط';

    public const STAGE_SUSPENDED = 'موقوف';

    /** @var list<string> */
    protected $fillable = [
        'name', 'description', 'stage', 'target_audience',
        'sessions_count', 'hours_count', 'execution_requirements',
        'platform_url', 'platform_notes', 'platform_steps', 'current_version_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sessions_count' => 'integer',
            'hours_count' => 'integer',
        ];
    }

    /** @return HasMany<ProgramVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ProgramVersion::class);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<ProgramPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(ProgramPrice::class);
    }

    /** @return HasMany<ProgramFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(ProgramFile::class);
    }

    /** @return BelongsTo<ProgramVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class, 'current_version_id');
    }

    /**
     * 06A-B1 — organizations that executed this program, derived from its
     * projects' partnerships. Never entered by hand.
     *
     * @return Collection<int, Organization>
     */
    public function executingOrganizations(): Collection
    {
        return Organization::query()
            ->whereIn('id', Partnership::query()
                ->whereIn('project_id', $this->projects()->select('id'))
                ->whereNotNull('organization_id')
                ->select('organization_id'))
            ->orderBy('name')
            ->get();
    }
}
