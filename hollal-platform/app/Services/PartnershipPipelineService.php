<?php

namespace App\Services;

use App\Models\Partnership;
use App\Models\PartnershipContract;
use App\Models\PartnershipPayment;
use App\Models\PartnershipStageLog;
use App\Models\User;
use App\Notifications\PartnershipStageChanged;
use App\Notifications\PartnershipStale;
use App\Support\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * 05-B2 — the seven-stage journey. Manual and system transitions share
 * moveTo(). Auto-advance never regresses. تعاقد stays locked to its conditions.
 */
class PartnershipPipelineService
{
    /**
     * Move a partnership to another stage and write the log row.
     * Time: O(1) + O(c) contacts on notify | Space: O(1)
     */
    public function moveTo(Partnership $partnership, int $stage, ?User $actor = null, ?string $note = null): PartnershipStageLog
    {
        if (! array_key_exists($stage, Partnership::STAGE_LABELS)) {
            throw new \InvalidArgumentException('مرحلة غير معروفة');
        }

        if ($stage === Partnership::STAGE_CONTRACTED) {
            throw new \InvalidArgumentException('مرحلة التعاقد مدمجة في عرض السعر');
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
     * Forward-only system advance. No-op when already at or past $stage,
     * or when the journey is stalled/closed.
     */
    public function advanceIfBefore(Partnership $partnership, int $stage, ?User $actor = null, ?string $note = null): ?PartnershipStageLog
    {
        $current = (int) ($partnership->stage ?? 0);

        if (in_array($current, [Partnership::STAGE_STALLED, Partnership::STAGE_CLOSED], true)) {
            return null;
        }

        if ($current >= $stage) {
            return null;
        }

        return $this->moveTo($partnership->fresh() ?? $partnership, $stage, $actor, $note);
    }

    /**
     * Cumulative renewal: new opportunity linked to the parent. Never overwrites.
     * Time: O(1) | Space: O(1)
     */
    public function openRenewal(Partnership $parent, ?User $actor = null, ?string $note = null): Partnership
    {
        $existing = Partnership::query()->where('renewed_from_id', $parent->id)->first();

        if ($existing) {
            return $existing;
        }

        $renewal = Partnership::create([
            'organization_id' => $parent->organization_id,
            'entity_name' => $parent->entity_name,
            'owner_id' => $parent->owner_id,
            'renewed_from_id' => $parent->id,
            'stage' => Partnership::STAGE_OPPORTUNITY,
            'stage_entered_at' => now(),
        ]);

        $this->moveTo(
            $renewal,
            Partnership::STAGE_OPPORTUNITY,
            $actor,
            $note ?? 'فرصة تجديد مرتبطة بالشراكة #'.$parent->id,
        );

        return $renewal;
    }

    public function assertContractedReady(Partnership $partnership): void
    {
        $contract = $partnership->latestContract()
            ?? $partnership->partnershipContracts()->latest('id')->first();

        if (! $contract || ! $contract->hasSignedCopy()) {
            throw new \RuntimeException('لا تعاقد دون نسخة موقعة مؤكدة');
        }

        if ($contract->requires_first_payment && ! $this->firstPaymentConfirmed($contract)) {
            throw new \RuntimeException('لا تعاقد قبل تأكيد المالية للدفعة الأولى');
        }
    }

    public function firstPaymentConfirmed(PartnershipContract $contract): bool
    {
        $first = $contract->schedule()->orderBy('sequence')->first();

        if (! $first) {
            return false;
        }

        return PartnershipPayment::query()
            ->where('contract_payment_schedule_id', $first->id)
            ->where('status', PartnershipPayment::STATUS_CONFIRMED)
            ->exists();
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

    /** Kanban columns keyed by stage. Legacy contracted rows sit under quote. Time: O(n) | Space: O(n) */
    public function board(): Collection
    {
        $visible = Partnership::PIPELINE_STAGES;
        $partnerships = Partnership::query()
            ->whereIn('stage', [...$visible, Partnership::STAGE_CONTRACTED])
            ->with(['organization', 'owner'])
            ->get();

        return collect($visible)->mapWithKeys(fn (int $stage) => [
            $stage => $partnerships
                ->filter(fn (Partnership $p) => $p->pipelineColumnStage() === $stage)
                ->values(),
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
            ->whereIn('stage', array_diff(Partnership::PIPELINE_STAGES, [Partnership::STAGE_EXECUTION]))
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
