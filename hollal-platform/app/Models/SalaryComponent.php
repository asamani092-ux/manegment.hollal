<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryComponent extends Model
{
    use SoftDeletes;

    public const TYPE_BASE = 'أساسي';

    public const TYPE_ALLOWANCE = 'بدل';

    public const TYPE_DEDUCTION = 'خصم_ثابت';

    /** @var list<string> */
    protected $fillable = [
        'employee_id', 'type', 'label_ar', 'amount', 'valid_from', 'valid_to', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Components effective on a given date (open-ended when valid_to is null).
     *
     * @param  Builder<SalaryComponent>  $query
     */
    public function scopeEffectiveOn(Builder $query, \DateTimeInterface|string $date): void
    {
        $query->where('is_active', true)
            ->whereDate('valid_from', '<=', $date)
            ->where(function (Builder $inner) use ($date) {
                $inner->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date);
            });
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
