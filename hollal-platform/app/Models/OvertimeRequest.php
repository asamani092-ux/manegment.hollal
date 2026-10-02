<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OvertimeRequest extends Model
{
    /** @var list<string> */
    protected $fillable = ['employee_id', 'date', 'hours', 'reason', 'status'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date' => 'date', 'hours' => 'decimal:2'];
    }
}
