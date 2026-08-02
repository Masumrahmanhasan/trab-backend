<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expire subscriptions whose trial or paid period has ended (hourly).
Schedule::command('subscriptions:check-expiry')->hourly();

// Remind users whose subscription expires within 7 days (daily at 9am).
Schedule::command('subscriptions:send-reminders')->dailyAt('09:00');
