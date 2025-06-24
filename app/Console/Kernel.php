<?php
namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Carbon\Carbon;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\FetchAttendanceRecords::class,
        Commands\ProcessAttendanceShifts::class,
        Commands\GenerateMonthlyAttendanceReport::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Fetch attendance records every minute
        $schedule->command('update:attendance-records')
            ->hourly()
            ->withoutOverlapping()
            ->timezone('Africa/Nairobi');

        // Process attendance shifts - runs daily at 5:30 AM
        // This processes the previous day's shifts after all punches are likely complete
        $schedule->command('process:shifts')
            ->hourly()
            ->withoutOverlapping()
            ->timezone('Africa/Nairobi')
            ->appendOutputTo(storage_path('logs/shift-processing.log'));

        // Monthly attendance report
        $schedule->command('report:monthly-attendance')
            ->when(function () {
                $now = now('Africa/Nairobi');

                // Calculate the last day of the current month
                $lastDayOfMonth = Carbon::now('Africa/Nairobi')->endOfMonth();

                // Find the last weekday of the month
                $lastWeekdayOfMonth = $lastDayOfMonth->copy();
                while ($lastWeekdayOfMonth->isWeekend()) {
                    $lastWeekdayOfMonth->subDay();
                }

                // Calculate the day before the last weekday of the month
                $dayBeforeLastWeekday = $lastWeekdayOfMonth->copy()->subDay();

                // Check if today is the last weekday of the month OR the day before it
                $isLastWeekdayOrDayBefore = $now->isSameDay($lastWeekdayOfMonth) || $now->isSameDay($dayBeforeLastWeekday);

                return $isLastWeekdayOrDayBefore;
            })
            ->dailyAt('10:25') // <-- This ensures it runs once at 10:25 AM
            ->withoutOverlapping()
            ->timezone('Africa/Nairobi');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
