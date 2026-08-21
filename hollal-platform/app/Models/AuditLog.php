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
        'role.updated' => 'تحديث دور',
        'role.created' => 'إنشاء دور',
        'role.deleted' => 'حذف دور',
        'chart_of_accounts.created' => 'إنشاء حساب في الدليل',
        'chart_of_accounts.updated' => 'تحديث حساب في الدليل',
        'chart_of_accounts.deleted' => 'حذف حساب من الدليل',
        'journal.posted' => 'ترحيل قيد يومي',
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

    /** Human-readable status derived from metadata when present. */
    public function displayStatus(): ?string
    {
        $meta = $this->metadata;
        if (! is_array($meta) || $meta === []) {
            return null;
        }

        if (isset($meta['status']) && is_scalar($meta['status'])) {
            return (string) $meta['status'];
        }

        if (! empty($meta['final'])) {
            return 'نهائي';
        }

        if (isset($meta['stage']) && is_scalar($meta['stage'])) {
            $stage = (string) $meta['stage'];
            if (isset($meta['next_stage']) && is_scalar($meta['next_stage'])) {
                return $stage.' ← '.(string) $meta['next_stage'];
            }

            return 'مرحلة: '.$stage;
        }

        if (isset($meta['file_type']) && is_scalar($meta['file_type'])) {
            return (string) $meta['file_type'];
        }

        return null;
    }
}
