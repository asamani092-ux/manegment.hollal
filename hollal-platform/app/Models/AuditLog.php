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

    /** @var array<string, string> keep stored keys; Arabic is display-only. */
    public const ACTION_LABELS = [
        'audit_log.exported' => 'تصدير سجل النشاط',
        'asset.condition_changed' => 'تغيير حالة أصل',
        'structure.transfer' => 'نقل هيكلي',
        'permissions.exceptional_granted' => 'منح صلاحية استثنائية',
        'permissions.exceptional_revoked' => 'سحب صلاحية استثنائية',
        'permissions.role_synced' => 'مزامنة صلاحيات دور',
        'backup.created' => 'إنشاء نسخة احتياطية',
        'settings.updated' => 'تحديث إعدادات',
        'report.exported' => 'تصدير تقرير',
        'report.document_archived' => 'أرشفة تقرير في المستودع',
        'weekly_report.generated' => 'توليد تقرير أسبوعي',
        'expense.approved' => 'اعتماد صرف',
        'expense.rejected' => 'رفض صرف',
        'expense.returned' => 'إعادة صرف للمراجعة',
        'expense.paid' => 'صرف مدفوع',
        'role.created' => 'إنشاء دور',
        'role.updated' => 'تحديث دور',
        'role.deleted' => 'حذف دور',
        'file.download' => 'تنزيل ملف',
        'auth.login_failure' => 'فشل تسجيل الدخول',
        'auth.login_success' => 'تسجيل دخول ناجح',
        'partnership_contract.signed' => 'توقيع عقد شراكة',
    ];

    protected $fillable = [
        'actor_id',
        'action',
        'target_type',
        'target_id',
        'metadata',
        'ip_address',
        'created_at',
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

    /** نجح/فشل — whether the audited attempt itself succeeded. */
    public function displayStatus(): string
    {
        return $this->isFailure() ? 'فشل' : 'نجح';
    }

    /** Failure reason, only meaningful when {@see displayStatus()} is «فشل». */
    public function statusReason(): ?string
    {
        if (! $this->isFailure()) {
            return null;
        }

        $meta = $this->metadata;
        $reason = is_array($meta)
            ? ($meta['reason'] ?? $meta['error'] ?? $meta['failure_reason'] ?? null)
            : null;

        if (is_scalar($reason)) {
            return (string) $reason;
        }

        return match ($this->action) {
            'auth.login_failure' => 'بيانات الدخول غير صحيحة',
            default => null,
        };
    }

    private function isFailure(): bool
    {
        $meta = $this->metadata;
        if (is_array($meta)) {
            if (($meta['failed'] ?? false) === true) {
                return true;
            }
            if (($meta['success'] ?? null) === false) {
                return true;
            }
        }

        return str_ends_with($this->action, '_failure')
            || str_ends_with($this->action, '.failed')
            || str_ends_with($this->action, '_failed');
    }
}
