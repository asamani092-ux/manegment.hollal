<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Project — soft deletes only.
 * Time: O(1) single record | list queries O(n).
 */
class Project extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'partnership_id',
        'program_id',
        'kind',
        'launch_date',
        'manager_id',
        'start_date',
        'end_date',
        'budget',
        'status',
        'idea_goal',
        'target_audience',
        'required_outputs',
        'final_outputs',
        'current_phase',
        'hollal_template_version_id',
        'entity_template_version_id',
        'generated_from_request_id',
        'lesson_learned',
        'final_report_path',
        'final_report_approved_at',
        'delivered_at',
        'closed_at',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'launch_date' => 'date',
            'budget' => 'decimal:2',
            'final_report_approved_at' => 'datetime',
            'delivered_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Forward link to the owning partnership (00-B4 reversed relation).
     *
     * @return BelongsTo<Partnership, $this>
     */
    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class);
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function team(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')->withTimestamps();
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return HasMany<Partnership, $this> */
    public function partnerships(): HasMany
    {
        return $this->hasMany(Partnership::class);
    }

    /** @return HasMany<ExpenseRequest, $this> */
    public function expenseRequests(): HasMany
    {
        return $this->hasMany(ExpenseRequest::class);
    }

    public function actualSpend(): float
    {
        return (float) $this->expenseRequests()
            ->countedAsSpend()
            ->sum('amount');
    }

    public function remainingBudget(): ?float
    {
        if ($this->budget === null) {
            return null;
        }

        return (float) $this->budget - $this->actualSpend();
    }

    /** @return HasMany<ProjectUpdate, $this> */
    public function projectUpdates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class);
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** 06B-B3 @return HasMany<ProjectVisit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(ProjectVisit::class);
    }

    /** 06B-B3 @return HasMany<Consultation, $this> */
    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    /** 06B-B4 @return HasMany<BeneficiaryGroup, $this> */
    public function beneficiaryGroups(): HasMany
    {
        return $this->hasMany(BeneficiaryGroup::class);
    }

    /** 06B-B4 @return HasMany<MeasurementResponse, $this> */
    public function measurementResponses(): HasMany
    {
        return $this->hasMany(MeasurementResponse::class);
    }

    /** 06B-B2 @return HasMany<ProjectEntityMember, $this> */
    public function entityMembers(): HasMany
    {
        return $this->hasMany(ProjectEntityMember::class);
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }
}
