<x-ds-page>
    <x-ds-page-header title="إعدادات الإشعارات والبريد (SMTP)" />

    <section class="ds-section">
        <form wire:submit="save">
            <x-ds-form-group label="خادم البريد (Host)" for="mail-host" :error="$errors->first('host')">
                <input type="text" id="mail-host" class="ds-input" wire:model="host" dir="ltr"
                       placeholder="smtp.example.com">
            </x-ds-form-group>

            <x-ds-form-group label="المنفذ (Port)" for="mail-port" :error="$errors->first('port')">
                <input type="number" id="mail-port" class="ds-input" wire:model="port" dir="ltr"
                       placeholder="587">
            </x-ds-form-group>

            <x-ds-form-group label="التشفير" for="mail-encryption" :error="$errors->first('encryption')">
                <select id="mail-encryption" class="ds-input" wire:model="encryption">
                    <option value="">بدون</option>
                    <option value="tls">TLS</option>
                    <option value="ssl">SSL</option>
                </select>
            </x-ds-form-group>

            <x-ds-form-group label="اسم المستخدم" for="mail-username" :error="$errors->first('username')">
                <input type="text" id="mail-username" class="ds-input" wire:model="username" dir="ltr"
                       autocomplete="off">
            </x-ds-form-group>

            <x-ds-form-group label="كلمة المرور" for="mail-password" :error="$errors->first('password')">
                <input type="password" id="mail-password" class="ds-input" wire:model="password" dir="ltr"
                       autocomplete="new-password"
                       placeholder="{{ $hasStoredPassword ? '•••••••• (محفوظة — اتركها فارغة للإبقاء عليها)' : 'أدخل كلمة المرور' }}">
            </x-ds-form-group>

            <x-ds-form-group label="عنوان المُرسِل" for="mail-from-address" :error="$errors->first('from_address')">
                <input type="email" id="mail-from-address" class="ds-input" wire:model="from_address" dir="ltr"
                       placeholder="no-reply@example.com">
            </x-ds-form-group>

            <x-ds-form-group label="اسم المُرسِل" for="mail-from-name" :error="$errors->first('from_name')">
                <input type="text" id="mail-from-name" class="ds-input" wire:model="from_name"
                       placeholder="منصة حلّل الإدارية">
            </x-ds-form-group>

            <div class="ds-page-toolbar">
                <button type="submit" class="ds-btn ds-btn-primary">
                    <i class="fas fa-save" aria-hidden="true"></i>
                    حفظ الإعدادات
                </button>
                <button type="button" class="ds-btn ds-btn-outline" wire:click="sendTest">
                    <i class="fas fa-paper-plane" aria-hidden="true"></i>
                    إرسال رسالة اختبار
                </button>
            </div>
        </form>
    </section>
</x-ds-page>
