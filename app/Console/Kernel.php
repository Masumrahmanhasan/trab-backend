<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Check for expired subscriptions daily at midnight
        $schedule->command('subscriptions:check-expiry')->daily();

        // Send subscription expiry reminders
        $schedule->command('subscriptions:send-reminders')->daily();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
