<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosisAnswer extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['partnership_id', 'question_id', 'value', 'created_at'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Partnership, $this> */
    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class);
    }

    /** @return BelongsTo<DiagnosisQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(DiagnosisQuestion::class, 'question_id');
    }
}
