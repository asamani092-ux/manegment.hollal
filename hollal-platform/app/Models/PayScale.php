<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayScale extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['name_ar', 'grades', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'grades' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Look up a grade definition by its label.
     *
     * @return array{label: string, base_amount: float|int}|null
     */
    public function grade(string $label): ?array
    {
        foreach ($this->grades ?? [] as $grade) {
            if (($grade['label'] ?? null) === $label) {
                return $grade;
            }
        }

        return null;
    }
}
