<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Fire reminder emails as events approach. Runs hourly so the 3-day and 24-hour
// windows are caught promptly; the command itself is idempotent.
Schedule::command('events:send-reminders')->hourly()->withoutOverlapping();
