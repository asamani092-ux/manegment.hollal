<?php

namespace App\Support;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Cache;

/**
 * 00-B6 — runtime settings helper. Reads from platform_settings with a 5-minute
 * per-key cache; writes record old+new to audit_logs and bust the cache.
 */
class Setting
{
    private const CACHE_PREFIX = 'platform_setting:';

    private const CACHE_TTL = 300; // 5 minutes

    public static function get(string $key, mixed $default = null): mixed
    {
        $cached = Cache::remember(self::CACHE_PREFIX.$key, self::CACHE_TTL, function () use ($key) {
            $setting = PlatformSetting::query()->where('key', $key)->first();

            if (! $setting) {
                return ['missing' => true];
            }

            return ['value' => $setting->typedValue()];
        });

        if (isset($cached['missing'])) {
            return $default;
        }

        return $cached['value'];
    }

    public static function set(string $key, mixed $value, ?User $actor = null): PlatformSetting
    {
        $setting = PlatformSetting::query()->firstOrNew(['key' => $key]);
        $type = $setting->type ?? 'string';

        $oldEncoded = $setting->value;
        $newEncoded = PlatformSetting::encodeValue($value, $type);

        $setting->old_value = $oldEncoded;
        $setting->value = $newEncoded;
        $setting->updated_by = ($actor ?? auth()->user())?->id;
        $setting->save();

        if ($oldEncoded !== $newEncoded) {
            app(AuditLogService::class)->record(
                action: 'settings.updated',
                target: $setting,
                metadata: [
                    'key' => $key,
                    'old_value' => $oldEncoded,
                    'new_value' => $newEncoded,
                ],
                actor: $actor,
            );
        }

        Cache::forget(self::CACHE_PREFIX.$key);

        return $setting;
    }

    public static function forget(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX.$key);
    }
}
