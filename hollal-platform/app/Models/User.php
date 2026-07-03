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

#[Fillable(['name', 'email', 'phone', 'password', 'must_change_password', 'department_id', 'manager_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
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

    /** @return HasMany<ExpenseRequest, $this> */
    public function expenseRequests(): HasMany
    {
        return $this->hasMany(ExpenseRequest::class, 'requester_id');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
