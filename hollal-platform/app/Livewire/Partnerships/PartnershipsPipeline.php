<?php

namespace App\Livewire\Partnerships;

use App\Models\Partnership;
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

        $this->validate([
            'movingId' => 'required|exists:partnerships,id',
            'targetStage' => 'required|integer|min:1|max:9',
            'stageNote' => 'nullable|string|max:255',
        ], [], ['targetStage' => 'المرحلة']);

        app(PartnershipPipelineService::class)->moveTo(
            Partnership::findOrFail($this->movingId),
            (int) $this->targetStage,
            auth()->user(),
            $this->stageNote,
        );

        $this->showStageModal = false;
        $this->dispatch('ds-toast', message: 'تم نقل الشراكة وتسجيل الانتقال');
    }

    public function render(): View
    {
        $service = app(PartnershipPipelineService::class);

        return view('livewire.partnerships.partnerships-pipeline', [
            'board' => $service->board(),
            'stageLabels' => Partnership::STAGE_LABELS,
            'pipelineStages' => Partnership::PIPELINE_STAGES,
            'staleThreshold' => $service->staleThresholdDays(),
            'list' => Partnership::query()->with(['organization', 'owner'])->orderByDesc('id')->get(),
        ])->layout('layouts.app', ['title' => 'رحلة الشراكات']);
    }
}
