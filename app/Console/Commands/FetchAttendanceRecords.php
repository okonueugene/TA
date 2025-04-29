<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use Illuminate\Support\Facades\Log;

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
    $tad = app('tad');
    $data = $tad->get_att_log()->to_array()['Row'] ?? [];
    
    $totalRecords = count($data);
    $newRecords = 0;
    
    foreach ($data as $record) {
        $attendance = Attendance::firstOrCreate([
            'pin'      => $record['PIN'],
            'datetime' => $record['DateTime'],
        ], [
            'verified'  => $record['Verified'] ?? null,
            'status'    => $record['Status'] ?? null,
            'work_code' => $record['WorkCode'] ?? null,
        ]);
        
        // Count only newly created records
        if ($attendance->wasRecentlyCreated) {
            $newRecords++;
        }
    }
    
    if ($newRecords > 0) {
        $this->info("Processed $totalRecords records, added $newRecords new attendance records.");
        Log::info("Attendance records fetched: $totalRecords total, $newRecords new records added.");
    } else {
        $this->info("Processed $totalRecords records. No new records found.");
        Log::info("Attendance records checked: $totalRecords total, no new records to add.");
    }
    }
}