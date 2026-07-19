<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 05-B3 — a quote version. Totals and tax are always computed from the items
 * and the platform tax rate; editing an issued quote creates a new version and
 * leaves the previous one intact.
 */
class Quote extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'مسودة';

    public const STATUS_APPROVED = 'معتمد';

    public const STATUS_SENT = 'مرسل';

    public const STATUS_WITH_NOTES = 'بملاحظات';

    public const STATUS_ACCEPTED = 'مقبول';

    public const STATUS_REJECTED = 'مرفوض';

    /** @var list<string> */
    protected $fillable = [
        'partnership_id', 'version', 'supersedes_id', 'status', 'discount', 'subtotal',
        'tax_rate', 'tax_total', 'total', 'entity_notes', 'approved_by', 'approved_at',
        'sent_at', 'accepted_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'discount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Partnership, $this> */
    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class);
    }

    /** @return HasMany<QuoteItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    /** @return BelongsTo<Quote, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /**
     * Service types sold in this quote — drives which template service items a
     * generated project receives (06B-B1).
     *
     * @return list<string>
     */
    public function includedServices(): array
    {
        return $this->items()->distinct()->pluck('service_type')->values()->all();
    }
}
