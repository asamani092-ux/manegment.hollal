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
        'minutes_missing_signatures_reason',
        'archived_document_id',
        'minutes_notified_at',
        'signed_document_id',
        'version',
    ];

    public const APPROVAL_DRAFT = 'مسودة';

    public const APPROVAL_PENDING = 'بانتظار_الاعتماد';

    public const APPROVAL_APPROVED = 'معتمد';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_SCHEDULED => 'مجدول',
        self::STATUS_IN_PROGRESS => 'جارٍ',
        self::STATUS_COMPLETED => 'مكتمل',
        self::STATUS_CANCELLED => 'ملغى',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'approved_at' => 'datetime',
            'minutes_notified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Meeting $meeting): void {
            if (! $meeting->partnership_id) {
                return;
            }

            $partnership = $meeting->partnership;
            if ($partnership === null) {
                return;
            }

            app(\App\Services\PartnershipPipelineService::class)->advanceIfBefore(
                $partnership,
                Partnership::STAGE_MEETING,
                null,
                'حفظ لقاء أو محضر مرتبط بالشراكة',
            );
        });
    }

    /** Time: O(1) | Space: O(1) */
    public function statusLabel(): string
    {
        if ($this->status === null || $this->status === '') {
            return '—';
        }

        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === self::APPROVAL_APPROVED;
    }

    /**
     * P2 wave C — gates the pre/post-meeting confirm-minutes UX and the
     * minutes-ready notification. Time: O(1) | Space: O(1)
     */
    public function hasEnded(): bool
    {
        return $this->scheduled_at !== null && $this->scheduled_at->isPast();
    }

    /**
     * Advance runtime status from scheduled → completed after the meeting time.
     * Does not override cancelled. Time: O(1) | Space: O(1)
     */
    public function syncRuntimeStatus(): void
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return;
        }

        if ($this->hasEnded() && $this->status !== self::STATUS_COMPLETED) {
            $this->forceFill(['status' => self::STATUS_COMPLETED])->saveQuietly();
        }
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
        return $this->belongsToMany(User::class, 'meeting_user')
            ->withPivot(['confirmed_at', 'signature_text', 'signature_image_path'])
            ->withTimestamps();
    }

    /** @return HasMany<MeetingItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MeetingItem::class);
    }

    /** P2 wave C — external guests (no employee account). @return HasMany<MeetingGuest, $this> */
    public function guests(): HasMany
    {
        return $this->hasMany(MeetingGuest::class);
    }

    /** P2 wave C — manually uploaded signed PDF, alongside the electronic archive. @return BelongsTo<Document, $this> */
    public function signedDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'signed_document_id');
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
