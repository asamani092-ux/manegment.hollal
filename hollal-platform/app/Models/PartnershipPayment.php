<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 05-B6 — a recorded payment. Finance confirmation creates exactly one revenue
 * (double reference: revenue_id here, source_id there).
 */
class PartnershipPayment extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'بانتظار تأكيد المالية';

    public const STATUS_CONFIRMED = 'مؤكدة';

    public const STATUS_REJECTED = 'مرفوضة';

    public const VIA_INTERNAL = 'داخلي';

    public const VIA_PORTAL = 'رابط الجهة';

    /** @var list<string> */
    protected $fillable = [
        'partnership_id', 'contract_payment_schedule_id', 'amount', 'paid_on', 'proof_path',
        'status', 'recorded_via', 'confirmed_by', 'confirmed_at', 'revenue_id', 'tax_invoice_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Partnership, $this> */
    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class);
    }

    /** @return BelongsTo<ContractPaymentSchedule, $this> */
    public function scheduleItem(): BelongsTo
    {
        return $this->belongsTo(ContractPaymentSchedule::class, 'contract_payment_schedule_id');
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }
}
