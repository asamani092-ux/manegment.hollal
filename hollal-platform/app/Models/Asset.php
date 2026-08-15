<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use SoftDeletes;

    public const CONDITION_GOOD = 'جيد';

    public const CONDITION_MAINTENANCE = 'صيانة';

    public const CONDITION_DAMAGED = 'تالف';

    public const CONDITION_RETIRED = 'مستبعد';

    /** @var list<string> */
    public const INACTIVE_CONDITIONS = [self::CONDITION_DAMAGED, self::CONDITION_RETIRED];

    /** @var list<string> */
    protected $fillable = [
        'code', 'name_ar', 'description', 'category_id', 'can_be_custody', 'purchase_date',
        'purchase_amount', 'useful_life_years', 'purchase_expense_id', 'location', 'condition',
        'current_holder_id', 'holder_since',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'can_be_custody' => 'boolean',
            'purchase_date' => 'date',
            'purchase_amount' => 'decimal:2',
            'useful_life_years' => 'integer',
            'holder_since' => 'date',
        ];
    }

    /**
     * True when the asset still counts among the active register (not
     * damaged/retired). Damaged/retired assets stay in the independent
     * register but leave the default active set.
     */
    public function isActive(): bool
    {
        return ! in_array($this->condition, self::INACTIVE_CONDITIONS, true);
    }

    /**
     * Straight-line book value: purchase_amount minus one useful-life share
     * per elapsed year since purchase, floored at zero. Never stored —
     * always derived so it reflects "today" on every read.
     * Time: O(1) | Space: O(1)
     */
    public function bookValue(): ?float
    {
        if ($this->purchase_amount === null) {
            return null;
        }

        $purchaseAmount = (float) $this->purchase_amount;

        if (! $this->useful_life_years || $this->useful_life_years <= 0) {
            return round($purchaseAmount, 2);
        }

        $start = $this->purchase_date ?? $this->created_at;
        if (! $start) {
            return round($purchaseAmount, 2);
        }

        $yearsElapsed = max(0.0, $start->diffInDays(now()) / 365.25);
        $annualDepreciation = $purchaseAmount / $this->useful_life_years;
        $bookValue = $purchaseAmount - ($annualDepreciation * $yearsElapsed);

        return max(0.0, round($bookValue, 2));
    }

    /** Excludes damaged/retired assets — the default view of the register. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('condition', self::INACTIVE_CONDITIONS);
    }

    /** @return HasMany<AssetMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class);
    }

    /** @return BelongsTo<User, $this> */
    public function currentHolder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_holder_id');
    }

    /** @return BelongsTo<AssetCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }
}
