<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-2 Wave D-deep — one row per invoice type (ضريبية/مبسطة) carrying an
 * uploadable letterhead/background used behind the PDF content. Company
 * data (name/VAT/CR/address) always comes from CompanyProfile, not here.
 */
class TaxInvoiceTemplate extends Model
{
    /** @var list<string> */
    protected $fillable = ['type', 'letterhead_path', 'updated_by'];

    public static function forType(string $type): ?self
    {
        return static::query()->where('type', $type)->first();
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
