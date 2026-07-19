<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollRun extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'مسودة';

    public const STATUS_SUBMITTED = 'مرفوع_للمالية';

    public const STATUS_EXECUTED = 'منفذ';

    public const STATUS_RETURNED = 'معاد_للتصحيح';

    /** @var list<string> */
    protected $fillable = [
        'month', 'status', 'submitted_by', 'submitted_at',
        'finance_approved_by', 'finance_approved_at', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'finance_approved_at' => 'datetime',
        ];
    }

    public function isFinanceApproved(): bool
    {
        return $this->finance_approved_at !== null;
    }

    /**
     * HR may edit amounts only before the run is handed to finance (or after it
     * is returned for correction).
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED], true);
    }

    /** @return HasMany<PayrollRunItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
