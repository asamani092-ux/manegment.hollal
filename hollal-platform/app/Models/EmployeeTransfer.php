<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 09-B1 — an append-only record of a move between units/departments. History is
 * never overwritten: the previous placement stays readable here.
 */
class EmployeeTransfer extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id', 'from_org_unit_id', 'to_org_unit_id',
        'from_department_id', 'to_department_id', 'effective_on', 'reason', 'moved_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['effective_on' => 'date'];
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<OrgUnit, $this> */
    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'from_org_unit_id');
    }

    /** @return BelongsTo<OrgUnit, $this> */
    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'to_org_unit_id');
    }
}
