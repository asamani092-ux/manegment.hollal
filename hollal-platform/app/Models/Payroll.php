<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Payroll — monthly salary record, soft deletes only.
 */
class Payroll extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'month',
        'base',
        'additions',
        'deductions',
        'net',
        'transfer_status',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'base' => 'decimal:2',
            'additions' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net' => 'decimal:2',
        ];
    }

    public static function computeNet(float|string $base, float|string $additions, float|string $deductions): string
    {
        return number_format((float) $base + (float) $additions - (float) $deductions, 2, '.', '');
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
