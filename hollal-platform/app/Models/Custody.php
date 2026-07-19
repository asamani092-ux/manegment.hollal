<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Custody extends Model
{
    use SoftDeletes;

    public const STATUS_REQUESTED = 'طلب';

    public const STATUS_APPROVED = 'معتمدة';

    public const STATUS_DISBURSED = 'صرف';

    public const STATUS_SETTLING = 'تسوية';

    public const STATUS_CLOSED = 'مغلقة';

    /** @var list<string> */
    protected $fillable = [
        'employee_id', 'amount', 'disbursed_amount', 'returned_amount', 'purpose',
        'category_id', 'project_id', 'requested_by', 'approved_by', 'status', 'due_date',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'disbursed_amount' => 'decimal:2',
            'returned_amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status !== self::STATUS_CLOSED;
    }

    public function settledTotal(): float
    {
        return (float) $this->settlementItems()->sum('amount');
    }

    /** @return HasMany<CustodySettlementItem, $this> */
    public function settlementItems(): HasMany
    {
        return $this->hasMany(CustodySettlementItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
