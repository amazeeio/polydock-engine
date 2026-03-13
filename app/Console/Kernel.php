<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    #[\Override]
    protected function schedule(Schedule $schedule): void
    {
        require base_path('routes/console.php');
    }

    /**
     * Register the commands for the application.
     */
    #[\Override]
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
