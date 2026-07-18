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
                    <x-ds-form-group :label="$setting->label_ar ?? $setting->key" :for="'set-'.$setting->id">
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
                        <small class="ds-text-muted" dir="ltr">{{ $setting->key }}</small>
                    </x-ds-form-group>
                @endforeach
            </section>
        @endforeach

        <div class="ds-page-toolbar">
            <button type="submit" class="ds-btn ds-btn-primary">
                <i class="fas fa-save" aria-hidden="true"></i>
                حفظ الإعدادات
            </button>
        </div>
    </form>
</x-ds-page>
