<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasurementQuestion extends Model
{
    /** @var list<string> */
    protected $fillable = ['measurement_form_id', 'text', 'max_score', 'position'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['max_score' => 'integer', 'position' => 'integer'];
    }

    /** @return BelongsTo<MeasurementForm, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(MeasurementForm::class, 'measurement_form_id');
    }
}
