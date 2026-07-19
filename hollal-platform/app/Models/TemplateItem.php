<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 06A-B2 — one node of the five-level template tree:
 * 1 المرحلة · 2 الإجراء الرئيسي · 3 الإجراءات الفرعية ·
 * 4 المهام التفصيلية الرئيسية · 5 المهام التفصيلية الفرعية.
 */
class TemplateItem extends Model
{
    public const KIND_MANDATORY = 'إلزامي';

    public const KIND_SERVICE = 'خدمة';

    public const MAX_LEVEL = 5;

    /** @var list<string> */
    protected $fillable = [
        'template_version_id', 'parent_id', 'level', 'title', 'role',
        'start_offset_days', 'duration_days', 'evidence_required',
        'item_kind', 'service_type', 'guidance_note', 'position',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'start_offset_days' => 'integer',
            'duration_days' => 'integer',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<TemplateVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class, 'template_version_id');
    }

    /** @return BelongsTo<TemplateItem, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<TemplateItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function isService(): bool
    {
        return $this->item_kind === self::KIND_SERVICE;
    }
}
