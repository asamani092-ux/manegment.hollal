<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        self::STAGE_CONTRACTED => 'تعاقد',
        self::STAGE_EXECUTION => 'تنفيذ',
        self::STAGE_STALLED => 'متعثرة',
        self::STAGE_CLOSED => 'مغلقة',
    ];

    /** @var list<int> the seven pipeline columns (terminal states are not columns) */
    public const PIPELINE_STAGES = [1, 2, 3, 4, 5, 6, 7];

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
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'stage_entered_at' => 'datetime',
            'pricing_amount' => 'decimal:2',
            'expected_value' => 'decimal:2',
            'stage' => 'integer',
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
}
