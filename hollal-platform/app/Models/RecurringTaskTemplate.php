<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringTaskTemplate extends Model
{
    use SoftDeletes;

    public const PATTERN_WEEKLY = 'أسبوعي';

    public const PATTERN_MONTHLY = 'شهري';

    /** @var list<string> */
    protected $fillable = [
        'title', 'description', 'required_evidence', 'assigned_to_id', 'created_by',
        'project_id', 'priority', 'pattern', 'day_of_week', 'day_of_month',
        'is_active', 'last_generated_on',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_generated_on' => 'date',
            'day_of_week' => 'integer',
            'day_of_month' => 'integer',
        ];
    }

    public function isDueOn(\Carbon\CarbonInterface $date): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($this->pattern) {
            self::PATTERN_WEEKLY => (int) $date->dayOfWeek === (int) $this->day_of_week,
            self::PATTERN_MONTHLY => (int) $date->day === (int) $this->day_of_month,
            default => false,
        };
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }
}
