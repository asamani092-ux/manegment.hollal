<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Partnership with magic link token — soft deletes only.
 */
class Partnership extends Model
{
    use SoftDeletes;

    /** 05-B2 — the seven-stage journey plus the two terminal states. */
    public const STAGE_OPPORTUNITY = 1;

    public const STAGE_CONTACT = 2;

    public const STAGE_MEETING = 3;

    public const STAGE_DIAGNOSIS = 4;

    public const STAGE_QUOTE = 5;

    /** @deprecated Merged into STAGE_QUOTE; kept for legacy rows only. */
    public const STAGE_CONTRACTED = 6;

    public const STAGE_EXECUTION = 7;

    public const STAGE_STALLED = 8;

    public const STAGE_CLOSED = 9;

    /** @var array<int, string> */
    public const STAGE_LABELS = [
        self::STAGE_OPPORTUNITY => 'فرصة',
        self::STAGE_CONTACT => 'تواصل',
        self::STAGE_MEETING => 'لقاء/عرض تعريفي',
        self::STAGE_DIAGNOSIS => 'تشخيص الاحتياج',
        self::STAGE_QUOTE => 'عرض السعر',
        self::STAGE_CONTRACTED => 'عرض السعر',
        self::STAGE_EXECUTION => 'تنفيذ',
        self::STAGE_STALLED => 'متعثرة',
        self::STAGE_CLOSED => 'مغلقة',
    ];

    /**
     * Pipeline columns — تعاقد folded into عرض السعر.
     *
     * @var list<int>
     */
    public const PIPELINE_STAGES = [1, 2, 3, 4, 5, 7];

    protected $fillable = [
        'organization_id',
        'owner_id',
        'stage',
        'stalled_reason',
        'closed_reason',
        'renewed_from_id',
        'expected_value',
        'stage_entered_at',
        'entity_name',
        'contact_person',
        'contact_phone',
        'magic_link_token',
        'token_expires_at',
        'type_quantity',
        'halal_commitments',
        'partner_commitments',
        'pricing_amount',
        'contract_pdf',
        'project_id',
        'status',
        'awaiting_internal_approval',
        'internal_approval_notes',
        'portal_features',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'stage_entered_at' => 'datetime',
            'pricing_amount' => 'decimal:2',
            'expected_value' => 'decimal:2',
            'stage' => 'integer',
            'awaiting_internal_approval' => 'boolean',
            'portal_features' => 'array',
        ];
    }

    public function stageLabel(): string
    {
        return self::STAGE_LABELS[$this->stage] ?? '—';
    }

    /** Days spent in the current stage — drives the stale highlight (05-B2). */
    public function stageAgeDays(): int
    {
        return (int) ($this->stage_entered_at ?? $this->created_at ?? now())->diffInDays(now());
    }

    public function executionDays(): int
    {
        return $this->stage === self::STAGE_EXECUTION ? $this->stageAgeDays() : 0;
    }

    /** @return HasMany<PartnershipStageLog, $this> */
    public function stageLogs(): HasMany
    {
        return $this->hasMany(PartnershipStageLog::class)->latest('id');
    }

    /** @return HasMany<Quote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class)->orderByDesc('version');
    }

    /** @return HasMany<PartnershipContract, $this> */
    public function partnershipContracts(): HasMany
    {
        return $this->hasMany(PartnershipContract::class);
    }

    /** @return HasMany<PartnershipPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(PartnershipPayment::class);
    }

    /** @return HasMany<PartnerLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(PartnerLink::class);
    }

    /** @return HasMany<DiagnosisAnswer, $this> */
    public function diagnosisAnswers(): HasMany
    {
        return $this->hasMany(DiagnosisAnswer::class);
    }

    /** @return BelongsToMany<Program, $this> */
    public function allowedPrograms(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'partnership_allowed_programs')
            ->withTimestamps()
            ->orderBy('programs.name');
    }

    /** @return HasMany<ProjectGenerationRequest, $this> */
    public function generationRequests(): HasMany
    {
        return $this->hasMany(ProjectGenerationRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function confirmedContract(): ?PartnershipContract
    {
        return $this->partnershipContracts()
            ->where('status', PartnershipContract::STATUS_CONFIRMED)
            ->latest('id')
            ->first();
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<self, $this> */
    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    /** @return HasMany<self, $this> */
    public function renewals(): HasMany
    {
        return $this->hasMany(self::class, 'renewed_from_id');
    }

    /** @return array{programs: bool, diagnosis: bool, quotes: bool, payments: bool, contract: bool} */
    public static function defaultPortalFeatures(): array
    {
        return [
            'programs' => true,
            'diagnosis' => true,
            'quotes' => false,
            'payments' => false,
            'contract' => false,
        ];
    }

    /** @return array{programs: bool, diagnosis: bool, quotes: bool, payments: bool, contract: bool} */
    public function portalFeatureFlags(): array
    {
        $stored = is_array($this->portal_features) ? $this->portal_features : [];

        return array_merge(self::defaultPortalFeatures(), array_intersect_key($stored, self::defaultPortalFeatures()));
    }

    /** @param list<string> $keys Time: O(1) | Space: O(1) */
    public function enablePortalFeatures(array $keys): void
    {
        $flags = $this->portalFeatureFlags();
        foreach ($keys as $key) {
            if (array_key_exists($key, $flags)) {
                $flags[$key] = true;
            }
        }
        $this->forceFill(['portal_features' => $flags])->save();
    }

    public function canRenewJourney(): bool
    {
        return self::projectStatusAllowsRenewal($this->project?->status);
    }

    public static function projectStatusAllowsRenewal(?string $status): bool
    {
        return in_array($status, [
            'completed', 'on_hold', 'closed',
            'مكتمل', 'متوقف', 'منتهٍ', 'منتهي', 'مغلق',
        ], true);
    }

    public function latestContract(): ?PartnershipContract
    {
        return $this->partnershipContracts()->latest('id')->first();
    }

    /** Commercial work (quote → contract → payments) lives under عرض السعر. */
    public function isCommercialStage(): bool
    {
        return in_array((int) $this->stage, [self::STAGE_QUOTE, self::STAGE_CONTRACTED], true);
    }

    /** Map legacy contracted rows onto the quote column. Time: O(1) | Space: O(1) */
    public function pipelineColumnStage(): int
    {
        $stage = (int) $this->stage;

        return $stage === self::STAGE_CONTRACTED ? self::STAGE_QUOTE : $stage;
    }
}
