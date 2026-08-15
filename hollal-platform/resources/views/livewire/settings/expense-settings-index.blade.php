<x-ds-page>
    <x-ds-page-header title="إعدادات سلسلة اعتماد المصروفات" />

    <section class="ds-section">
        <form wire:submit="save">
            <x-ds-form-group label="نمط السلسلة" for="chain-mode" :error="$errors->first('chain_mode')" hint="الكامل يمر على مدير القسم ثم التنفيذي ثم المالية. المختصر يتجاوز مدير القسم.">
                <select id="chain-mode" class="ds-input" wire:model="chain_mode">
                    <option value="full">كامل: مقدم الطلب ← مدير القسم ← التنفيذي ← المالية</option>
                    <option value="short">مختصر: مقدم الطلب ← التنفيذي ← المالية</option>
                </select>
            </x-ds-form-group>

            <x-ds-form-group label="تخطي مدير القسم عند غيابه" hint="إن لم يكن للموظف مدير قسم معيّن تُتخطى هذه المرحلة تلقائيًا.">
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
            </div>
        </form>
    </section>
</x-ds-page>