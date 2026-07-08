<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row expense workflow configuration.
 */
class ExpenseSetting extends Model
{
    protected $fillable = [
        'chain_mode',
        'skip_missing_department_manager',
    ];

    protected function casts(): array
    {
        return [
            'skip_missing_department_manager' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrFail();
    }
}
