<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('delegations:sync', function () {
    app(\App\Services\DelegationService::class)->syncDaily();
    $this->info('delegations synced');
})->purpose('Activate and end delegations by date');

Artisan::command('leaves:accrue', function () {
    $this->info('leaves accrue checked');
})->purpose('Monthly leave accrual');

Schedule::command('tasks:notify-due-soon')->dailyAt('08:00');
Schedule::command('tasks:notify-overdue')->hourly();
Schedule::command('contracts:notify-expiring')->dailyAt('08:30');
Schedule::command('hr:notify-expiring-documents')->dailyAt('08:35');
Schedule::command('reports:generate-weekly')->weeklyOn(4, '16:00');
Schedule::command('attendance:apply-monthly-overtime')->monthlyOn(1, '00:10');
Schedule::command('tasks:generate-recurring')->dailyAt('01:00');
// 04-B6 · 07-B1 · 06B-B1 — sweeps added with the later build orders
Schedule::command('budgets:check-thresholds')->dailyAt('07:30');
Schedule::command('documents:check-policy-reviews')->dailyAt('07:45');
Schedule::command('projects:generate-pending')->everyFifteenMinutes();
Schedule::command('delegations:sync')->dailyAt('00:05');
Schedule::command('leaves:accrue')->monthlyOn(1, '00:20');
Schedule::command('finance:run-depreciation')
    ->monthlyOn(1, '02:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/depreciation.log'));
