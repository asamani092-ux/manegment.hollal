<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * سلسلة اعتماد واحدة لنوع طلب.
 * Time: O(1) | Space: O(steps)
 */
class ApprovalChain extends Model
{
    /** @var list<string> */
    public const TYPES = [
        'expense',
        'custody',
        'leave',
        'overtime',
        'excuse',
        'delegation',
        'quote',
        'contract',
    ];

    /** @var array<string, string> */
    public const TYPE_LABELS = [
        'expense' => 'صرف',
        'custody' => 'عهدة',
        'leave' => 'إجازة',
        'overtime' => 'عمل إضافي',
        'excuse' => 'استئذان',
        'delegation' => 'إنابة',
        'quote' => 'عرض سعر',
        'contract' => 'عقد',
    ];

    /** @var list<string> */
    protected $fillable = ['request_type', 'is_active', 'updated_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<ApprovalChainStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalChainStep::class)->orderBy('position');
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function label(): string
    {
        return self::TYPE_LABELS[$this->request_type] ?? $this->request_type;
    }

    public function valueUnit(): string
    {
        return match ($this->request_type) {
            'leave' => 'يوم',
            'overtime', 'excuse' => 'ساعة',
            'delegation' => '',
            default => 'ريال',
        };
    }
}
