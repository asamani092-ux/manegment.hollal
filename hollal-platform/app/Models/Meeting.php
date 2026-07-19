<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'type',
        'scheduled_at',
        'agenda',
        'location',
        'link',
        'chair_id',
        'secretary_id',
        'status',
        'project_id',
        'partnership_id',
        'committee_id',
        'approval_status',
        'approved_by',
        'approved_at',
        'archived_document_id',
        'version',
    ];

    public const APPROVAL_DRAFT = 'مسودة';

    public const APPROVAL_PENDING = 'بانتظار_الاعتماد';

    public const APPROVAL_APPROVED = 'معتمد';

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function isApproved(): bool
    {
        return $this->approval_status === self::APPROVAL_APPROVED;
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Partnership, $this> */
    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class);
    }

    /** @return HasMany<MeetingAmendment, $this> */
    public function amendments(): HasMany
    {
        return $this->hasMany(MeetingAmendment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function chair(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chair_id');
    }

    /** @return BelongsTo<User, $this> */
    public function secretary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretary_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_user')->withTimestamps();
    }

    /** @return HasMany<MeetingItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MeetingItem::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
