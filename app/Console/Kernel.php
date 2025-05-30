<?php
namespace App\Console;
use Illuminate\Console\Scheduling\Schedule;
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
        Commands\GenerateMonthlyAttendanceReport::class
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

        // Alternative: Process shifts every hour during business hours
        // Uncomment this if you prefer more frequent processing
        /*
        $schedule->command('process:shifts')
            ->hourly()
            ->between('06:00', '23:00')
            ->withoutOverlapping()
            ->timezone('Africa/Nairobi')
            ->appendOutputTo(storage_path('logs/shift-processing.log'));
        */

        // Monthly attendance report
        $schedule->command('report:monthly-attendance')
            ->when(function () {
                return now('Africa/Nairobi')->isSameDay(now('Africa/Nairobi')->startOfMonth()->addDays(23)) && // 24th of month
                       now('Africa/Nairobi')->between(
                           now('Africa/Nairobi')->setTime(15, 40),
                           now('Africa/Nairobi')->setTime(15, 50)
                       );
            })
            ->everyMinute()
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
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}