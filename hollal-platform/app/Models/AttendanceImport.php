<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceImport extends Model
{
    public const STATUS_DRAFT = 'مسودة';

    public const STATUS_NEEDS_MATCH = 'بانتظار_مطابقة';

    public const STATUS_DONE = 'مكتمل';

    /** @var list<string> */
    protected $fillable = [
        'file_path',
        'source_label',
        'import_month',
        'status',
        'column_mapping',
        'staged_rows',
        'unmatched_rows',
        'replaced',
        'period_from',
        'period_to',
        'rows_count',
        'uploaded_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'column_mapping' => 'array',
            'staged_rows' => 'array',
            'unmatched_rows' => 'array',
            'replaced' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
