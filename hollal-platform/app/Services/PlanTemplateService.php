<?php

namespace App\Services;

use App\Models\PlanTemplate;
use App\Models\TemplateItem;
use App\Models\TemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 06A-B2 — plan template editor.
 *
 * Every edit produces a new version: projects generated from an older version
 * keep their copy, new projects take the current one. Real generation stays
 * blocked while `needs_review` is set (the review session with عبدالله).
 */
class PlanTemplateService
{
    /** Create a template with an empty first version. */
    public function create(string $name, string $kind, ?User $author = null, ?int $programId = null): PlanTemplate
    {
        return DB::transaction(function () use ($name, $kind, $author, $programId) {
            $template = PlanTemplate::create([
                'name' => $name,
                'kind' => $kind,
                'program_id' => $programId,
                'needs_review' => true,
            ]);

            $this->newVersion($template, $author, 'الإصدار الأول');

            return $template->fresh();
        });
    }

    /**
     * Open a new version. Items of the current version are copied across, so
     * the previous version stays frozen for the projects that used it.
     */
    public function newVersion(PlanTemplate $template, ?User $author = null, ?string $reason = null): TemplateVersion
    {
        return DB::transaction(function () use ($template, $author, $reason) {
            $previous = $template->currentVersion;

            $template->versions()->where('is_current', true)->update(['is_current' => false]);

            $version = $template->versions()->create([
                'version_label' => 'v'.($template->versions()->count() + 1),
                'is_current' => true,
                'change_reason' => $reason,
                'created_by' => $author?->id,
            ]);

            if ($previous) {
                $this->copyItems($previous, $version);
            }

            $template->forceFill(['current_version_id' => $version->id])->save();

            return $version;
        });
    }

    /**
     * Add an item to a version. Level is derived from the parent, capped at 5.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function addItem(TemplateVersion $version, array $attributes, ?TemplateItem $parent = null): TemplateItem
    {
        $level = $parent ? $parent->level + 1 : 1;

        if ($level > TemplateItem::MAX_LEVEL) {
            throw new \InvalidArgumentException('البنية الخمسية لا تسمح بأكثر من خمسة مستويات');
        }

        $kind = $attributes['item_kind'] ?? TemplateItem::KIND_MANDATORY;

        if (! in_array($kind, [TemplateItem::KIND_MANDATORY, TemplateItem::KIND_SERVICE], true)) {
            throw new \InvalidArgumentException('نوع البند يجب أن يكون إلزامي أو خدمة');
        }

        return $version->items()->create([
            'parent_id' => $parent?->id,
            'level' => $level,
            'title' => $attributes['title'],
            'role' => $attributes['role'] ?? null,
            'start_offset_days' => (int) ($attributes['start_offset_days'] ?? 0),
            'duration_days' => (int) ($attributes['duration_days'] ?? 1),
            'evidence_required' => $attributes['evidence_required'] ?? null,
            'item_kind' => $kind,
            'service_type' => $attributes['service_type'] ?? null,
            'guidance_note' => $attributes['guidance_note'] ?? null,
            'position' => (int) ($attributes['position'] ?? $version->items()->where('parent_id', $parent?->id)->count()),
        ]);
    }

    /** Clear the review flag — only after the review session has signed off. */
    public function markReviewed(PlanTemplate $template, User $reviewer, ?string $note = null): PlanTemplate
    {
        $template->forceFill([
            'needs_review' => false,
            'review_note' => $note,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ])->save();

        return $template;
    }

    /**
     * Guard called by the generation engine (06B-B1).
     *
     * @throws \RuntimeException while the template still needs review
     */
    public function assertGeneratable(PlanTemplate $template): void
    {
        if ($template->needs_review) {
            throw new \RuntimeException(
                'القالب «'.$template->name.'» بانتظار جلسة المراجعة قبل أول توليد حقيقي'
            );
        }

        if (! $template->current_version_id) {
            throw new \RuntimeException('القالب «'.$template->name.'» بلا إصدار حالي');
        }
    }

    /**
     * Items that a generated project should receive: mandatory items always,
     * service items only when the service was sold in the quote.
     *
     * @param  list<string>  $includedServices
     * @return \Illuminate\Support\Collection<int, TemplateItem>
     */
    public function itemsForServices(TemplateVersion $version, array $includedServices = [])
    {
        return $version->items()->orderBy('level')->orderBy('position')->get()
            ->filter(fn (TemplateItem $item) => ! $item->isService()
                || in_array((string) $item->service_type, $includedServices, true))
            ->values();
    }

    private function copyItems(TemplateVersion $from, TemplateVersion $to): void
    {
        $map = [];

        foreach ($from->items()->orderBy('level')->orderBy('id')->get() as $item) {
            $copy = $to->items()->create([
                'parent_id' => $item->parent_id ? ($map[$item->parent_id] ?? null) : null,
                'level' => $item->level,
                'title' => $item->title,
                'role' => $item->role,
                'start_offset_days' => $item->start_offset_days,
                'duration_days' => $item->duration_days,
                'evidence_required' => $item->evidence_required,
                'item_kind' => $item->item_kind,
                'service_type' => $item->service_type,
                'guidance_note' => $item->guidance_note,
                'position' => $item->position,
            ]);

            $map[$item->id] = $copy->id;
        }
    }
}
