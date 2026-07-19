<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasurementResponse extends Model
{
    public const PHASE_PRE = 'قبلي';

    public const PHASE_POST = 'بعدي';

    /** @var list<string> */
    protected $fillable = [
        'project_id', 'measurement_form_id', 'beneficiary_group_id',
        'phase', 'answers', 'total_score', 'max_score',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'total_score' => 'decimal:2',
            'max_score' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<MeasurementForm, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(MeasurementForm::class, 'measurement_form_id');
    }

    /** @return BelongsTo<BeneficiaryGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(BeneficiaryGroup::class, 'beneficiary_group_id');
    }

    public function percent(): float
    {
        return (float) $this->max_score > 0
            ? round(((float) $this->total_score / (float) $this->max_score) * 100, 2)
            : 0.0;
    }
}
