<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Internal attendance shift (path-2): start/end, late grace, weekdays.
 */
class WorkShift extends Model
{
    /** @var list<string> Arabic weekday labels keyed by Carbon::dayOfWeek (0=Sun). */
    public const WEEKDAY_LABELS = [
        0 => 'الأحد',
        1 => 'الإثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ];

    /** @var list<string> */
    protected $fillable = [
        'name', 'start_time', 'end_time', 'grace_minutes', 'weekdays', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'grace_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<EmployeeProfile, $this> */
    public function profiles(): HasMany
    {
        return $this->hasMany(EmployeeProfile::class, 'work_shift_id');
    }

    /** Normalized HH:MM start. */
    public function startHm(): string
    {
        return $this->normalizeHm((string) $this->start_time);
    }

    /** Normalized HH:MM end. */
    public function endHm(): string
    {
        return $this->normalizeHm((string) $this->end_time);
    }

    /** Whether Carbon dayOfWeek is in this shift. */
    public function coversWeekday(int $dayOfWeek): bool
    {
        $days = array_map('intval', $this->weekdays ?? []);

        return in_array($dayOfWeek, $days, true);
    }

    /** Comma-separated Arabic weekday labels for UI. */
    public function weekdaysLabel(): string
    {
        $days = array_map('intval', $this->weekdays ?? []);
        sort($days);
        $labels = [];
        foreach ($days as $d) {
            if (isset(self::WEEKDAY_LABELS[$d])) {
                $labels[] = self::WEEKDAY_LABELS[$d];
            }
        }

        return $labels === [] ? '—' : implode('، ', $labels);
    }

    private function normalizeHm(string $raw): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})/', $raw, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return '08:00';
    }
}
