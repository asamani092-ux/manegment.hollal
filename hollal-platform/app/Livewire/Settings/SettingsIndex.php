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
