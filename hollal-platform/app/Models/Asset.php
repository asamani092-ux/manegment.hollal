<?php

namespace App\Models;

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
    protected $fillable = [
        'code', 'name_ar', 'category_id', 'can_be_custody', 'purchase_date',
        'purchase_amount', 'purchase_expense_id', 'location', 'condition',
        'current_holder_id', 'holder_since',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'can_be_custody' => 'boolean',
            'purchase_date' => 'date',
            'purchase_amount' => 'decimal:2',
            'holder_since' => 'date',
        ];
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
}
