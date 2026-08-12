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
        'permissions.role_synced' => 'مزامنة صلاحيات دور',
        'backup.created' => 'إنشاء نسخة احتياطية',
        'settings.updated' => 'تحديث إعدادات',
        'report.exported' => 'تصدير تقرير',
        'expense.approved' => 'اعتماد صرف',
        'role.updated' => 'تحديث دور',
        'file.download' => 'تنزيل ملف',
        'auth.login_failure' => 'فشل تسجيل الدخول',
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
}
