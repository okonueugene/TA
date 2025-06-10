<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use App\Mail\AttendanceSyncFailed;
use App\Mail\AttendanceSyncResolved;
use Throwable;

class FetchAttendanceRecords extends Command
{
    protected $signature = 'update:attendance-records';
    protected $description = 'Fetch and store new attendance records from the biometric device.';

public function handle()
{
    Log::info("Starting attendance sync at " . now());

    try {
        $tadIp = config('tad.ip');
        Log::info("Connecting to TAD device at IP: $tadIp");

        $tad = app('tad');
        $response = $tad->get_att_log()->to_array();
        $rows = $response['Row'] ?? [];

        if (empty($rows)) {
            $this->info("No attendance data found.");
            Log::info("No attendance records found from TAD.");
            return Command::SUCCESS;
        }

        $records = $this->is_assoc($rows) ? [$rows] : $rows;
        $total = count($records);
        Log::info("Fetched $total record(s) from TAD.");

        $pins = collect(array_column($records, 'PIN'))->unique();
        Log::info("Extracted unique PINs (count: {$pins->count()})");

        $existingKeys = collect();

        if ($pins->isNotEmpty()) {
            $pins->chunk(1000)->each(function ($chunk, $index) use (&$existingKeys) {
                Log::info("Querying existing keys for PIN chunk $index...");
                $keys = Attendance::whereIn('pin', $chunk->all())
                    ->select(DB::raw("CONCAT(pin, '|', datetime) as attendance_key"))
                    ->pluck('attendance_key');

                Log::info("Found " . $keys->count() . " existing keys in chunk $index.");
                $existingKeys = $existingKeys->merge($keys);
            });
        }

        $existingKeys = $existingKeys->flip()->toArray();
        Log::info("Prepared existing attendance keys lookup (total keys: " . count($existingKeys) . ")");

        $newRecords = [];
        $skipped = 0;

        foreach ($records as $row) {
            if (!isset($row['PIN'], $row['DateTime'])) {
                $skipped++;
                continue;
            }

            $key = $row['PIN'] . '|' . $row['DateTime'];
            if (!isset($existingKeys[$key])) {
                $newRecords[] = [
                    'pin'        => $row['PIN'],
                    'datetime'   => $row['DateTime'],
                    'verified'   => $row['Verified'] ?? null,
                    'status'     => $row['Status'] ?? null,
                    'work_code'  => $row['WorkCode'] ?? null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }
        }

        Log::info("Filtered new records: " . count($newRecords) . ", Skipped records: $skipped");

        if (!empty($newRecords)) {
            $insertedCount = 0;
            $chunkSize = 1000;

            DB::transaction(function () use ($newRecords, $chunkSize, &$insertedCount) {
                $chunks = array_chunk($newRecords, $chunkSize);
                Log::info("Inserting new attendance records in " . count($chunks) . " chunk(s).");

                foreach ($chunks as $i => $chunk) {
                    Attendance::insert($chunk);
                    Log::info("Inserted chunk #" . ($i + 1) . " with " . count($chunk) . " record(s).");
                    $insertedCount += count($chunk);
                }

            });

            Log::info("Finished inserting new records. Total inserted: $insertedCount");
        } else {
            Log::info("No new attendance records to insert.");
        }

        if (Cache::has('attendance_sync_last_error')) {
            $lastError = Cache::pull('attendance_sync_last_error');
            Mail::to(config('tad.notify_emails'))->send(new AttendanceSyncResolved($lastError));
            Log::info("Recovered from previous error. Notified via email.");
        }

        $this->info("Attendance sync successful. Fetched: $total, New: " . ($insertedCount ?? 0));
        Log::info("Attendance sync completed successfully at " . now());

        return Command::SUCCESS;

    } catch (Throwable $e) {
        $errorMessage = "TAD Sync failed (IP: " . config('tad.ip') . "): " . $e->getMessage();
        Log::error($errorMessage, ['trace' => $e->getTraceAsString()]);

        $cacheKey = 'attendance_sync_error:' . md5($errorMessage);

        if (!Cache::has($cacheKey)) {
            Mail::to(config('tad.notify_emails'))->send(new AttendanceSyncFailed($errorMessage));
            Cache::put($cacheKey, true, now()->addSeconds(config('tad.cache_ttl.error')));
            Log::warning("Sent failure notification for attendance sync.");
        }

        Cache::put('attendance_sync_last_error', $errorMessage, now()->addSeconds(config('tad.cache_ttl.last_error')));
        $this->error($errorMessage);
        return Command::FAILURE;
    }
}

    /**
     * Check if an array is associative
     */
    private function is_assoc(array $arr): bool
    {
        return $arr !== [] && array_keys($arr) !== range(0, count($arr) - 1);
    }
}