<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceCycle extends Model
{
    /** @var list<string> */
    protected $fillable = ['month', 'status', 'approved_by', 'closed_at', 'reopened_reason'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    /** @return HasMany<AttendanceCycleDay, $this> */
    public function days(): HasMany
    {
        return $this->hasMany(AttendanceCycleDay::class);
    }
}
