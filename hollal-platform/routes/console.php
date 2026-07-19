<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tasks:notify-due-soon')->dailyAt('08:00');
Schedule::command('tasks:notify-overdue')->hourly();
Schedule::command('contracts:notify-expiring')->dailyAt('08:30');
Schedule::command('reports:generate-weekly')->weeklyOn(4, '16:00');
Schedule::command('attendance:apply-monthly-overtime')->monthlyOn(1, '00:10');
Schedule::command('tasks:generate-recurring')->dailyAt('01:00');
