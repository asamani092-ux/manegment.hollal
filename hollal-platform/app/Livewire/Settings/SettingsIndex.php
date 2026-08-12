<?php

namespace App\Livewire\Settings;

use App\Models\PlatformSetting;
use App\Support\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * 00-B6 — platform settings editor grouped by section. Each save records the
 * old and new value to audit_logs via the Setting helper and busts the cache.
 */
class SettingsIndex extends Component
{
    use AuthorizesRequests;

    /** @var array<string, mixed> key => current input value */
    public array $values = [];

    public function mount(): void
    {
        $this->authorize('settings.manage');

        foreach (PlatformSetting::all() as $setting) {
            // Dots in keys collide with Livewire's dot-notation binding, so the
            // form uses "__" as a separator and we translate back on save.
            $this->values[self::safeKey($setting->key)] = $setting->type === 'boolean'
                ? (bool) $setting->typedValue()
                : (string) ($setting->value ?? '');
        }
    }

    public static function safeKey(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    public static function helpFor(string $key): string
    {
        return match ($key) {
            'general.platform_name' => 'الاسم الظاهر في الشريط العلوي والمستندات الرسمية.',
            'general.logo_path' => 'مسار ملف الشعار داخل التخزين العام.',
            'general.timezone' => 'المنطقة الزمنية لحساب المواعيد والتنبيهات (مثل Asia/Riyadh).',
            'notifications.task_due_days_before' => 'كم يومًا قبل الاستحقاق يُرسل تنبيه المهمة.',
            'notifications.task_escalation_hours' => 'بعد كم ساعة تُصعَّد المهمة المتأخرة.',
            'notifications.contract_expiry_days' => 'أيام التنبيه قبل انتهاء العقد، كمصفوفة أرقام.',
            'notifications.meeting_reminder_minutes' => 'التذكير قبل الاجتماع بالدقائق.',
            'notifications.partnership_stale_days' => 'بعد كم يوم تُعد الشراكة راكدة في مرحلتها.',
            'notifications.decision_stale_days' => 'بعد كم يوم يُعد القرار المفتوح متأخرًا.',
            'finance.tax_rate' => 'نسبة الضريبة المستخدمة في الفواتير (مثال 0.15).',
            'finance.currency' => 'رمز العملة المعروض في المبالغ.',
            'finance.chain_mode' => 'نمط سلسلة اعتماد الصرف: كامل أو مختصر.',
            'finance.skip_missing_dept_manager' => 'تخطي مرحلة مدير القسم إن لم يُعيَّن.',
            'finance.budget_alert_threshold' => 'نسبة الإنفاق التي تطلق تنبيه الموازنة.',
            'finance.tax.mode' => 'وضع الفوترة: داخلي أو خارجي.',
            'finance.tax.seller_name' => 'اسم البائع الظاهر على الفاتورة الضريبية.',
            'finance.tax.seller_vat_number' => 'الرقم الضريبي للبائع في الفاتورة.',
            'hr.evaluation_cycle' => 'دورة التقييم الدوري (ربع سنوي أو غيره).',
            'hr.evaluation_window_days' => 'عدد أيام نافذة تعبئة التقييم.',
            'hr.overtime_monthly_days' => 'أيام العمل الإضافي المحتسبة شهريًا.',
            'attendance.workload_threshold' => 'حد المهام المفتوحة قبل تنبيه العبء.',
            'links.default_expiry_days' => 'مدة صلاحية رابط بوابة الشريك بالأيام.',
            'links.max_active_per_partnership' => 'أقصى عدد روابط سارية لكل شراكة.',
            'aging.task_stale_days' => 'بعد كم يوم تُعد المهمة راكدة.',
            'aging.project_stale_days' => 'بعد كم يوم يُعد المشروع راكدًا.',
            'attendance.monthly_working_days' => 'أيام الدوام المعتمدة لحساب الشهر.',
            'maintenance.enabled' => 'تفعيل وضع الصيانة لإيقاف الدخول التشغيلي.',
            'maintenance.message' => 'الرسالة المعروضة أثناء الصيانة.',
            'backup.last_run_at' => 'وقت آخر نسخة احتياطية (للعرض).',
            'backup.retention_days' => 'كم يومًا تُحفظ النسخ الاحتياطية.',
            default => 'يضبط تشغيل المنصة؛ غيّره بعد التأكد من أثره.',
        };
    }

    public function save(): void
    {
        $this->authorize('settings.manage');

        foreach ($this->values as $safeKey => $value) {
            Setting::set(str_replace('__', '.', $safeKey), $value);
        }

        $this->dispatch('toast', type: 'success', message: 'تم حفظ الإعدادات');
    }

    /**
     * Settings grouped by their section (first key segment).
     *
     * @return Collection<string, Collection<int, PlatformSetting>>
     */
    public function getGroupedProperty(): Collection
    {
        return PlatformSetting::orderBy('key')->get()
            ->groupBy(fn (PlatformSetting $setting) => explode('.', $setting->key, 2)[0]);
    }

    public function render(): View
    {
        return view('livewire.settings.settings-index', [
            'grouped' => $this->grouped,
        ])->layout('layouts.app', ['title' => 'إعدادات المنصة']);
    }
}
