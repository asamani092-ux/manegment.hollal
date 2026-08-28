<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationTemplateItem extends Model
{
    public const SECTION_MANAGER = 'مدير';

    public const SECTION_HR = 'موارد';

    /** @var list<string> */
    public const SECTIONS = [self::SECTION_MANAGER, self::SECTION_HR];

    /** @var list<string> */
    protected $fillable = [
        'evaluation_template_id', 'section', 'question_text', 'weight', 'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<EvaluationTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'evaluation_template_id');
    }
}
