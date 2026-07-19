<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractPaymentSchedule extends Model
{
    /** @var list<string> */
    protected $fillable = ['partnership_contract_id', 'sequence', 'label', 'amount', 'due_on'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'amount' => 'decimal:2',
            'due_on' => 'date',
        ];
    }

    /** @return BelongsTo<PartnershipContract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(PartnershipContract::class, 'partnership_contract_id');
    }

    /** @return HasMany<PartnershipPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(PartnershipPayment::class, 'contract_payment_schedule_id');
    }

    public function confirmedAmount(): float
    {
        return (float) $this->payments()->where('status', PartnershipPayment::STATUS_CONFIRMED)->sum('amount');
    }

    public function isLate(): bool
    {
        return $this->due_on->isPast() && $this->confirmedAmount() < (float) $this->amount;
    }
}
