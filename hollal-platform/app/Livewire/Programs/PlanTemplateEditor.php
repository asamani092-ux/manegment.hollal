<?php

namespace App\Livewire\Programs;

use App\Models\PlanTemplate;
use App\Models\TemplateItem;
use App\Services\PlanTemplateService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * 06A-B2 — five-level plan template editor. Each save opens a new version, so
 * projects already generated keep the version they were built from.
 */
class PlanTemplateEditor extends Component
{
    use AuthorizesRequests;

    public ?int $templateId = null;

    public bool $showItemModal = false;

    public ?int $parentId = null;

    public string $title = '';

    public ?string $role = null;

    public string $startOffsetDays = '0';

    public string $durationDays = '1';

    public ?string $evidenceRequired = null;

    public string $itemKind = TemplateItem::KIND_MANDATORY;

    public ?string $serviceType = null;

    public ?string $guidanceNote = null;

    public ?string $reviewNote = null;

    public function mount(?int $templateId = null): void
    {
        $this->authorize('projects.templates.manage');
        $this->templateId = $templateId ?? PlanTemplate::query()->orderBy('id')->value('id');
    }

    public function selectTemplate(int $id): void
    {
        $this->templateId = $id;
    }

    public function openItemModal(?int $parentId = null): void
    {
        $this->authorize('projects.templates.manage');
        $this->parentId = $parentId;
        $this->title = '';
        $this->role = null;
        $this->startOffsetDays = '0';
        $this->durationDays = '1';
        $this->evidenceRequired = null;
        $this->itemKind = TemplateItem::KIND_MANDATORY;
        $this->serviceType = null;
        $this->guidanceNote = null;
        $this->showItemModal = true;
    }

    public function addItem(): void
    {
        $this->authorize('projects.templates.manage');

        $this->validate([
            'title' => 'required|string|max:255',
            'role' => 'nullable|string|max:100',
            'startOffsetDays' => 'required|integer|min:0',
            'durationDays' => 'required|integer|min:1',
            'evidenceRequired' => 'nullable|string|max:255',
            'itemKind' => 'required|in:'.TemplateItem::KIND_MANDATORY.','.TemplateItem::KIND_SERVICE,
            'serviceType' => 'nullable|string|max:50',
            'guidanceNote' => 'nullable|string',
        ], [], ['title' => 'عنوان البند']);

        $service = app(PlanTemplateService::class);
        $template = $this->template();

        // Every edit opens a new version; older projects keep their copy.
        $version = $service->newVersion($template, auth()->user(), 'إضافة بند: '.$this->title);
        $parent = $this->parentId
            ? $version->items()->where('title', TemplateItem::findOrFail($this->parentId)->title)->first()
            : null;

        $service->addItem($version, [
            'title' => $this->title,
            'role' => $this->role,
            'start_offset_days' => (int) $this->startOffsetDays,
            'duration_days' => (int) $this->durationDays,
            'evidence_required' => $this->evidenceRequired,
            'item_kind' => $this->itemKind,
            'service_type' => $this->itemKind === TemplateItem::KIND_SERVICE ? $this->serviceType : null,
            'guidance_note' => $this->guidanceNote,
        ], $parent);

        $this->showItemModal = false;
        $this->dispatch('ds-toast', message: 'تمت إضافة البند في إصدار جديد');
    }

    /** Clear the review flag after the review session. */
    public function markReviewed(): void
    {
        $this->authorize('projects.templates.manage');

        app(PlanTemplateService::class)->markReviewed($this->template(), auth()->user(), $this->reviewNote);

        $this->dispatch('ds-toast', message: 'تم اعتماد القالب بعد جلسة المراجعة');
    }

    public function render(): View
    {
        $template = $this->templateId ? $this->template() : null;

        return view('livewire.programs.plan-template-editor', [
            'templates' => PlanTemplate::orderBy('id')->get(),
            'template' => $template,
            'items' => $template?->currentVersion
                ? $template->currentVersion->items()->orderBy('level')->orderBy('position')->get()
                : collect(),
        ])->layout('layouts.app', ['title' => 'محرر قوالب الخطط']);
    }

    private function template(): PlanTemplate
    {
        return PlanTemplate::findOrFail($this->templateId);
    }
}
