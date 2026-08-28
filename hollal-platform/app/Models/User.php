<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'electronic_signature', 'signature_image_path', 'email', 'phone', 'password', 'must_change_password', 'manager_id', 'org_unit_id', 'is_active', 'attendance_enabled', 'employment_status', 'offboarding_started_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    public const STATUS_ACTIVE = 'نشط';

    public const STATUS_FROZEN = 'مجمد';

    public const STATUS_TERMINATED = 'منتهية_علاقته';

    /** @return HasOne<EmployeeProfile, $this> */
    public function profile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    /** 09-B1 @return \Illuminate\Database\Eloquent\Relations\BelongsTo<OrgUnit, $this> */
    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    /** @return HasMany<SalaryComponent, $this> */
    public function salaryComponents(): HasMany
    {
        return $this->hasMany(SalaryComponent::class, 'employee_id');
    }

    /**
     * 01-B1 — transition employment status, keeping the is_active login gate in
     * sync. منتهية_علاقته is reachable only through offboarding (01-B5).
     */
    public function transitionStatus(string $status, bool $viaOffboarding = false): void
    {
        if ($status === self::STATUS_TERMINATED && ! $viaOffboarding) {
            throw new \InvalidArgumentException('إنهاء العلاقة يتم عبر مسار إنهاء الخدمة فقط.');
        }

        $this->employment_status = $status;
        $this->is_active = $status === self::STATUS_ACTIVE;
        $this->save();
    }

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Display label for org placement (قسم or إدارة ancestor). Time: O(1) | Space: O(1)
     */
    public function orgPlacementLabel(): string
    {
        $node = $this->orgUnit;
        if (! $node) {
            return '—';
        }

        if ($node->level === OrgUnit::LEVEL_JOB) {
            $unit = $node->relationLoaded('parent') ? $node->parent : $node->parent()->first();

            return $unit?->name ?? $node->name;
        }

        return $node->name;
    }

    /** @return HasMany<User, $this> */
    public function subordinates(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    /** @return HasMany<Project, $this> */
    public function managedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'manager_id');
    }

    /** @return BelongsToMany<Project, $this> */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user')->withTimestamps();
    }

    /** @return HasMany<Task, $this> */
    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    /** @return HasMany<PeriodicEvaluation, $this> */
    public function periodicEvaluations(): HasMany
    {
        return $this->hasMany(PeriodicEvaluation::class, 'employee_id');
    }

    /** @return HasMany<Task, $this> */
    public function delegatedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_by');
    }

    /** @return HasMany<Meeting, $this> */
    public function chairedMeetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'chair_id');
    }

    /** @return HasMany<Meeting, $this> */
    public function secretaryMeetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'secretary_id');
    }

    /** @return BelongsToMany<Meeting, $this> */
    public function meetings(): BelongsToMany
    {
        return $this->belongsToMany(Meeting::class, 'meeting_user')->withTimestamps();
    }

    /** @return HasMany<MeetingItem, $this> */
    public function responsibleMeetingItems(): HasMany
    {
        return $this->hasMany(MeetingItem::class, 'responsible_id');
    }

    /** @return HasMany<Contract, $this> */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'employee_id');
    }

    /** @return HasMany<EmployeeDocument, $this> */
    public function employeeDocuments(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    /** @return HasMany<ExpenseRequest, $this> */
    public function expenseRequests(): HasMany
    {
        return $this->hasMany(ExpenseRequest::class, 'requester_id');
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->email ?: (string) $this->phone;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
            'attendance_enabled' => 'boolean',
            'offboarding_started_at' => 'datetime',
        ];
    }
}
