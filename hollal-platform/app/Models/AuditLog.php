<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only security audit log — no updates, no soft deletes.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'action',
        'target_type',
        'target_id',
        'metadata',
        'ip_address',
        'created_at',
    ];

    /** @var array<string, string> */
    public const ACTION_LABELS = [
        'expense.paid' => 'تسجيل دفع مصروف',
        'expense.approved' => 'اعتماد مصروف',
        'expense.rejected' => 'رفض مصروف',
        'report.exported' => 'تصدير تقرير',
        'audit_log.exported' => 'تصدير سجل النشاط',
        'asset.condition_changed' => 'تغيير حالة أصل',
        'structure.transfer' => 'نقل موظف',
        'permissions.exceptional_granted' => 'منح استثناء صلاحية',
        'login' => 'تسجيل دخول',
        'logout' => 'تسجيل خروج',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }

    public static function labelFor(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? $action;
    }

    /** success|failure from metadata */
    public function outcomeStatus(): string
    {
        $meta = $this->metadata ?? [];
        if (($meta['failed'] ?? false) === true || ($meta['success'] ?? null) === false) {
            return 'فشل';
        }

        return 'نجاح';
    }

    public function outcomeReason(): ?string
    {
        $meta = $this->metadata ?? [];

        return $meta['error'] ?? $meta['reason'] ?? $meta['failure_reason'] ?? null;
    }
}
