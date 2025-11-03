<?php

namespace App\Console;

use App\Console\Commands\PruneExpiredMfaAttempts;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        /**
         * Cleanup expired Sanctum tokens
         *
         * @see https://laravel.com/docs/10.x/sanctum#revoking-tokens
         */
        $schedule->command('sanctum:prune-expired --hours=24')
            ->daily()
            ->onOneServer();

        /**
         * Cleanup expired MFA Attempt records
         *
         * @see PruneExpiredMfaAttempts
         */
        $schedule->command('mfa:prune-expired-attempts')
            ->daily()
            ->onOneServer();

        /**
         * Compile locator slip information for the day and apply it to DTR's remarks
         *
         * @see LocatorUpdateDtrRemarks
         */
        $schedule->command('locator-update-dtr-remarks')
            ->daily()
            ->onOneServer();

        /**
         * Computes time for today's locator slips.
         *
         * @see LocatorComputeTime
         */
        $schedule->command('locator-compute-time')
            ->daily()
            ->onOneServer();

    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
