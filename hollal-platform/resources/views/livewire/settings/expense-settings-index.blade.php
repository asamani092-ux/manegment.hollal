<x-ds-page>
    <x-ds-page-header title="احتياطي: نمط سلسلة الاعتماد" />

    <div class="ds-card ds-mb-3">
        <p>
            <strong>المصدر الأساسي</strong> لخطوات الاعتماد هو
            <a href="{{ route('settings.approval-rules') }}" class="ds-link">قواعد سلسلة الاعتماد حسب المبلغ</a>.
            عند وجود قاعدة نشطة لنوع العملية والمبلغ تُتجاهل إعدادات هذه الصفحة للنمط (كامل/مختصر).
        </p>
        <p class="ds-text-muted">تبقى خيارات هذه الشاشة كاحتياطي فقط إن لم تُعرَّف قواعد، ولخيار تخطي مدير القسم عند غيابه.</p>
    </div>

    <section class="ds-section">
        <form wire:submit="save">
            <x-ds-form-group label="نمط السلسلة (احتياطي)" for="chain-mode" :error="$errors->first('chain_mode')" hint="يُستخدم فقط عند غياب قاعدة ديناميكية مطابقة.">
                <select id="chain-mode" class="ds-input" wire:model="chain_mode">
                    <option value="full">كامل: مقدم الطلب ← مدير القسم ← التنفيذي ← المالية</option>
                    <option value="short">مختصر: مقدم الطلب ← التنفيذي ← المالية</option>
                </select>
            </x-ds-form-group>

            <x-ds-form-group label="تخطي مدير القسم عند غيابه" hint="ينطبق أيضاً على القواعد الديناميكية التي تتضمن مرحلة مدير القسم.">
                <label class="ds-checkbox-label">
                    <input type="checkbox" wire:model="skip_missing_department_manager">
                    <span>تخطي مرحلة مدير القسم إذا لم يُعيَّن مدير مباشر للموظف</span>
                </label>
            </x-ds-form-group>

            <div class="ds-page-toolbar">
                <button type="submit" class="ds-btn ds-btn-primary">
                    <i class="fas fa-save" aria-hidden="true"></i>
                    حفظ الإعدادات
                </button>
                <a href="{{ route('settings.approval-rules') }}" class="ds-btn ds-btn-outline">إدارة القواعد الديناميكية</a>
            </div>
        </form>
    </section>
</x-ds-page>
