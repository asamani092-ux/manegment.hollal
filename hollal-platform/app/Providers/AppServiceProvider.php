<?php

namespace App\Providers;

use App\Models\MailSetting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('files', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // 05-B5 — the partner portal is public (token-only), so it is rate-limited by IP.
        RateLimiter::for('portal', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        $this->applyMailSettings();
    }

    /**
     * 00-B3 — apply stored SMTP settings to the runtime mailer so both
     * interactive and queued mail use the configured credentials.
     */
    private function applyMailSettings(): void
    {
        try {
            if (! Schema::hasTable('mail_settings')) {
                return;
            }

            MailSetting::query()->first()?->applyToConfig();
        } catch (\Throwable) {
            // Never let mail configuration break application boot.
        }
    }
}
