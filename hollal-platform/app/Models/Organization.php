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

    /** @return HasMany<OrganizationImpactRecord, $this> */
    public function impactRecords(): HasMany
    {
        return $this->hasMany(OrganizationImpactRecord::class);
    }

    /**
     * 05-B1 — projects reached through this organization's partnerships.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Project>
     */
    public function projects()
    {
        return Project::query()
            ->whereIn('id', $this->partnerships()->whereNotNull('project_id')->select('project_id'))
            ->orderByDesc('id')
            ->get();
    }

    /**
     * 05-B1 — cumulative impact: totals rolled up from every project record.
     *
     * @return array{beneficiaries: int, improvement_percent: ?float, satisfaction_percent: ?float, records: int}
     */
    public function cumulativeImpact(): array
    {
        $records = $this->impactRecords()->get();

        return [
            'beneficiaries' => (int) $records->sum('beneficiaries'),
            'improvement_percent' => $records->whereNotNull('improvement_percent')->avg('improvement_percent'),
            'satisfaction_percent' => $records->whereNotNull('satisfaction_percent')->avg('satisfaction_percent'),
            'records' => $records->count(),
        ];
    }

    /**
     * 05-B1 — communication timeline: stage moves and meetings, newest first.
     *
     * @return \Illuminate\Support\Collection<int, array{at: \Illuminate\Support\Carbon, kind: string, title: string}>
     */
    public function timeline(): \Illuminate\Support\Collection
    {
        $partnershipIds = $this->partnerships()->pluck('id');

        $stageEvents = PartnershipStageLog::query()
            ->whereIn('partnership_id', $partnershipIds)
            ->get()
            ->map(fn (PartnershipStageLog $log) => [
                'at' => $log->created_at,
                'kind' => 'مرحلة',
                'title' => 'انتقال إلى: '.(Partnership::STAGE_LABELS[$log->to_stage] ?? '—')
                    .($log->note ? ' — '.$log->note : ''),
            ]);

        $meetings = Meeting::query()
            ->whereIn('partnership_id', $partnershipIds)
            ->get()
            ->map(fn (Meeting $meeting) => [
                'at' => $meeting->created_at,
                'kind' => 'اجتماع',
                'title' => $meeting->title,
            ]);

        return $stageEvents->concat($meetings)->sortByDesc('at')->values();
    }
}
