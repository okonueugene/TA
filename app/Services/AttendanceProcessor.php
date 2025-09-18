<?php
namespace App\Services;

use App\Helpers\AttendanceHelper;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Utilities\ShiftAnomalyDetector;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

// For logging inside the service

class AttendanceProcessor
{
    private $logger;                  // To allow logging via the calling command's info/warn methods
    private $employeePunchCache = []; // Cache for employee punches across multiple days

//     This module is the Core Business Logic Processor for a single employee's attendance. It orchestrates the detailed steps of identifying and recording shifts.

// Handles:

// Employee-Specific Shift Processing: Manages the entire process for one Employee and a targetDate.

// Idempotence: Ensures that re-running the process for a specific employee and date range doesn't create duplicate shifts by deleting existing records first.

// Punch Aggregation: Fetches all relevant attendance punches for a given employee within a necessary date window.

// Punch De-duplication: Leverages AttendanceHelper to pre-filter raw punches to remove duplicates.

// Shift Detection Algorithms: Contains the sequential logic for:

// Detecting night shifts ending on the target date.

// Detecting shifts starting on the target date (day or night).

// Handling common human error patterns (IN-IN, OUT-OUT) to infer shifts.

// Processing isolated punches (missing clock-in/out).

// Shift Calculation Orchestration: Calls AttendanceHelper for calculations (lateness, overtime, shift type) and ShiftAnomalyDetector for anomaly flagging.

// Shift Record Creation: Centralizes the actual database insertion of EmployeeShift records.

// State Management (for used punches): Manages the usedAttendanceIds collection to prevent the same raw punch from being used in multiple calculated shifts for an employee.

// Does NOT Handle:

// Direct command-line interaction.

// Date range iteration for a month (delegates to the Command).

// Low-level utility calculations (delegates to AttendanceHelper).

// Specific anomaly detection rules (delegates to ShiftAnomalyDetector).

    public function __construct( ? callable $logger = null)
    {
        // Default logger if none is provided (e.g., using Laravel's default Log facade)
        $this->logger = $logger ?? function ($level, $message) {
            if ($level === 'info') {
                Log::info($message);
            } elseif ($level === 'warn') {
                Log::warning($message);
            } elseif ($level === 'error') {
                Log::error($message);
            }
        };
    }

    /**
     * Main method to process an employee's shifts for a specific target date.
     *
     * @param Employee $employee
     * @param Carbon $targetDate The date under which shifts will be recorded (e.g., day shift start, or prev day for night shift).
     * @param Carbon $previousDay The day before the target date.
     * @param Carbon $nextDay The day after the target date.
     * @return int The number of shifts processed for this employee.
     */
    /**
     * Process an employee's shifts for an entire date range (optimized for monthly processing)
     * This method handles the batch operations and delegates daily processing.
     */
    public function processEmployeeShiftsForDateRange(Employee $employee, Carbon $startDate, Carbon $endDate) : int
    {
        $totalShiftsProcessed = 0;
        $employeePin          = $employee->pin;

        // Pre-fetch all punches for the entire date range + buffer days
        $bufferStart = $startDate->copy()->subDay();
        $bufferEnd   = $endDate->copy()->addDay();

        $allEmployeePunches = $this->fetchEmployeePunches($employeePin, $bufferStart, $bufferEnd);
        $filteredPunches    = AttendanceHelper::filterDuplicatePunches($allEmployeePunches, $employeePin, $this->logger);

        // Cache the punches for this employee
        $this->employeePunchCache[$employeePin] = $filteredPunches;

        // Pre-delete all existing shifts in the date range for idempotence
        $this->performBatchDeletion($employeePin, $startDate, $endDate);

        // Track used attendance IDs across the entire processing period
        $globalUsedAttendanceIds = new Collection();

        // Process each day in the range
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $shiftsForDay = $this->processEmployeeShiftsForSingleDay(
                $employee,
                $currentDate->copy(),
                $globalUsedAttendanceIds
            );

            $totalShiftsProcessed += $shiftsForDay;
            $currentDate->addDay();
        }

        // Clear cache after processing
        unset($this->employeePunchCache[$employeePin]);

