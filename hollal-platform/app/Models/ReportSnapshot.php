<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 08-B1 — an immutable report snapshot.
 *
 * Once created, the payload is frozen: the model refuses updates and carries a
 * hash so any out-of-band tampering is detectable.
 */
class ReportSnapshot extends Model
{
    public const KIND_MONTHLY = 'monthly';

    public const KIND_PROJECT_DASHBOARD = 'project_dashboard';

    public const KIND_IMPACT = 'impact';

    public const KIND_KPI = 'kpi';

    /** @var list<string> */
    protected $fillable = ['kind', 'label', 'period', 'subject_id', 'payload', 'payload_hash', 'generated_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $snapshot) {
            throw new \RuntimeException('لقطة التقرير غير قابلة للتعديل بعد إنشائها');
        });

        static::deleting(function (self $snapshot) {
            throw new \RuntimeException('لقطة التقرير غير قابلة للحذف');
        });
    }

    public static function hashFor(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function isIntact(): bool
    {
        return $this->payload_hash === self::hashFor($this->payload);
    }

    /** @return BelongsTo<User, $this> */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
