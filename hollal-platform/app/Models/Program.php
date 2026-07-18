<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name', 'description', 'stage', 'target_audience',
        'sessions_count', 'hours_count', 'execution_requirements',
        'platform_url', 'platform_notes',
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
}