        return $totalShiftsProcessed;
    }

    /**
     * Legacy method maintained for backward compatibility (single day processing)
     * Now uses the optimized internal method.
     */
    public function processEmployeeShifts(Employee $employee, Carbon $targetDate, Carbon $previousDay, Carbon $nextDay): int
    {
        // For single-day processing, we'll use a simplified approach
        $employeePin       = $employee->pin;
        $usedAttendanceIds = new Collection();

        // Enhanced deletion for single-day processing
        $this->performEnhancedSingleDayDeletion($employeePin, $targetDate, $previousDay);

        // Fetch punches for the 3-day window
        $allEmployeePunches = $this->fetchEmployeePunches($employeePin, $previousDay, $nextDay);
        $filteredPunches    = AttendanceHelper::filterDuplicatePunches($allEmployeePunches, $employeePin, $this->logger);

        return $this->processSingleDayShifts($employee, $targetDate, $previousDay, $nextDay, $filteredPunches, $usedAttendanceIds);
    }

    /**
     * Optimized single-day processing that uses cached punches when available
     */
    private function processEmployeeShiftsForSingleDay(Employee $employee, Carbon $targetDate, Collection &$globalUsedAttendanceIds): int
    {
        $employeePin = $employee->pin;
        $previousDay = $targetDate->copy()->subDay();
        $nextDay     = $targetDate->copy()->addDay();

        // Use cached punches if available, otherwise fetch them
        $filteredPunches = $this->employeePunchCache[$employeePin] ??
        AttendanceHelper::filterDuplicatePunches(
            $this->fetchEmployeePunches($employeePin, $previousDay, $nextDay),
            $employeePin,
            $this->logger
        );

        return $this->processSingleDayShifts($employee, $targetDate, $previousDay, $nextDay, $filteredPunches, $globalUsedAttendanceIds);
    }

    /**
     * Core single-day shift processing logic
     */
    private function processSingleDayShifts(Employee $employee, Carbon $targetDate, Carbon $previousDay, Carbon $nextDay, Collection $filteredPunches, Collection &$usedAttendanceIds): int
    {
        $shiftsProcessedCount = 0;

        // Process night shifts ending on target day
        $shiftsProcessedCount += $this->processNightShiftEndingOnTargetDay($employee, $targetDate, $previousDay, $filteredPunches, $usedAttendanceIds);

        // Process shifts starting on target day
        $shiftsProcessedCount += $this->processShiftsStartingOnTargetDay($employee, $targetDate, $nextDay, $filteredPunches, $usedAttendanceIds);

        // Process isolated punches
        $shiftsProcessedCount += $this->processIsolatedPunchesOnTargetDate($employee, $targetDate, $filteredPunches, $usedAttendanceIds);

        return $shiftsProcessedCount;
    }

    /**
     * Enhanced deletion for single-day processing to handle cross-day shifts
     */
    private function performEnhancedSingleDayDeletion(string $employeePin, Carbon $targetDate, Carbon $previousDay): void
    {
        $shiftsToDelete = EmployeeShift::where('employee_pin', $employeePin)
            ->where(function ($query) use ($targetDate, $previousDay) {
                // Delete shifts recorded on targetDate
                $query->whereDate('shift_date', $targetDate->toDateString())
                // Delete night shifts from previous day that end on targetDate
                    ->orWhere(function ($subQuery) use ($previousDay, $targetDate) {
                        $subQuery->whereDate('shift_date', $previousDay->toDateString())
                            ->where('shift_type', 'night')
                            ->whereDate('clock_out_time', $targetDate->toDateString());
                    });
            });

        $deletedCount = $shiftsToDelete->count();
        if ($deletedCount > 0) {
            $shiftsToDelete->delete();
            ($this->logger)('info', "Deleted {$deletedCount} existing shifts for employee {$employeePin} on {$targetDate->toDateString()} for idempotence.");
        }
    }

    /**
     * Batch deletion for date range processing
     */
    private function performBatchDeletion(string $employeePin, Carbon $startDate, Carbon $endDate): void
    {
        $bufferStart = $startDate->copy()->subDay(); // Include previous day for night shifts

        $deletedCount = EmployeeShift::where('employee_pin', $employeePin)
            ->whereBetween('shift_date', [$bufferStart->toDateString(), $endDate->toDateString()])
            ->count();

        if ($deletedCount > 0) {
            EmployeeShift::where('employee_pin', $employeePin)
                ->whereBetween('shift_date', [$bufferStart->toDateString(), $endDate->toDateString()])
                ->delete();

            ($this->logger)('info', "Batch deleted {$deletedCount} existing shifts for employee {$employeePin} from {$startDate->toDateString()} to {$endDate->toDateString()}.");
        }
    }

    /**
     * Centralized punch fetching with proper caching
     */
    private function fetchEmployeePunches(string $employeePin, Carbon $startDate, Carbon $endDate): Collection
    {
        return Attendance::where(function ($q) use ($employeePin) {
            $q->where('pin', '1' . $employeePin)
                ->orWhere('pin', '2' . $employeePin);
        })
            ->whereBetween('datetime', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->orderBy('datetime')
            ->get()
            ->map(function ($record) {
                $record->datetime = Carbon::parse($record->datetime);
                return $record;
            });
    }

    /**
     * Processes night shifts that started on the previous day and ended on the target date.
     *
     * @param Employee $employee
     * @param Carbon $targetEndDate The date on which the night shift is expected to end.
     * @param Carbon $previousDay The day before the target date.
     * @param Collection $filteredPunches The pre-filtered collection of all relevant employee punches.
     * @param Collection $usedAttendanceIds Reference to the collection of used attendance IDs.
     * @return int Number of shifts processed.
     */
    private function processNightShiftEndingOnTargetDay(Employee $employee, Carbon $targetEndDate, Carbon $previousDay, Collection $filteredPunches, Collection &$usedAttendanceIds): int
    {
        $employeePin = $employee->pin;
        $shiftsCount = 0;

        // Get clock-ins from the previous day that are potential night shift starts (e.g., after 5 PM)
        $prevDayClockIns = AttendanceHelper::getPunches($filteredPunches, $previousDay, 'in', $usedAttendanceIds)
            ->filter(fn($ci) => $ci->datetime->hour >= AttendanceHelper::PREV_DAY_NIGHT_IN_AFTER_HOUR)
            ->sortBy('datetime');

        if ($prevDayClockIns->isEmpty()) {
            return 0; // No potential night shift starts from previous day
        }

        $firstPrevDayNightIn = $prevDayClockIns->first();

        // Get potential clock-outs on the target date that are early morning and occur after the clock-in
        $potentialTargetDayEarlyClockOuts = AttendanceHelper::getPunches($filteredPunches, $targetEndDate, 'out', $usedAttendanceIds)
            ->filter(fn($co) => $co->datetime->hour < AttendanceHelper::TARGET_DAY_NIGHT_OUT_BEFORE_HOUR && $co->datetime->greaterThan($firstPrevDayNightIn->datetime))
            ->sortBy('datetime');

        $matchingTargetDayNightOut = $potentialTargetDayEarlyClockOuts->first();

        if ($firstPrevDayNightIn && $matchingTargetDayNightOut) {
            $shiftsCount += $this->processCompleteShiftWrapper($employee, $previousDay, $firstPrevDayNightIn, $matchingTargetDayNightOut, $filteredPunches, true);

            // Mark all attendance IDs associated with this shift as used
            AttendanceHelper::markPunchesAsUsed($filteredPunches, $firstPrevDayNightIn->datetime, $matchingTargetDayNightOut->datetime, $usedAttendanceIds);
        }
        return $shiftsCount;
    }

    /**
     * Processes shifts that start on the target date (can be day or night shifts).
     * Includes logic for common human error scenarios (IN-IN, OUT-OUT).
     *
     * @param Employee $employee
     * @param Carbon $targetDate The date shifts are starting on.
     * @param Carbon $nextDay The day after the target date (for cross-day shifts).
     * @param Collection $filteredPunches The pre-filtered collection of all relevant employee punches.
     * @param Collection $usedAttendanceIds Reference to the collection of used attendance IDs.
     * @return int Number of shifts processed.
     */
    private function processShiftsStartingOnTargetDay(Employee $employee, Carbon $targetDate, Carbon $nextDay, Collection $filteredPunches, Collection &$usedAttendanceIds): int
    {
        $employeePin          = $employee->pin;
        $shiftsCount          = 0;
        $firstTargetDayIn     = null;
        $lastMatchingOut      = null;
        $isHumanErrorOverride = false; // Flag for shifts derived from human error patterns

        // --- Attempt 1: Find a standard IN-OUT pair starting on Target Date ---
        $potentialFirstIn = AttendanceHelper::getPunches($filteredPunches, $targetDate, 'in', $usedAttendanceIds)
            ->sortBy('datetime')
            ->first(); // Get the earliest unused IN punch on the target day

        if ($potentialFirstIn) {
            // Look for a potential same-day OUT (after the clock-in)
            $potentialLastOutsSameDay = AttendanceHelper::getPunches($filteredPunches, $targetDate, 'out', $usedAttendanceIds)
                ->filter(fn($co) => $co->datetime->greaterThan($potentialFirstIn->datetime))
                ->sortBy('datetime');
            $lastMatchingOut = $potentialLastOutsSameDay->first(); // Earliest valid OUT on same day

            if (! $lastMatchingOut) {
                // If no same-day OUT, look for a next-day clock-out (for night shifts, typically early morning)
                $potentialNextDayOuts = AttendanceHelper::getPunches($filteredPunches, $nextDay, 'out', $usedAttendanceIds)
                    ->filter(fn($co) => $co->datetime->hour < AttendanceHelper::NEXT_DAY_CLOCKOUT_LOOKAHEAD_HOUR && $co->datetime->greaterThan($potentialFirstIn->datetime))
                    ->sortBy('datetime');
                $lastMatchingOut = $potentialNextDayOuts->first(); // Earliest valid OUT on next day
            }

            if ($lastMatchingOut) {
                $firstTargetDayIn = $potentialFirstIn;
            }
        }

        // --- Attempt 2: Human Error - IN-IN (if standard IN-OUT failed) ---
        // If we found a clock-in but no matching clock-out, check for an IN-IN pattern.
        // Treat the second IN as an effective OUT.
        if (! $firstTargetDayIn && $potentialFirstIn) {
            // Find the very next 'in' punch after the first 'in' on the target day
            $humanErrorClockOutCandidate = AttendanceHelper::getPunches($filteredPunches, $targetDate, 'in', $usedAttendanceIds)
                ->filter(fn($punch) => $punch->datetime->greaterThan($potentialFirstIn->datetime))
                ->sortBy('datetime')
                ->first();

            if ($humanErrorClockOutCandidate) {
                $firstTargetDayIn     = $potentialFirstIn;
                $lastMatchingOut      = $humanErrorClockOutCandidate;
                $isHumanErrorOverride = true;
                ($this->logger)('warn', "Detected IN-IN human error for {$employeePin} on {$targetDate->toDateString()}. Treating second IN as OUT.");
            }
        }

        // --- Attempt 3: Human Error - OUT-OUT (if previous attempts failed) ---
        // If still no shift found, check if the first unused punch is an OUT, followed by another OUT.
        // Treat the first OUT as an effective IN.
        if (! $firstTargetDayIn) {
            // Get the first *unused* punch on the target day regardless of type
            $firstUnusedPunchOnTargetDay = $filteredPunches->filter(function ($punch) use ($targetDate, $usedAttendanceIds) {
                return $punch->datetime->isSameDay($targetDate) && ! $usedAttendanceIds->contains($punch->id);
            })->sortBy('datetime')->first();

            // If the first unused punch is an OUT
            if ($firstUnusedPunchOnTargetDay && str_starts_with($firstUnusedPunchOnTargetDay->pin, '2')) {
                // Find the next available OUT punch on the same day after the first one
                $potentialSubsequentOuts = AttendanceHelper::getPunches($filteredPunches, $targetDate, 'out', $usedAttendanceIds)
                    ->filter(fn($punch) => $punch->datetime->greaterThan($firstUnusedPunchOnTargetDay->datetime))
                    ->sortBy('datetime');
                $subsequentOutCandidate = $potentialSubsequentOuts->first();

                if ($subsequentOutCandidate) {
                    $firstTargetDayIn     = $firstUnusedPunchOnTargetDay; // Treat the first OUT as an IN
                    $lastMatchingOut      = $subsequentOutCandidate;
                    $isHumanErrorOverride = true;
                    ($this->logger)('warn', "Detected OUT-OUT human error for {$employeePin} on {$targetDate->toDateString()}. Treating first OUT as IN.");
                }
            }
        }

        // --- Process the found shift (standard or human error) ---
        if ($firstTargetDayIn && $lastMatchingOut) {
            // Determine if it's a night shift (clock-in and clock-out are on different calendar days)
            $isNightShift = ! $lastMatchingOut->datetime->isSameDay($firstTargetDayIn->datetime);

            $shiftsCount += $this->processCompleteShiftWrapper(
                $employee,
                $targetDate, // Shift recorded on targetDate
                $firstTargetDayIn,
                $lastMatchingOut,
                $filteredPunches,
                $isNightShift,
                $isHumanErrorOverride
            );

            // Mark all attendance IDs associated with this shift as used
            AttendanceHelper::markPunchesAsUsed($filteredPunches, $firstTargetDayIn->datetime, $lastMatchingOut->datetime, $usedAttendanceIds);
        }
        return $shiftsCount;
    }

    /**
     * Processes any remaining isolated punches on the target date that haven't been part of a complete shift.
     * These typically indicate missing corresponding punches.
     *
     * @param Employee $employee
     * @param Carbon $targetDate The date to process isolated punches for.
     * @param Collection $filteredPunches The pre-filtered collection of all relevant employee punches.
     * @param Collection $usedAttendanceIds Reference to the collection of used attendance IDs.
     * @return int Number of shifts processed.
     */
    private function processIsolatedPunchesOnTargetDate(Employee $employee, Carbon $targetDate, Collection $filteredPunches, Collection &$usedAttendanceIds): int
    {
        $employeePin = $employee->pin;
        $shiftsCount = 0;
        $isWeekend   = $targetDate->isWeekend();
        $isHoliday   = AttendanceHelper::isHoliday($targetDate);

        // Process isolated clock-ins: find the earliest unused IN on the target date.
        $isolatedTargetDayIn = AttendanceHelper::getPunches($filteredPunches, $targetDate, 'in', $usedAttendanceIds)
            ->sortBy('datetime')
            ->first();

        if ($isolatedTargetDayIn) {
            // A common "human error" is clocking in then out immediately.
            // Check if there's an immediate clock-out after this isolated IN
            $immediateOut = AttendanceHelper::getPunches($filteredPunches, $targetDate, 'out', $usedAttendanceIds)
                ->filter(fn($p) => $p->datetime->greaterThanOrEqualTo($isolatedTargetDayIn->datetime) && $p->datetime->diffInMinutes($isolatedTargetDayIn->datetime) <= ShiftAnomalyDetector::ACCIDENTAL_DOUBLE_PUNCH_MINUTES)
                ->sortBy('datetime')
                ->first();

            if ($immediateOut) {
                // If there's an immediate out, this IN should be processed as part of a complete (but very short/anomalous) shift
                $shiftsCount += $this->processCompleteShiftWrapper(
                    $employee, $targetDate, $isolatedTargetDayIn, $immediateOut, $filteredPunches, false, true, // Not night shift, IS human error override
                    "Accidental double punch: IN and OUT within " . ShiftAnomalyDetector::ACCIDENTAL_DOUBLE_PUNCH_MINUTES . " minutes."
                );
                AttendanceHelper::markPunchesAsUsed($filteredPunches, $isolatedTargetDayIn->datetime, $immediateOut->datetime, $usedAttendanceIds);
            } else {
                // If no immediate out, it's a true missing clock-out scenario
                $shiftsCount += $this->processMissingClockOut($employeePin, $targetDate, $isolatedTargetDayIn, $isWeekend, $isHoliday);
                $usedAttendanceIds->push($isolatedTargetDayIn->id); // Mark this specific punch as used
                $usedAttendanceIds = $usedAttendanceIds->unique()->values();
            }
        }

        // Process isolated clock-outs: find the latest unused OUT on the target date.
        // We look for the latest because if there are multiple unused OUTs, the last one is most likely
        // the intended final punch for a shift whose IN was missed.
        $isolatedTargetDayOut = AttendanceHelper::getPunches($filteredPunches, $targetDate, 'out', $usedAttendanceIds)
            ->sortByDesc('datetime') // We want the latest one if there are multiple
            ->first();

        if ($isolatedTargetDayOut) {
            // Before treating as missing clock-in, check if this specific OUT was already associated
            // with a *complete* shift (e.g., if it was the OUT of a night shift from previous day,
            // but the IN was previously marked as used by mistake or other logic).
            $isAlreadyUsedAsClockOutInCompleteShift = EmployeeShift::where('clock_out_attendance_id', $isolatedTargetDayOut->id)
                ->where('is_complete', true)
                ->exists();

            if ($isAlreadyUsedAsClockOutInCompleteShift) {
                ($this->logger)('info', "Ignoring isolated clock-out [ID:{$isolatedTargetDayOut->id}] for {$employeePin} as it is already the end of a completed shift.");
                $usedAttendanceIds->push($isolatedTargetDayOut->id);
                $usedAttendanceIds = $usedAttendanceIds->unique()->values();
                return $shiftsCount; // Already used, no new shift counted
            }

            $shiftsCount += $this->processMissingClockIn($employeePin, $targetDate, $isolatedTargetDayOut, $isWeekend, $isHoliday);
            $usedAttendanceIds->push($isolatedTargetDayOut->id); // Mark this specific punch as used
            $usedAttendanceIds = $usedAttendanceIds->unique()->values();
        }
        return $shiftsCount;
    }

    /**
     * Wrapper for processing a complete shift, preparing common data for the main processing method.
     *
     * @param Employee $employee
     * @param Carbon $shiftRecordDate The date under which the shift should be recorded in the database (e.g., day shift start, or previous day for night shift).
     * @param object $clockInRecord The attendance record for clock-in.
     * @param object $clockOutRecord The attendance record for clock-out.
     * @param Collection $allEmployeePunches All employee punches (pre-filtered) for error detection.
     * @param bool $isPrevDayNightShift True if this is a night shift that started on the previous day.
     * @param bool $isHumanErrorOverride True if the shift was identified via human error patterns (IN-IN, OUT-OUT).
     * @param string $anomalyNote Optional anomaly note to prepend to the shift notes.
     * @return int Number of shifts processed (0 or 1).
     */
    private function processCompleteShiftWrapper(
        Employee $employee,
        Carbon $shiftRecordDate,
        $clockInRecord,
        $clockOutRecord,
        Collection $allEmployeePunches,
        bool $isPrevDayNightShift,
        bool $isHumanErrorOverride = false,
        string $anomalyNote = ''
    ): int {
        // The actual calendar day the shift began on (from clock-in time)
        $shiftActualStartDate = $clockInRecord->datetime->copy()->startOfDay();

        $isWeekendBasedOnActualStart         = $shiftActualStartDate->isWeekend();
        $isHolidayBasedOnActualStart         = AttendanceHelper::isHoliday($shiftActualStartDate);
        $isSaturdayBasedOnActualStart        = $shiftActualStartDate->isSaturday();
        $isSundayOrHolidayBasedOnActualStart = $shiftActualStartDate->isSunday() || $isHolidayBasedOnActualStart;

        // Get all punches on the actual clock-in day for human error detection, unfiltered by usedAttendanceIds
        $clockInsOnShiftInDayForErrorCheck   = $allEmployeePunches->filter(fn($p) => str_starts_with($p->pin, '1') && $p->datetime->isSameDay($clockInRecord->datetime));
        $clockOutsOnShiftOutDayForErrorCheck = $allEmployeePunches->filter(fn($p) => str_starts_with($p->pin, '2') && $p->datetime->isSameDay($clockOutRecord->datetime));

        return $this->processCompleteShift(
            $employee,
            $shiftRecordDate,
            $clockInRecord,
            $clockOutRecord,
            $clockInsOnShiftInDayForErrorCheck,
            $clockOutsOnShiftOutDayForErrorCheck,
            $isWeekendBasedOnActualStart,
            $isHolidayBasedOnActualStart,
            $isSaturdayBasedOnActualStart,
            $isSundayOrHolidayBasedOnActualStart,
            $isPrevDayNightShift,
            $shiftActualStartDate,
            $isHumanErrorOverride,
            $anomalyNote
        );
    }

    /**
     * Processes and creates a complete shift record. This is the main logic for calculating hours,
     * lateness, and overtime for a recognized IN-OUT pair.
     *
     * @param string $employeePin
     * @param Carbon $shiftRecordDate The date under which the shift is recorded in the DB (e.g., '2025-07-01').
     * @param object $firstClockIn The attendance record for the clock-in.
     * @param object $lastClockOut The attendance record for the clock-out.
     * @param Collection $relevantClockInsForError All clock-ins on the day the shift's clock-in occurred (for detecting internal errors).
     * @param Collection $relevantClockOutsForError All clock-outs on the day the shift's clock-out occurred (for detecting internal errors).
     * @param bool $isWeekendBasedOnActualStart Is the actual shift start date a weekend?
     * @param bool $isHolidayBasedOnActualStart Is the actual shift start date a holiday?
     * @param bool $isSaturdayBasedOnActualStart Is the actual shift start date a Saturday?
     * @param bool $isSundayOrHolidayBasedOnActualStart Is the actual shift start date a Sunday or holiday?
     * @param bool $isPrevDayNightShift True if it's a night shift that started on the previous calendar day.
     * @param Carbon $shiftActualStartDate The actual calendar day the shift started (from clock-in time).
     * @param bool $isHumanErrorOverride Flag if the shift was inferred from human error patterns (e.g., IN-IN, OUT-OUT).
     * @param string $anomalyNote Optional anomaly note to prepend.
     * @return int Number of shifts processed (0 or 1).
     */
    private function processCompleteShift(
        $employee,
        Carbon $shiftRecordDate,
        $firstClockIn,
        $lastClockOut,
        Collection $relevantClockInsForError,
        Collection $relevantClockOutsForError,
        $isWeekendBasedOnActualStart,
        $isHolidayBasedOnActualStart,
        $isSaturdayBasedOnActualStart,
        $isSundayOrHolidayBasedOnActualStart,
        bool $isPrevDayNightShift,
        Carbon $shiftActualStartDate,
        bool $isHumanErrorOverride = false,
        string $anomalyNote = ''
    ): int {
        $employeePin  = $employee->pin;
        $clockInTime  = $firstClockIn->datetime;
        $clockOutTime = $lastClockOut->datetime;
        $shiftsCount  = 0;

        $shiftDurationMinutes = $clockOutTime->diffInMinutes($clockInTime);

        // --- Anomaly Checks (order matters) ---
        if ($clockOutTime->lessThanOrEqualTo($clockInTime)) {
            $notes = "Inverted punch times. In:{$clockInTime->toDateTimeString()}, Out:{$clockOutTime->toDateTimeString()}";
            $this->createShiftRecord(
                $employeePin, $shiftRecordDate, $firstClockIn->id, $lastClockOut->id, $clockInTime, $clockOutTime,
                0, 'inverted_times', false, $notes, 0, 0.0, 0.0, AttendanceHelper::isHoliday($shiftRecordDate), $shiftRecordDate->isWeekend(), 0
            );
            ($this->logger)('warn', "Processed inverted times for {$employeePin}, shift recorded on {$shiftRecordDate->toDateString()}.");
            return 1;
        }

        if ($isHumanErrorOverride && ShiftAnomalyDetector::isAccidentalDoublePunch($shiftDurationMinutes)) {
            $anomalyNote = "Human error punches (e.g., IN-IN) occurred within " . ShiftAnomalyDetector::ACCIDENTAL_DOUBLE_PUNCH_MINUTES . " minutes. Treating as an accidental double-punch.";
            $notes       = AttendanceHelper::generateNotes($clockInTime, $clockOutTime, 'double_punch_anomaly', 0, false, 0, 0, 0, true, $anomalyNote);
            $this->createShiftRecord(
                $employeePin, $shiftRecordDate, $firstClockIn->id, $lastClockOut->id, $clockInTime, $clockOutTime,
                0, 'double_punch_anomaly', false, $notes, 0, 0.0, 0.0, AttendanceHelper::isHoliday($shiftRecordDate), $shiftRecordDate->isWeekend(), 0
            );
            ($this->logger)('warn', "PIN{$employeePin}: " . $anomalyNote);
            return 1;
        }

        if (! $isHumanErrorOverride && ShiftAnomalyDetector::isTooShortShift($shiftDurationMinutes)) {
            $anomalyNote = "Shift duration ({$shiftDurationMinutes} min) is less than the minimum required (" . ShiftAnomalyDetector::MINIMUM_SHIFT_DURATION_MINUTES . " min). Discarding as an anomaly.";
            $notes       = AttendanceHelper::generateNotes($clockInTime, $clockOutTime, 'short_shift_anomaly', 0, false, 0, 0, 0, false, $anomalyNote);
            $this->createShiftRecord(
                $employeePin, $shiftRecordDate, $firstClockIn->id, $lastClockOut->id, $clockInTime, $clockOutTime,
                0, 'short_shift_anomaly', false, $notes, 0, 0.0, 0.0, AttendanceHelper::isHoliday($shiftRecordDate), $shiftRecordDate->isWeekend(), 0
            );
            ($this->logger)('warn', "PIN{$employeePin}: " . $anomalyNote);
            return 1;
        }

        // Standard shift processing
        $hasHumanError = ShiftAnomalyDetector::detectHumanError($relevantClockInsForError, $relevantClockOutsForError);
        $hoursWorked   = $clockOutTime->floatDiffInHours($clockInTime); // Accurate float difference

        // --- Modified Shift Type Determination Logic for Blowmolding Employees ---
        if ($employee->is_blowmolding) {
            // For blowmolding employees, always determine shift type as day or night,
            // as overtime is now based on the total shifts in the week, not on weekend/holiday start.
            $shiftTypeDetermined = AttendanceHelper::determineShiftType($clockInTime, $clockOutTime, $shiftActualStartDate);
        } elseif ($isWeekendBasedOnActualStart || $isHolidayBasedOnActualStart) {
            // For non-blowmolding employees, keep the original logic.
            $shiftTypeDetermined = 'overtime_shift';
        } else {
            $shiftTypeDetermined = AttendanceHelper::determineShiftType($clockInTime, $clockOutTime, $shiftActualStartDate);
        }
        // --- END Modified Shift Type Determination ---

        // --- Enhanced Logic for Blowmolding Shift Patterns (Now for any shift, not just night) ---
        $applyBlowMoldingSundayException = false;
        // The rule is now triggered for any complete shift ending on a Sunday for blowmolding employees.
        if ($employee->is_blowmolding && $clockOutTime->isSunday()) {
                                                                                     // Determine the week (Sunday to Saturday) based on the shift's start date
            $weekStart = $shiftActualStartDate->copy()->startOfWeek(Carbon::SUNDAY); // Week starts on Sunday
            $weekEnd   = $weekStart->copy()->endOfWeek(Carbon::SATURDAY);            // Week ends on Saturday

            // Count *all* completed shifts in the week before the current one.
            // We assume this method is called once per shift, and the current shift is not yet in the DB.
            $completedShiftsInWeek = EmployeeShift::where('employee_pin', $employee->pin)
                ->whereBetween('shift_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->where('is_complete', true)
                ->count();

            // Add the current shift being processed to the count.
            $totalShiftsInWeek = $completedShiftsInWeek + 1;

                                          // If the shift ends on Sunday, check the total number of shifts in the week
            if ($totalShiftsInWeek < 4) { // e.g., 3-shift week
                $applyBlowMoldingSundayException = true;
                         // Logging can be re-added here if desired.
            } else { // e.g., 4 or more shifts
                         // Logging can be re-added here if desired.
            }
        }
        // --- END Enhanced Logic ---

        $latenessMinutes = AttendanceHelper::calculateLateness($clockInTime, $shiftTypeDetermined, $shiftActualStartDate, $isPrevDayNightShift);

        list($overtime1_5x, $overtime2_0x, $regularHours) = AttendanceHelper::calculateOvertimeAndHours(
            $hoursWorked, $clockInTime, $clockOutTime, $shiftActualStartDate, $shiftTypeDetermined,
            $isSaturdayBasedOnActualStart, $isSundayOrHolidayBasedOnActualStart,
            $employee->is_blowmolding,       // MODIFIED: Pass blowmolding status
            $applyBlowMoldingSundayException // MODIFIED: Pass exception flag
        );

        $notes = AttendanceHelper::generateNotes(
            $clockInTime, $clockOutTime, $shiftTypeDetermined, $hoursWorked, $hasHumanError,
            $latenessMinutes, $overtime1_5x, $overtime2_0x, $isHumanErrorOverride, $anomalyNote
        );

        $this->createShiftRecord(
            $employeePin, $shiftRecordDate, $firstClockIn->id, $lastClockOut->id, $clockInTime, $clockOutTime,
            $hoursWorked, $shiftTypeDetermined, true, $notes, $latenessMinutes, $overtime1_5x, $overtime2_0x,
            AttendanceHelper::isHoliday($shiftRecordDate), $shiftRecordDate->isWeekend(), $regularHours
        );

        ($this->logger)('info', "Processed complete shift for {$employeePin}, recorded on {$shiftRecordDate->toDateString()}. Type:{$shiftTypeDetermined}, Hours:" . round($hoursWorked, 2) . ", Regular:" . round($regularHours, 2) . ", OT1.5:" . round($overtime1_5x, 2) . ", OT2.0:" . round($overtime2_0x, 2));
        return 1;
    }

    /**
     * Processes and creates a shift record for a missing clock-out (isolated clock-in).
     *
     * @param string $employeePin
     * @param Carbon $shiftDate The date for the shift record.
     * @param object $clockIn The clock-in attendance record.
     * @param bool $isWeekend Is the shift date a weekend?
     * @param bool $isHoliday Is the shift date a holiday?
     * @return int Number of shifts processed (0 or 1).
     */
    private function processMissingClockOut(string $employeePin, Carbon $shiftDate, $clockIn, bool $isWeekend, bool $isHoliday): int
    {
        // Determine shift type based on weekend/holiday status for incomplete shifts
        $shiftType = 'missing_clockout';
        if ($isHoliday) {
            $shiftType = 'holiday_incomplete';
        } elseif ($isWeekend) {
            $shiftType = 'weekend_incomplete';
        }

        $latenessMinutes = AttendanceHelper::calculateLateness($clockIn->datetime, 'day', $clockIn->datetime->copy()->startOfDay(), false);
        $notes           = AttendanceHelper::generateNotes($clockIn->datetime, null, $shiftType, 0, false, $latenessMinutes, 0, 0, false, "Missing clock-out.");

        $this->createShiftRecord(
            $employeePin, $shiftDate, $clockIn->id, null, $clockIn->datetime, null,
            0, $shiftType, false, $notes, $latenessMinutes, 0.0, 0.0, $isHoliday, $isWeekend, 0
        );

        ($this->logger)('warn', "Missing clock-out for employee {$employeePin} on {$shiftDate->toDateString()}.");
        return 1;
    }

    /**
     * Processes and creates a shift record for a missing clock-in (isolated clock-out).
     *
     * @param string $employeePin
     * @param Carbon $shiftDate The date for the shift record.
     * @param object $clockOut The clock-out attendance record.
     * @param bool $isWeekend Is the shift date a weekend?
     * @param bool $isHoliday Is the shift date a holiday?
     * @return int Number of shifts processed (0 or 1).
     */
    private function processMissingClockIn(string $employeePin, Carbon $shiftDate, $clockOut, bool $isWeekend, bool $isHoliday): int
    {
        // Determine shift type based on weekend/holiday status for incomplete shifts
        $shiftType = 'missing_clockin';
        if ($isHoliday) {
            $shiftType = 'holiday_incomplete';
        } elseif ($isWeekend) {
            $shiftType = 'weekend_incomplete';
        }

        $notes = AttendanceHelper::generateNotes(null, $clockOut->datetime, $shiftType, 0, false, 0, 0, 0, false, "Missing clock-in.");

        $this->createShiftRecord(
            $employeePin, null, null, $clockOut->id, null, $clockOut->datetime,
            0, $shiftType, false, $notes, 0, 0.0, 0.0, $isHoliday, $isWeekend, 0
        );

        ($this->logger)('warn', "Missing clock-in for employee {$employeePin} on {$shiftDate->toDateString()}.");
        return 1;
    }

    /**
     * Creates a new EmployeeShift record in the database.
     * This method centralizes all `EmployeeShift::create` calls for consistency.
     *
     * @param string $employeePin
     * @param Carbon $shiftRecordDate The date under which the shift is recorded.
     * @param int|null $clockInId Attendance ID of clock-in punch.
     * @param int|null $clockOutId Attendance ID of clock-out punch.
     * @param Carbon|null $clockInTime Actual clock-in time.
     * @param Carbon|null $clockOutTime Actual clock-out time.
     * @param float $hoursWorked Total hours worked for the shift.
     * @param string $shiftType Determined shift type (e.g., 'day', 'night', 'missing_clockout').
     * @param bool $isComplete True if both clock-in and clock-out are present.
     * @param string $notes Comprehensive notes for the shift.
     * @param int $latenessMinutes Lateness in minutes.
     * @param float $overtime1_5x Hours at 1.5x rate.
     * @param float $overtime2_0x Hours at 2.0x rate.
     * @param bool $isHolidayOnRecordDate True if the shift_date itself is a holiday.
     * @param bool $isWeekendOnRecordDate True if the shift_date itself is a weekend.
     * @param float $regularHours Regular hours worked for the shift.
     */
    private function createShiftRecord(
        string $employeePin,
        ?Carbon $shiftRecordDate, // Can be null for missing_in/out where date is inferred from punch
        ?int $clockInId,
        ?int $clockOutId,
        ?Carbon $clockInTime,
        ?Carbon $clockOutTime,
        float $hoursWorked,
        string $shiftType,
        bool $isComplete,
        string $notes,
        int $latenessMinutes,
        float $overtime1_5x,
        float $overtime2_0x,
        bool $isHolidayOnRecordDate,
        bool $isWeekendOnRecordDate,
        float $regularHours
    ) {
        // Determine the shift_date for records with missing punches.
        // If clockInTime is present, use its date. If only clockOutTime, use its date.
        // If both are null, this shouldn't happen for a shift record.
        $finalShiftDate = $shiftRecordDate ?? ($clockInTime ? $clockInTime->toDateString() : ($clockOutTime ? $clockOutTime->toDateString() : null));

        if (! $finalShiftDate) {
            ($this->logger)('error', "Cannot determine shift_date for shift record. Employee: {$employeePin}, Notes: {$notes}");
            return; // Prevent creating record without a date
        }

        EmployeeShift::create([
            'employee_pin'            => $employeePin,
            'shift_date'              => $finalShiftDate,
            'clock_in_attendance_id'  => $clockInId,
            'clock_out_attendance_id' => $clockOutId,
            'clock_in_time'           => $clockInTime,
            'clock_out_time'          => $clockOutTime,
            'hours_worked'            => round($hoursWorked, 2),
            'regular_hours_worked'    => round($regularHours, 2),
            'shift_type'              => $shiftType,
            'is_complete'             => $isComplete,
            'notes'                   => $notes,
            'lateness_minutes'        => $latenessMinutes,
            'overtime_hours_1_5x'     => round($overtime1_5x, 2),
            'overtime_hours_2_0x'     => round($overtime2_0x, 2),
            'is_holiday'              => $isHolidayOnRecordDate,
            'is_weekend'              => $isWeekendOnRecordDate,
        ]);
    }
}
