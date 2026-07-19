<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRunItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'base', 'allowances', 'deductions',
        'overtime_hours', 'overtime_amount', 'variables', 'gross', 'net',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'base' => 'decimal:2',
            'allowances' => 'decimal:2',
            'deductions' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'overtime_amount' => 'decimal:2',
            'gross' => 'decimal:2',
            'net' => 'decimal:2',
            'variables' => 'array',
        ];
    }

    /**
     * Recompute gross/net from the derived fields. Never set manually:
     * gross = base + allowances + overtime + variable additions
     * net   = gross - deductions - variable deductions
     */
    public function recalculate(): void
    {
        $variableAdditions = 0.0;
        $variableDeductions = 0.0;

        foreach ($this->variables ?? [] as $variable) {
            $amount = (float) ($variable['amount'] ?? 0);
            if (($variable['kind'] ?? null) === 'deduction') {
                $variableDeductions += $amount;
            } else {
                $variableAdditions += $amount;
            }
        }

        $this->gross = (float) $this->base + (float) $this->allowances + (float) $this->overtime_amount + $variableAdditions;
        $this->net = (float) $this->gross - (float) $this->deductions - $variableDeductions;
    }

    /** @return BelongsTo<PayrollRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
