<?php
namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use App\Mail\AttendanceSyncFailed;
use App\Mail\AttendanceSyncResolved;

class FetchAttendanceRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:attendance-records';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Attendance Records';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $tad  = app('tad');
            $data = $tad->get_att_log()->to_array()['Row'] ?? [];

            $totalRecords = count($data);
            $newRecords   = 0;

            foreach ($data as $record) {
                $attendance = Attendance::firstOrCreate([
                    'pin'      => $record['PIN'],
                    'datetime' => $record['DateTime'],
                ], [
                    'verified'  => $record['Verified'] ?? null,
                    'status'    => $record['Status'] ?? null,
                    'work_code' => $record['WorkCode'] ?? null,
                ]);

                if ($attendance->wasRecentlyCreated) {
                    $newRecords++;
                }
            }

            // SUCCESS: if we previously had a failure, notify of recovery
            if (Cache::has('attendance_sync_last_error')) {
                $lastError = Cache::pull('attendance_sync_last_error');
                // Mail::to('versionaskari19@gmail.com','christine.mwende@mcdave.co.ke','joseph.uimbia@mcdave.co.ke','judith.kendi@mcdave.co.ke')->send(new AttendanceSyncResolved($lastError));

                Mail::to(['versionaskari19@gmail.com','christine.mwende@mcdave.co.ke','joseph.uimbia@mcdave.co.ke','judith.kendi@mcdave.co.ke'])->send(new AttendanceSyncResolved($lastError));
                Log::info("Attendance sync recovered: $lastError");
            }

            $this->info("Processed $totalRecords records, added $newRecords new attendance records.");
            Log::info("Attendance records fetched: $totalRecords total, $newRecords new records added.");
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $cacheKey     = 'attendance_sync_error:' . md5($errorMessage);

            Log::error("Attendance sync failed: $errorMessage");

            if (! Cache::has($cacheKey)) {
                // Mail::to('versionaskari19@gmail.com','christine.mwende@mcdave.co.ke','joseph.uimbia@mcdave.co.ke','judith.kendi@mcdave.co.ke')->send(new AttendanceSyncFailed($errorMessage));
                Mail::to(['versionaskari19@gmail.com','christine.mwende@mcdave.co.ke','joseph.uimbia@mcdave.co.ke','judith.kendi@mcdave.co.ke'])->send(new AttendanceSyncFailed($errorMessage));
                Cache::put($cacheKey, true, now()->addHour()); // Prevent re-sending for 1 hour
            }

            // Always record the latest failure (for recovery tracking)
            Cache::put('attendance_sync_last_error', $errorMessage, now()->addDay());

            $this->error("Failed to sync attendance: $errorMessage");
        }
    }
}
