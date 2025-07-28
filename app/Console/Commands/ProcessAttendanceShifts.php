<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\AttendanceProcessor;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
                            {date? : The specific date to process (YYYY-MM-DD). Shifts are recorded under this date.}
                            {--month= : Process shifts for a whole month (YYYY-MM). If not provided, defaults to the previous month.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processes attendance, associating shifts with the given date (as start or end for night shifts). Can process a single day or a whole month. Now uses dedicated helper services.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $specificDate = $this->argument('date');
        $monthOption = $this->option('month');

        if ($specificDate) {
            $this->processSingleDay(Carbon::parse($specificDate)->startOfDay());
        } elseif ($monthOption) {
            $this->processMonth($monthOption);
        } else {
            // Default to processing yesterday if no date or month is specified,
            // as today's shifts might still be incomplete.
            $this->processSingleDay(Carbon::today()->subDay()->startOfDay());
        }

        return Command::SUCCESS;
    }

    /**
     * Processes shifts for a single specified date.
     *
     * @param Carbon $targetDate The date to process. Shifts are recorded under this date.
     */
    private function processSingleDay(Carbon $targetDate)
    {
        $this->info("Processing attendance shifts to be recorded under date: {$targetDate->toDateString()}");
        $totalShiftsProcessed = 0;

        // Initialize AttendanceProcessor with the command's logging methods
        $attendanceProcessor = new AttendanceProcessor(function ($level, $message) {
            if ($level === 'info') $this->info($message);
            elseif ($level === 'warn') $this->warn($message);
            elseif ($level === 'error') $this->error($message);
        });

        DB::transaction(function () use ($targetDate, $attendanceProcessor, &$totalShiftsProcessed) {
            $previousDay = $targetDate->copy()->subDay();
            $nextDay = $targetDate->copy()->addDay();

            // Fetch activity over a 3-day window to capture all cross-day shifts
            $activityStartDate = $previousDay->copy()->startOfDay(); // Start of previous day
            $activityEndDate = $nextDay->copy()->endOfDay(); // End of next day

            // Eager load distinct employee PINs from attendance records within the activity window
            $potentialPins = Attendance::whereBetween('datetime', [$activityStartDate, $activityEndDate])
                ->distinct()
                ->pluck('pin')
                ->map(function ($pin) {
                    // Normalize pin: remove leading '1' (clock-in) or '2' (clock-out) if present
                    return preg_replace('/^[12]/', '', $pin);
                })
                ->filter(fn($pin) => !empty($pin)) // Filter out any empty pins after normalization
                ->unique()
                ->values(); // Reset keys

            if ($potentialPins->isEmpty()) {
                $this->info("No employee activity found around {$targetDate->toDateString()}.");
                return;
            }

            // Fetch all employee records for the identified pins in a single query
            $employees = Employee::whereIn('pin', $potentialPins)->get();

            if ($employees->isEmpty()) {
                $this->info("No employees found for active pins.");
                return;
            }

            // Chunk employees to manage memory for very large datasets
            $employees->chunk(50)->each(function ($employeeChunk) use ($targetDate, $previousDay, $nextDay, $attendanceProcessor, &$totalShiftsProcessed) {
                foreach ($employeeChunk as $employee) {
                    $processedForEmployee = $attendanceProcessor->processEmployeeShifts($employee, $targetDate, $previousDay, $nextDay);
                    $totalShiftsProcessed += $processedForEmployee;
                }
            });
        });

        $this->info("Finished processing for {$targetDate->toDateString()}. Total shifts processed: {$totalShiftsProcessed}");
    }

    /**
     * Processes shifts for a given month up to yesterday.
     *
     * @param string $monthString The month string in YYYY-MM format.
     */
    private function processMonth(string $monthString)
    {
        try {
            $month = Carbon::parse($monthString)->startOfMonth();
        } catch (\Exception $e) {
            $this->error("Invalid month format. Please use YYYY-MM (e.g., 2025-04).");
            return;
        }

        $today = Carbon::today()->startOfDay();
        $startDate = $month->copy();
        $endDate = $month->copy()->endOfMonth();

        // If the month is the current month, end processing at yesterday.
        // This prevents processing incomplete shifts for the current day.
        if ($month->isSameMonth($today)) {
            $endDate = $today->copy()->subDay()->startOfDay();
        }

        // Ensure we don't try to process future dates or if the month is entirely in the future
        if ($startDate->greaterThan($endDate)) {
            $this->info("No dates to process for the month {$monthString} up to yesterday.");
            return;
        }

        $this->info("Processing shifts for month {$monthString} from {$startDate->toDateString()} to {$endDate->toDateString()}.");

        $currentProcessingDate = $startDate->copy();
        while ($currentProcessingDate->lessThanOrEqualTo($endDate)) {
            $this->processSingleDay($currentProcessingDate->copy());
            $currentProcessingDate->addDay();
        }

        $this->info("Finished processing for the month {$monthString}.");
    }
}