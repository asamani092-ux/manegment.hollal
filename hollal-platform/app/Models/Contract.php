<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Employee contract — soft deletes only.
 * Time: O(1) single | list O(n).
 */
class Contract extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var list<string> */
    public const STATUSES = ['active', 'expired', 'terminated', 'pending'];

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        'active' => 'ساري',
        'expired' => 'منتهي',
        'terminated' => 'مكتمل',
        'pending' => 'معلق',
    ];

    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'value',
        'contract_file',
        'status',
        'renewal_history',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'value' => 'decimal:2',
            'renewal_history' => 'array',
        ];
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Renew is offered when expired (status) or ending within 30 days.
     * Time: O(1).
     */
    public function isRenewable(?\Carbon\CarbonInterface $asOf = null): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        if ($this->status === 'terminated') {
            return false;
        }

        if (! $this->end_date) {
            return false;
        }

        $asOf ??= now()->startOfDay();
        $end = $this->end_date->copy()->startOfDay();

        return $end->lessThanOrEqualTo($asOf->copy()->addDays(30));
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
