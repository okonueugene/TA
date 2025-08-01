<?php

namespace App\Console\Commands;

use App\Models\Attendance; // Still used for potentialPins identification
use App\Models\Employee;
use App\Services\AttendanceProcessor;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache; // Import Cache facade for locking

class ProcessAttendanceShifts extends Command
{
//     This module acts as the Orchestrator and User Interface for the attendance processing logic.

// Handles:

// Argument Parsing: Reads command-line arguments (e.g., date, --month).

// Date Range Determination: Determines the specific date(s) or month to process based on user input.

// High-Level Flow Control: Manages the overall execution flow (single day vs. month processing).

// Employee Identification: Queries the database to find unique employee PINs that have attendance activity within the relevant date window.

// Employee Batching/Chunking: Manages iterating through employees, optionally in chunks, to optimize memory usage.

// Transaction Management: Wraps the entire processing of a day's shifts in a database transaction to ensure atomicity (all or nothing).

// Logging Interface: Provides the console output ($this->info, $this->warn, $this->error) and passes this logging capability down to the AttendanceProcessor service.

// Error Handling (Top-Level): Catches and reports invalid month formats.

// Does NOT Handle:

// Detailed shift calculation.

// Specific punch filtering logic.

// Database interactions for individual shift records (delegates to AttendanceProcessor).

// Business logic for shift types or overtime.
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:shifts
                            {date? : The specific date to process (YYYY-MM-DD).}
                            {--month= : Process shifts for a whole month (YYYY-MM).}
                            {--employee= : Process a specific employee by PIN.}'; // Added --employee option

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
        $employeePin  = $this->option('employee'); // Get the employee PIN option

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
            // If no specific employee, get all active employees (or filtered by attendance activity in range)
            // To be truly robust, you'd fetch pins with activity in the extended range first.
            $activityStartDate = $startDate->copy()->subDays(2)->startOfDay();
            $activityEndDate   = $endDate->copy()->addDays(2)->endOfDay();
            $potentialPins = Attendance::whereBetween('datetime', [$activityStartDate, $activityEndDate])
                ->distinct()
                ->pluck('pin')
                ->map(fn($pin) => preg_replace('/^[12]/', '', $pin))
                ->filter(fn($pin) => !empty($pin))
                ->unique()
                ->values();

            if ($potentialPins->isEmpty()) {
                $this->info("No employee activity found in the specified range. Nothing to process.");
                return Command::SUCCESS;
            }
            $employees = Employee::whereIn('pin', $potentialPins)->get();
            $this->info("Found {$employees->count()} employees with activity in the range.");
        }

        if ($employees->isEmpty()) {
            $this->info('No employees found to process.');
            return Command::SUCCESS;
        }

        // --- 3. Iterate and process shifts for each employee ---
        $totalShiftsProcessed = 0;
        foreach ($employees as $employee) {
            // Create a unique lock key for this employee and processing period
            $lockKey = "attendance_process_lock_{$employee->pin}_{$startDate->toDateString()}_{$endDate->toDateString()}";
            $lock = Cache::lock($lockKey, 300); // Acquire a 5-minute lock

            if ($lock->get()) { // Attempt to acquire the lock
                try {
                    $this->comment("--- Processing shifts for employee: {$employee->name} (PIN: {$employee->pin}) ---");

                    // Initialize AttendanceProcessor for this employee (fresh $usedAttendanceIds)
                    $attendanceProcessor = new AttendanceProcessor(function ($level, $message) {
                        if ($level === 'info') $this->info($message);
                        elseif ($level === 'warn') $this->warn($message);
                        elseif ($level === 'error') $this->error($message);
                    });

                    // *** CORE CALL TO THE BATCH PROCESSING METHOD ***
                    $shiftsProcessedForEmployee = $attendanceProcessor->processEmployeeShiftsForDateRange(
                        $employee,
                        $startDate, // Start of the overall processing range
                        $endDate    // End of the overall processing range
                    );
                    $totalShiftsProcessed += $shiftsProcessedForEmployee;

                    $this->info("Completed processing for {$employee->name}. Total shifts recorded: {$shiftsProcessedForEmployee}.");

                } catch (\Exception $e) {
                    $this->error("Error processing shifts for employee {$employee->name} (PIN: {$employee->pin}): " . $e->getMessage());
                    // Log the full exception for debugging
                    Log::error("Attendance Processing Error for PIN {$employee->pin}: " . $e->getMessage(), ['exception' => $e, 'employee_pin' => $employee->pin, 'start_date' => $startDate->toDateString(), 'end_date' => $endDate->toDateString()]);
                } finally {
                    $lock->release(); // Always release the lock
                }
            } else {
                $this->warn("Skipping employee {$employee->name} (PIN: {$employee->pin}): another process is already running for this employee and period.");
            }
        }

        $this->info("Attendance shifts processing completed. Total shifts processed in this run: {$totalShiftsProcessed}.");
        return Command::SUCCESS;
    }

    // --- Removed processSingleDay() and processMonth() methods ---
    // Their logic is now incorporated directly into handle() or delegated to AttendanceProcessor.
}