<?php
namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\AttendanceProcessor;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessAttendanceShifts extends Command
{
    // This module acts as the Orchestrator and User Interface for the attendance processing logic.

    // Handles:
    // - Argument Parsing: Reads command-line arguments (e.g., date, --month).
    // - Date Range Determination: Determines the specific date(s) or month to process based on user input.
    // - High-Level Flow Control: Manages the overall execution flow (single day vs. month processing).
    // - Employee Identification: Queries the database to find unique employee PINs that have attendance activity within the relevant date window.
    // - Employee Batching/Chunking: Manages iterating through employees, optionally in chunks, to optimize memory usage.
    // - Transaction Management: Wraps the entire processing of an employee's shifts for a range in a database transaction to ensure atomicity (all or nothing).
    // - Logging Interface: Provides the console output ($this->info, $this->warn, $this->error) and passes this logging capability down to the AttendanceProcessor service.
    // - Error Handling (Top-Level): Catches and reports invalid month formats.

    // Does NOT Handle:
    // - Detailed shift calculation.
    // - Specific punch filtering logic.
    // - Database interactions for individual shift records (delegates to AttendanceProcessor).
    // - Business logic for shift types or overtime.

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:shifts
                            {date? : The specific date to process (YYYY-MM-DD).}
                            {--month= : Process shifts for a whole month (YYYY-MM).}
                            {--employee= : Process a specific employee by PIN.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processes attendance shifts for a given date range. Now uses optimized batch processing.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $specificDate = $this->argument('date');
        $monthOption  = $this->option('month');
        $employeePin  = $this->option('employee');

        $startDate = null;
        $endDate   = null;

        // --- 1. Determine the overall processing date range ---
        if ($specificDate) {
            try {
                $startDate = Carbon::parse($specificDate)->startOfDay();
                $endDate   = $startDate->copy()->endOfDay();
                $this->info("Processing shifts for date: {$startDate->toDateString()}.");
            } catch (\Exception $e) {
                $this->error("Invalid date format for 'date' argument. Please use YYYY-MM-DD.");
                return Command::FAILURE;
            }
        } elseif ($monthOption) {
            try {
                $startDate = Carbon::parse($monthOption)->startOfMonth();
                $endDate   = $startDate->copy()->endOfMonth();
                $this->info("Processing shifts for month: {$monthOption} ({$startDate->toDateString()} to {$endDate->toDateString()}).");
            } catch (\Exception $e) {
                $this->error("Invalid month format for '--month' option. Please use YYYY-MM.");
                return Command::FAILURE;
            }
        } else {
            // Default to processing yesterday if no date or month is specified,
            // as today's shifts might still be incomplete.
            $startDate = Carbon::today()->subDay()->startOfDay();
            $endDate   = $startDate->copy()->endOfDay();
            $this->info("Defaulting to processing shifts for yesterday: {$startDate->toDateString()}.");
        }

        // --- 2. Identify employees to process ---
        $employees = collect();
        if ($employeePin) {
            $employee = Employee::where('pin', $employeePin)->first();
            if ($employee) {
                $employees->push($employee);
                $this->info("Processing shifts for specific employee: {$employee->name} (PIN: {$employee->pin}).");
            } else {
                $this->error("Employee with PIN '{$employeePin}' not found.");
                return Command::FAILURE;
            }
        } else {
            // Fetch potential PINs from attendance records within an extended window
            // This extended window ensures we catch employees whose shifts might cross into the main processing period.
            // Adjust subDays/addDays as per your longest possible cross-day shift or required punch history.
            $activityStartDate = $startDate->copy()->subDays(2)->startOfDay();
            $activityEndDate   = $endDate->copy()->addDays(2)->endOfDay();

            $potentialRawPins = Attendance::whereBetween('datetime', [$activityStartDate, $activityEndDate])
                ->distinct()
                ->pluck('pin');

            // Normalize pins (remove leading 1 or 2 from clock-in/out pins, e.g., '1106' -> '106')
            $normalizedPins = $potentialRawPins
                ->map(fn($pin) => preg_replace('/^[12]/', '', $pin))
                ->filter(fn($pin) => ! empty($pin))
                ->unique()
                ->values();

            if ($normalizedPins->isEmpty()) {
                $this->info("No employee activity found in the specified range ({$activityStartDate->toDateString()} to {$activityEndDate->toDateString()}). Nothing to process.");
                return Command::SUCCESS;
            }
            // Fetch Employee models based on normalized pins
            $employees = Employee::whereIn('pin', $normalizedPins)->get();
            $this->info("Found {$employees->count()} employees with activity in the range.");
        }

        if ($employees->isEmpty()) {
            $this->info('No employees found to process based on filtered activity.');
            return Command::SUCCESS;
        }

        // --- 3. Iterate and process shifts for each employee ---
        $totalShiftsProcessed = 0;
        foreach ($employees as $employee) {
            // Create a unique lock key for this employee and processing period
            // Using date strings makes the lock key unique per employee per defined period.
            $lockKey = "attendance_process_lock_{$employee->pin}_{$startDate->toDateString()}_{$endDate->toDateString()}";
            $lock    = Cache::lock($lockKey, 300); // Acquire a 5-minute lock (adjust timeout as needed)

            if ($lock->get()) { // Attempt to acquire the lock
                try {
                    $this->comment("--- Processing shifts for employee: {$employee->name} (PIN: {$employee->pin}) ---");

                    // Initialize AttendanceProcessor with the console logger
                    $attendanceProcessor = new AttendanceProcessor(function ($level, $message) {
                        if ($level === 'info') {
                            $this->info($message);
                        } elseif ($level === 'warn') {
                            $this->warn($message);
                        } elseif ($level === 'error') {
                            $this->error($message);
                        }

                    });

                    // Wrap the processing for each employee in a database transaction.
                    // This ensures atomicity: all shifts for this employee in this range are saved, or none are if an error occurs.
                    DB::beginTransaction();

                    // *** CORE CALL TO THE BATCH PROCESSING METHOD ***
                    $shiftsProcessedForEmployee = $attendanceProcessor->processEmployeeShiftsForDateRange(
                        $employee,  // Pass the Employee model (not just pin) for context in the service
                        $startDate, // Start of the overall processing range
                        $endDate    // End of the overall processing range
                    );

                    DB::commit(); // Commit transaction if successful

                    $totalShiftsProcessed += $shiftsProcessedForEmployee;
                    $this->info("Completed processing for {$employee->name}. Total shifts recorded: {$shiftsProcessedForEmployee}.");

                } catch (\Exception $e) {
                    DB::rollBack(); // Rollback transaction on error
                    $this->error("Error processing shifts for employee {$employee->name} (PIN: {$employee->pin}): " . $e->getMessage());
                    // Log the full exception for debugging
                    Log::error("Attendance Processing Error for PIN {$employee->pin}: " . $e->getMessage(), [
                        'exception'    => $e,
                        'employee_pin' => $employee->pin,
                        'start_date'   => $startDate->toDateString(),
                        'end_date'     => $endDate->toDateString(),
                    ]);
                } finally {
                    $lock->release(); // Always release the lock, even if an error occurred
                }
            } else {
                $this->warn("Skipping employee {$employee->name} (PIN: {$employee->pin}): another process is already running for this employee and period.");
            }
        }

        $this->info("Attendance shifts processing completed. Total shifts processed in this run: {$totalShiftsProcessed}.");
        return Command::SUCCESS;
    }
}
