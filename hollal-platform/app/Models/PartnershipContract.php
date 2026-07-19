<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 05-B4 — partnership contract. «تعاقد» requires a confirmed signed copy and,
 * when the contract calls for it, a confirmed first payment.
 */
class PartnershipContract extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'مسودة';

    public const STATUS_AWAITING_SIGNATURE = 'بانتظار التوقيع';

    public const STATUS_SIGNED = 'موقّع';

    public const STATUS_CONFIRMED = 'مؤكد';

    public const STATUS_CANCELLED = 'ملغى';

    public const METHOD_IN_LINK = 'داخل_الرابط';

    public const METHOD_MANUAL_UPLOAD = 'رفع_يدوي';

    /** @var list<string> */
    protected $fillable = [
        'partnership_id', 'quote_id', 'status', 'starts_on', 'ends_on',
        'hollal_commitments', 'partner_commitments', 'total_value', 'requires_first_payment',
        'unsigned_pdf_path', 'signed_pdf_path', 'signed_pdf_hash', 'signature_name',
        'signature_method', 'signature_position', 'signature_image_path',
        'signature_device', 'signed_at', 'confirmed_by', 'confirmed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'total_value' => 'decimal:2',
            'requires_first_payment' => 'boolean',
            'signed_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Partnership, $this> */
    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class);
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** @return HasMany<ContractPaymentSchedule, $this> */
    public function schedule(): HasMany
    {
        return $this->hasMany(ContractPaymentSchedule::class)->orderBy('sequence');
    }

    public function hasSignedCopy(): bool
    {
        return $this->signed_pdf_path !== null && $this->signed_pdf_hash !== null;
    }
}
