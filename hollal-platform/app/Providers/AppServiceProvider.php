<?php

namespace App\Providers;

use App\Events\AttendanceCycleClosed;
use App\Events\LeavePayImpactsRecorded;
use App\Events\ViolationApplied;
use App\Listeners\HrRemainderListener;
use App\Models\Delegation;
use App\Models\MailSetting;
use App\Models\OrgUnit;
use App\Models\User;
use App\Observers\OrgUnitObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
        OrgUnit::observe(OrgUnitObserver::class);
        Event::listen(AttendanceCycleClosed::class, [HrRemainderListener::class, 'onCycleClosed']);
        Event::listen(ViolationApplied::class, [HrRemainderListener::class, 'onViolationApplied']);
        Event::listen(LeavePayImpactsRecorded::class, [HrRemainderListener::class, 'onLeaveImpacts']);

        Gate::before(function ($user, string $ability) {
            if (! $user instanceof User) {
                return null;
            }
            static $inside = false;
            if ($inside) {
                return null;
            }
            $delegation = Delegation::query()
                ->where('delegate_id', $user->id)
                ->where('status', Delegation::STATUS_ACTIVE)
                ->whereDate('starts_on', '<=', today())
                ->whereDate('ends_on', '>=', today())
                ->first();
            if (! $delegation?->delegator) {
                return null;
            }
            $inside = true;
            try {
                $allowed = $delegation->delegator->hasPermissionTo($ability);
            } catch (\Throwable) {
                $allowed = false;
            }
            $inside = false;

            return $allowed ? true : null;
        });
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
