<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-ACC-1 — دليل الحسابات (شجرة).
 * Time: O(1) relations | Space: O(1)
 */
class ChartOfAccount extends Model
{
    use SoftDeletes;

    public const TYPE_ASSETS = 'أصول';

    public const TYPE_LIABILITIES = 'خصوم';

    public const TYPE_EQUITY = 'حقوق_ملكية';

    public const TYPE_REVENUE = 'إيرادات';

    public const TYPE_EXPENSE = 'مصروفات';

    public const NATURE_DEBIT = 'مدين';

    public const NATURE_CREDIT = 'دائن';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_ASSETS,
        self::TYPE_LIABILITIES,
        self::TYPE_EQUITY,
        self::TYPE_REVENUE,
        self::TYPE_EXPENSE,
    ];

    /** @var list<string> */
    protected $fillable = [
        'code', 'name_ar', 'type', 'parent_id', 'nature', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @param Builder<ChartOfAccount> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<ChartOfAccount> $query */
    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /** @return BelongsTo<ChartOfAccount, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<ChartOfAccount, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    /** @return HasMany<ExpenseCategory, $this> */
    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class, 'account_id');
    }

    /** @return HasMany<RevenueCategory, $this> */
    public function revenueCategories(): HasMany
    {
        return $this->hasMany(RevenueCategory::class, 'account_id');
    }

    /** Time: O(1) | Space: O(1) */
    public function hasMovements(): bool
    {
        if (! Schema::hasTable('journal_lines')) {
            return false;
        }

        return DB::table('journal_lines')->where('account_id', $this->id)->exists();
    }
}
