<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Prune activity-log entries older than config('activitylog.delete_records_older_than_days') (365)
Schedule::command('activitylog:clean')->dailyAt('03:00');

// Daily in-app reminders for overdue and soon-due books (app timezone)
Schedule::command('app:send-due-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping();
