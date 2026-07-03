<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Employee contract — soft deletes only.
 * Time: O(1) single | list O(n).
 */
class Contract extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var list<string> */
    public const STATUSES = ['active', 'expired', 'terminated', 'pending'];

    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'value',
        'contract_file',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'value' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
