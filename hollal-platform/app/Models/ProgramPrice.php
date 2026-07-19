<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 06A-B1 — per-service program price. Quotes (05-B3) pull from here.
 */
class ProgramPrice extends Model
{
    use SoftDeletes;

    public const SERVICE_PACKAGE = 'حقيبة';

    public const SERVICE_TRAINING = 'تدريب';

    public const SERVICE_VISIT = 'زيارة';

    public const SERVICE_CONSULTATION = 'استشارة';

    public const SERVICE_MEASUREMENT = 'قياس';

    /** @var list<string> */
    public const SERVICES = [
        self::SERVICE_PACKAGE,
        self::SERVICE_TRAINING,
        self::SERVICE_VISIT,
        self::SERVICE_CONSULTATION,
        self::SERVICE_MEASUREMENT,
    ];

    /** @var list<string> */
    protected $fillable = ['program_id', 'service_type', 'unit_price', 'currency', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
