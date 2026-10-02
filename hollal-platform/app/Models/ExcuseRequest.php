<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExcuseRequest extends Model
{
    /** @var list<string> */
    protected $fillable = ['employee_id', 'date', 'from_time', 'to_time', 'reason', 'status', 'minutes'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
