<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Official employee document with optional expiry for HR reminders.
 * Time: O(1) single | list O(d) per employee.
 */
class EmployeeDocument extends Model
{
    public const TYPE_CONTRACT = 'عقد عمل';

    public const TYPE_ID = 'هوية';

    public const TYPE_IQAMA = 'إقامة';

    public const TYPE_PASSPORT = 'جواز';

    public const TYPE_CLEARANCE = 'مخالصة';

    public const TYPE_OTHER = 'أخرى';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_CONTRACT,
        self::TYPE_ID,
        self::TYPE_IQAMA,
        self::TYPE_PASSPORT,
        self::TYPE_CLEARANCE,
        self::TYPE_OTHER,
    ];

    protected $fillable = [
        'user_id',
        'type',
        'document_number',
        'issue_date',
        'expiry_date',
        'file_path',
        'notes',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    /** Days until expiry; null if no date. Negative = expired. */
    public function daysUntilExpiry(?\Carbon\CarbonInterface $asOf = null): ?int
    {
        if (! $this->expiry_date) {
            return null;
        }

        $asOf ??= now()->startOfDay();

        return (int) $asOf->diffInDays($this->expiry_date->copy()->startOfDay(), false);
    }

    public function isExpiringSoon(int $withinDays = 30): bool
    {
        $days = $this->daysUntilExpiry();

        return $days !== null && $days >= 0 && $days <= $withinDays;
    }

    public function isExpired(): bool
    {
        $days = $this->daysUntilExpiry();

        return $days !== null && $days < 0;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
