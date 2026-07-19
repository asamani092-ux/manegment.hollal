<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Revenue extends Model
{
    use SoftDeletes;

    public const SOURCE_PARTNERSHIP = 'شراكة';

    public const SOURCE_MANUAL = 'يدوي';

    public const STATUS_RECORDED = 'مسجل';

    public const STATUS_CONFIRMED = 'مؤكد';

    /** @var list<string> */
    protected $fillable = [
        'source_type', 'source_id', 'category_id', 'amount', 'received_at',
        'confirmed_at', 'confirmed_by', 'tax_invoice_id', 'external_document_path', 'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }
}
