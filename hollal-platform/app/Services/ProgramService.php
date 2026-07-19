<?php

namespace App\Services;

use App\Models\Program;
use App\Models\ProgramPrice;
use App\Models\ProgramVersion;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 06A-B1 — program card operations: versioning (history is never overwritten),
 * service prices, and the «مشروع تطوير» internal development project.
 */
class ProgramService
{
    /**
     * Record a new version. The previous version stays in the archive with its
     * own snapshot; only `is_current` moves.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function createVersion(
        Program $program,
        string $label,
        ?User $editor = null,
        ?string $reason = null,
        array $snapshot = [],
    ): ProgramVersion {
        return DB::transaction(function () use ($program, $label, $editor, $reason, $snapshot) {
            $program->versions()->where('is_current', true)->update(['is_current' => false]);

            $version = $program->versions()->create([
                'version_label' => $label,
                'changed_by' => $editor?->id,
                'change_reason' => $reason,
                'is_current' => true,
                'snapshot' => $snapshot === [] ? $this->snapshot($program) : $snapshot,
            ]);

            $program->forceFill(['current_version_id' => $version->id])->save();

            return $version;
        });
    }

    public function approveVersion(ProgramVersion $version, User $approver): ProgramVersion
    {
        $version->forceFill([
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ])->save();

        return $version;
    }

    /**
     * Replace the program's price list. Every call is a versioned change.
     *
     * @param  array<string, float|int|string>  $prices  service_type => unit price
     */
    public function setPrices(Program $program, array $prices, ?User $editor = null): void
    {
        DB::transaction(function () use ($program, $prices, $editor) {
            foreach ($prices as $service => $price) {
                if (! in_array($service, ProgramPrice::SERVICES, true)) {
                    throw new \InvalidArgumentException('نوع خدمة غير معروف: '.$service);
                }

                ProgramPrice::updateOrCreate(
                    ['program_id' => $program->id, 'service_type' => $service],
                    ['unit_price' => (float) $price, 'is_active' => true],
                );
            }

            $this->createVersion(
                $program->fresh(),
                $this->nextVersionLabel($program),
                $editor,
                'تحديث الأسعار',
            );
        });
    }

    /**
     * «مشروع تطوير» — creates an internal project bound to the program so its
     * development work is tracked like any other project.
     */
    public function createDevelopmentProject(Program $program, User $manager, ?string $name = null): Project
    {
        return Project::create([
            'name' => $name ?? ('مشروع تطوير: '.$program->name),
            'program_id' => $program->id,
            'kind' => 'داخلي',
            'manager_id' => $manager->id,
            'status' => 'تخطيط',
            'idea_goal' => 'تطوير البرنامج وتحديث إصداره',
            'start_date' => now()->toDateString(),
        ]);
    }

    public function nextVersionLabel(Program $program): string
    {
        return 'v'.($program->versions()->count() + 1);
    }

    /** @return array<string, mixed> */
    private function snapshot(Program $program): array
    {
        return [
            'name' => $program->name,
            'stage' => $program->stage,
            'sessions_count' => $program->sessions_count,
            'hours_count' => $program->hours_count,
            'platform_url' => $program->platform_url,
            'prices' => $program->prices()->pluck('unit_price', 'service_type')->all(),
        ];
    }
}
