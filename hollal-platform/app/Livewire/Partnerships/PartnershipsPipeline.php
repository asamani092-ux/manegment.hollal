<?php

namespace App\Livewire\Partnerships;

use App\Models\Partnership;
use App\Models\Quote;
use App\Services\PartnershipPipelineService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * 05-B2 — the seven-stage pipeline as a Kanban board and a list, with the
 * per-stage form and the stale highlight.
 */
class PartnershipsPipeline extends Component
{
    use AuthorizesRequests;

    public string $view = 'kanban'; // kanban|list

    public bool $showStageModal = false;

    public ?int $movingId = null;

    public ?int $targetStage = null;

    public ?string $stageNote = null;

    public function mount(): void
    {
        $this->authorize('partnerships.pipeline.view');
    }

    public function openStageModal(int $partnershipId): void
    {
        $this->authorize('partnerships.pipeline.manage');
        $partnership = Partnership::findOrFail($partnershipId);

        $this->movingId = $partnership->id;
        $this->targetStage = $partnership->stage ?? Partnership::STAGE_OPPORTUNITY;
        $this->stageNote = null;
        $this->showStageModal = true;
    }

    public function moveStage(): void
    {
        $this->authorize('partnerships.pipeline.manage');

        $partnership = Partnership::findOrFail($this->movingId);
        $from = (int) ($partnership->stage ?? Partnership::STAGE_OPPORTUNITY);
        $to = (int) $this->targetStage;
        $noteRequired = $this->noteRequiredForMove($from, $to);

        $this->validate([
            'movingId' => 'required|exists:partnerships,id',
            'targetStage' => 'required|integer|min:1|max:9',
            'stageNote' => ($noteRequired ? 'required' : 'nullable').'|string|max:255',
        ], [
            'stageNote.required' => 'الملاحظة إلزامية عند الرجوع أو القفز أو التعثر/الإغلاق',
        ], ['targetStage' => 'المرحلة', 'stageNote' => 'ملاحظة الانتقال']);

        try {
            app(PartnershipPipelineService::class)->moveTo(
                $partnership,
                $to,
                auth()->user(),
                $this->stageNote,
            );
        } catch (\RuntimeException|\InvalidArgumentException $exception) {
            $this->addError('targetStage', $exception->getMessage());

            return;
        }

        $this->showStageModal = false;
        $this->dispatch('ds-toast', message: 'تم نقل الشراكة وتسجيل الانتقال');
    }

    private function noteRequiredForMove(int $from, int $to): bool
    {
        if (in_array($to, [Partnership::STAGE_STALLED, Partnership::STAGE_CLOSED], true)) {
            return true;
        }

        $pipeline = Partnership::PIPELINE_STAGES;
        if (! in_array($from, $pipeline, true) || ! in_array($to, $pipeline, true)) {
            return $from !== $to;
        }

        return $to < $from || $to > $from + 1;
    }

    public function render(): View
    {
        $service = app(PartnershipPipelineService::class);

        $pendingFinalQuotes = auth()->user()?->can('partnerships.quotes.finalize')
            ? Quote::query()
                ->where('status', Quote::STATUS_PENDING_FINAL)
                ->with('partnership:id,entity_name,organization_id')
                ->latest('id')
                ->limit(10)
                ->get()
            : collect();

        return view('livewire.partnerships.partnerships-pipeline', [
            'board' => $service->board(),
            'stageLabels' => Partnership::STAGE_LABELS,
            'pipelineStages' => Partnership::PIPELINE_STAGES,
            'staleThreshold' => $service->staleThresholdDays(),
            'list' => Partnership::query()->with(['organization', 'owner'])->orderByDesc('id')->get(),
            'pendingFinalQuotes' => $pendingFinalQuotes,
        ])->layout('layouts.app', ['title' => 'رحلة الشراكات']);
    }
}
