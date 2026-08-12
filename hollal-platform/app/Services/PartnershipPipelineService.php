<?php

namespace App\Services;

use App\Models\Partnership;
use App\Models\PartnershipStageLog;
use App\Models\User;
use App\Notifications\PartnershipStageChanged;
use App\Notifications\PartnershipStale;
use App\Support\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * 05-B2 — the seven-stage journey. Every transition is manual (except the
 * automatic move to «تعاقد» once the contract conditions are met) and every
 * transition is logged.
 */
class PartnershipPipelineService
{
    /**
     * Move a partnership to another stage and write the log row.
     */
    public function moveTo(Partnership $partnership, int $stage, ?User $actor = null, ?string $note = null): PartnershipStageLog
    {
        if (! array_key_exists($stage, Partnership::STAGE_LABELS)) {
            throw new \InvalidArgumentException('مرحلة غير معروفة');
        }

        return DB::transaction(function () use ($partnership, $stage, $actor, $note) {
            $from = $partnership->stage;

            $log = PartnershipStageLog::create([
                'partnership_id' => $partnership->id,
                'from_stage' => $from,
                'to_stage' => $stage,
                'note' => $note,
                'changed_by' => $actor?->id,
            ]);

            $attributes = ['stage' => $stage, 'stage_entered_at' => now()];

            if ($stage === Partnership::STAGE_STALLED) {
                $attributes['stalled_reason'] = $note;
            }

            if ($stage === Partnership::STAGE_CLOSED) {
                $attributes['closed_reason'] = $note;
            }

            $partnership->forceFill($attributes)->save();

            DB::afterCommit(function () use ($partnership, $from, $stage) {
                $this->notifyOrganization($partnership->fresh(['organization.contacts']), $from, $stage);
            });

            return $log;
        });
    }

    /**
     * Mail the organization's contacts (log driver in trial). Time: O(c) contacts.
     */
    private function notifyOrganization(?Partnership $partnership, ?int $from, int $to): void
    {
        if ($partnership === null) {
            return;
        }

        $emails = collect($partnership->organization?->contacts?->pluck('email') ?? [])
            ->filter(fn ($email) => is_string($email) && $email !== '')
            ->unique()
            ->values();

        foreach ($emails as $email) {
            Notification::route('mail', $email)
                ->notify(new PartnershipStageChanged($partnership, $from, $to));
        }
    }

    /** Kanban columns keyed by stage. */
    public function board(): Collection
    {
        $partnerships = Partnership::query()
            ->whereIn('stage', Partnership::PIPELINE_STAGES)
            ->with(['organization', 'owner'])
            ->get();

        return collect(Partnership::PIPELINE_STAGES)->mapWithKeys(fn (int $stage) => [
            $stage => $partnerships->where('stage', $stage)->values(),
        ]);
    }

    public function staleThresholdDays(): int
    {
        return (int) Setting::get('notifications.partnership_stale_days', 14);
    }

    /**
     * Partnerships sitting in a pipeline stage longer than the threshold.
     *
     * @return Collection<int, Partnership>
     */
    public function stale(): Collection
    {
        $threshold = $this->staleThresholdDays();

        return Partnership::query()
            ->whereIn('stage', Partnership::PIPELINE_STAGES)
            ->get()
            ->filter(fn (Partnership $p) => $p->stageAgeDays() >= $threshold)
            ->values();
    }

    /**
     * Notify the follow-up owner about every stale partnership.
     *
     * @return list<int> alerted partnership ids
     */
    public function fireStaleAlerts(): array
    {
        $alerted = [];

        foreach ($this->stale() as $partnership) {
            $recipients = collect();

            if ($partnership->owner_id && $owner = User::find($partnership->owner_id)) {
                $recipients->push($owner);
            }

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new PartnershipStale($partnership, $partnership->stageAgeDays()));
            }

            $alerted[] = $partnership->id;
        }

        return $alerted;
    }
}
