<x-ds-page>
    <x-ds-page-header title="إعدادات المنصة" />

    @php
        $sectionLabels = [
            'general' => 'عام',
            'notifications' => 'الإشعارات والتوقيتات',
            'finance' => 'المالية',
            'hr' => 'الموارد البشرية',
            'attendance' => 'الحضور',
            'links' => 'الروابط',
        ];
    @endphp

    <form wire:submit="save">
        @foreach ($grouped as $section => $settings)
            <section class="ds-section ds-section-spaced">
                <h2 class="ds-section-title">
                    <i class="fas fa-sliders ds-section-icon"></i>
                    {{ $sectionLabels[$section] ?? $section }}
                </h2>

                @foreach ($settings as $setting)
                    <x-ds-form-group :label="$setting->label_ar ?? $setting->key" :for="'set-'.$setting->id" :hint="\App\Livewire\Settings\SettingsIndex::helpFor($setting->key)">
                        @php($fieldKey = \App\Livewire\Settings\SettingsIndex::safeKey($setting->key))
                        @if ($setting->type === 'boolean')
                            <label class="ds-checkbox-label">
                                <input type="checkbox" wire:model="values.{{ $fieldKey }}">
                                <span>{{ $setting->label_ar ?? $setting->key }}</span>
                            </label>
                        @else
                            <input type="text" id="set-{{ $setting->id }}" class="ds-input"
                                   wire:model="values.{{ $fieldKey }}"
                                   @if (in_array($setting->type, ['integer'], true)) inputmode="numeric" @endif>
                        @endif
                    </x-ds-form-group>
                @endforeach
            </section>
        @endforeach

        @if (auth()->user()->can('settings.manage'))
            <section class="ds-section ds-section-spaced">
                <h2 class="ds-section-title">
                    <i class="fas fa-link ds-section-icon"></i>
                    إعدادات مرتبطة
                </h2>
                <ul class="ds-link-list">
                    <li><a href="{{ route('settings.expenses') }}" class="ds-link">سلسلة اعتماد طلبات الصرف</a></li>
                    <li><a href="{{ route('settings.notifications') }}" class="ds-link">إعدادات البريد والإشعارات</a></li>
                </ul>
            </section>
        @endif

        <div class="ds-page-toolbar">
            <button type="submit" class="ds-btn ds-btn-primary">
                <i class="fas fa-save" aria-hidden="true"></i>
                حفظ الإعدادات
            </button>
        </div>
    </form>
</x-ds-page>
