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
                
                // DEBUG: Check recovery state even for empty data
                $this->checkRecoveryState();
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

            // Check for recovery state
            $this->checkRecoveryState();

            $this->info("Attendance sync successful. Fetched: $total, New: " . ($insertedCount ?? 0));
            Log::info("Attendance sync completed successfully at " . now());

            return Command::SUCCESS;

        } catch (Throwable $e) {
            $errorMessage = "TAD Sync failed (IP: " . config('tad.ip') . "): " . $e->getMessage();
            Log::error($errorMessage, ['trace' => $e->getTraceAsString()]);

            // Use a consistent cache key for attendance sync errors
            $cacheKey = 'attendance_sync_error_notification';

            if (!Cache::has($cacheKey)) {
                try {
                    Mail::to(config('tad.notify_emails'))->send(new AttendanceSyncFailed($errorMessage));
                    Cache::put($cacheKey, true, now()->addSeconds(config('tad.cache_ttl.error')));
                    Log::info("Sent failure notification for attendance sync.");
                } catch (Throwable $mailException) {
                    Log::error("Failed to send failure notification: " . $mailException->getMessage());
                    // Still cache the error to prevent spam
                    Cache::put($cacheKey, true, now()->addSeconds(config('tad.cache_ttl.error')));
                }
            } else {
                Log::info("Skipping failure notification - already sent recently.");
            }

            // Always set the last error state for recovery detection
            Cache::put('attendance_sync_last_error', $errorMessage, now()->addSeconds(config('tad.cache_ttl.last_error')));
            Log::info("DEBUG: Set attendance_sync_last_error cache with TTL: " . config('tad.cache_ttl.last_error') . " seconds");
            
            $this->error($errorMessage);
            return Command::FAILURE;
        }
    }

    /**
     * Check and handle recovery state
     */
    private function checkRecoveryState()
    {
        Log::info("DEBUG: Checking for recovery state...");
        
        // Check if recovery cache exists
        $hasLastError = Cache::has('attendance_sync_last_error');
        Log::info("DEBUG: Cache::has('attendance_sync_last_error') = " . ($hasLastError ? 'true' : 'false'));
        
        if ($hasLastError) {
            $lastError = Cache::get('attendance_sync_last_error');
            Log::info("DEBUG: Found last error in cache: " . substr($lastError, 0, 100) . "...");
            
            try {
                Log::info("DEBUG: Attempting to send recovery notification...");
                Mail::to(config('tad.notify_emails'))->send(new AttendanceSyncResolved($lastError));
                Log::info("Recovered from previous error. Notified via email.");
                
                // Only remove the cache after successful notification
                Cache::forget('attendance_sync_last_error');
                Log::info("DEBUG: Removed attendance_sync_last_error from cache.");
                
            } catch (Throwable $mailException) {
                Log::error("Failed to send recovery notification: " . $mailException->getMessage());
                Log::error("DEBUG: Recovery notification failed, keeping cache entry for retry.");
                // Keep the cache entry so we can try again next time
            }
        } else {
            Log::info("DEBUG: No previous error found in cache - no recovery needed.");
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