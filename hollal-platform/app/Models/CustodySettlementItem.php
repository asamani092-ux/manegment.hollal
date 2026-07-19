<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustodySettlementItem extends Model
{
    /** @var list<string> */
    protected $fillable = ['custody_id', 'description', 'amount', 'category_id', 'invoice_file'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** @return BelongsTo<Custody, $this> */
    public function custody(): BelongsTo
    {
        return $this->belongsTo(Custody::class);
    }
}
