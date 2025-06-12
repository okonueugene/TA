<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProcessAttendanceShifts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:shifts
                            {date? : The specific date to process (YYYY-MM-DD). Shifts are recorded under this date.}
                            {--month= : Process shifts for a whole month (YYYY-MM). If not provided, defaults to the current month.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processes attendance, associating shifts with the given date (as start or end for night shifts). Can process a single day or a whole month.';

    // Shift definitions
    const DAY_SHIFT_START_HOUR = 7;
    const DAY_SHIFT_START_MINUTE = 0;
    const DAY_SHIFT_END_HOUR = 18;
    const DAY_SHIFT_END_MINUTE = 0;

    const NIGHT_SHIFT_START_HOUR = 18;
    const NIGHT_SHIFT_START_MINUTE = 0;
    const NIGHT_SHIFT_END_HOUR = 7;
    const NIGHT_SHIFT_END_MINUTE = 0;

    const PREV_DAY_NIGHT_IN_AFTER_HOUR = 17;
    const TARGET_DAY_NIGHT_OUT_BEFORE_HOUR = 8;
    const NEXT_DAY_CLOCKOUT_LOOKAHEAD_HOUR = 10;

    const DAY_SHIFT_START_BUFFER_MINUTES = 59;
    const MIN_HOURS_FOR_SAME_PIN_SHIFT = 4; // Added constant for human error check

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
            // Default behavior if no arguments or options are provided (process today as before)
            $this->processSingleDay(Carbon::today()->startOfDay());
        }

        return Command::SUCCESS;
    }

    /**
     * Processes shifts for a single specified date.
     *
     * @param Carbon $targetDate The date to process.
     */
    private function processSingleDay(Carbon $targetDate)
    {
        $this->info("Processing attendance shifts to be recorded under date: {$targetDate->toDateString()}");
        $shiftsProcessed = 0;

        DB::transaction(function () use ($targetDate, &$shiftsProcessed) {
            $previousDay = $targetDate->copy()->subDay();
            $nextDay = $targetDate->copy()->addDay();

            $activityStartDate = $targetDate->copy()->subDays(1)->startOfDay(); // Fetch from previous day start
            $activityEndDate = $targetDate->copy()->addDays(1)->endOfDay(); // Fetch till next day end

            $potentialPins = Attendance::whereBetween('datetime', [$activityStartDate, $activityEndDate])
                ->distinct()
                ->pluck('pin')
                ->map(function ($pin) {
                    return strlen($pin) > 1 ? substr($pin, 1) : null;
                })
                ->filter()
                ->unique()
                ->values();

            if ($potentialPins->isEmpty()) {
                $this->info("No employee activity found around {$targetDate->toDateString()}.");
                return;
            }

            $employees = Employee::whereIn('pin', $potentialPins)->get();

            if ($employees->isEmpty()) {
                $this->info("No employees found for active pins.");
                return;
            }

            foreach ($employees as $employee) {
                $this->processEmployeeShifts($employee, $targetDate, $previousDay, $nextDay, $shiftsProcessed);
            }
        });

        $this->info("Finished processing for {$targetDate->toDateString()}. Total shifts processed: {$shiftsProcessed}");
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


    private function processEmployeeShifts(Employee $employee, Carbon $targetDate, Carbon $previousDay, Carbon $nextDay, &$shiftsProcessed)
    {
        $employeePin = $employee->pin;
        $usedAttendanceIds = new Collection(); // Initialize here for each employee

        // Delete existing shifts for this employee and shift_date to prevent duplicates on re-runs
        EmployeeShift::where('employee_pin', $employeePin)
                     ->whereDate('shift_date', $targetDate->toDateString())
                     ->delete();

        // Fetch all potentially relevant punches ONCE for this employee
        $allEmployeePunches = Attendance::where(function ($q) use ($employeePin) {
            $q->where('pin', '1' . $employeePin)
              ->orWhere('pin', '2' . $employeePin);
        })
            ->whereDate('datetime', '>=', $previousDay->toDateString())
            ->whereDate('datetime', '<=', $nextDay->toDateString())
            ->orderBy('datetime')
            ->get()
            ->map(function ($record) {
                $record->datetime = Carbon::parse($record->datetime); // Ensure Carbon
                return $record;
            });

        // --- 1. Process Night Shifts: Started Previous Day, Ending on Target Date ---
        $this->processNightShiftEndingOnTargetDay($employee, $targetDate, $previousDay, $allEmployeePunches, $usedAttendanceIds, $shiftsProcessed);

        // --- 2. Process Shifts Starting on Target Date ---
        $this->processShiftsStartingOnTargetDay($employee, $targetDate, $nextDay, $allEmployeePunches, $usedAttendanceIds, $shiftsProcessed);

        // --- 3. Process any remaining isolated punches on Target Date ---
        $this->processIsolatedPunchesOnTargetDate($employee, $targetDate, $allEmployeePunches, $usedAttendanceIds, $shiftsProcessed);
    }

    private function getPunches(Collection $allPunches, Carbon $day, ?string $type, Collection $usedAttendanceIds)
    {
        return $allPunches->filter(function ($punch) use ($day, $type, $usedAttendanceIds) {
            if ($usedAttendanceIds->contains($punch->id)) {
                return false;
            }
            $pinPrefix = $type === 'in' ? '1' : ($type === 'out' ? '2' : null);
            $typeMatch = $pinPrefix ? str_starts_with($punch->pin, $pinPrefix) : true; // Check first char of 'pin'
            return $punch->datetime->isSameDay($day) && $typeMatch;
        });
    }

    private function processNightShiftEndingOnTargetDay(Employee $employee, Carbon $targetEndDate, Carbon $previousDay, Collection $allEmployeePunches, Collection &$usedAttendanceIds, &$shiftsProcessed)
    {
        $employeePin = $employee->pin;

        $prevDayClockIns = $this->getPunches($allEmployeePunches, $previousDay, 'in', $usedAttendanceIds)
            ->filter(fn ($ci) => $ci->datetime->hour >= self::PREV_DAY_NIGHT_IN_AFTER_HOUR);

        $targetDayEarlyClockOuts = $this->getPunches($allEmployeePunches, $targetEndDate, 'out', $usedAttendanceIds)
            ->filter(fn ($co) => $co->datetime->hour < self::TARGET_DAY_NIGHT_OUT_BEFORE_HOUR);

        if ($prevDayClockIns->isNotEmpty() && $targetDayEarlyClockOuts->isNotEmpty()) {
            $firstPrevDayNightIn = $prevDayClockIns->sortBy('datetime')->first();
            $matchingTargetDayNightOut = $targetDayEarlyClockOuts->filter(fn ($co) => $co->datetime->greaterThan($firstPrevDayNightIn->datetime))->sortBy('datetime')->first();

            if ($firstPrevDayNightIn && $matchingTargetDayNightOut) {
                // --- RECTIFICATION START ---
                // Check if a shift has already been created for this clock-in or clock-out.
                // This prevents this function from creating a duplicate of a shift that was
                // correctly processed by `processShiftsStartingOnTargetDay` on the previous day's run.
                $shiftAlreadyExists = EmployeeShift::where('clock_in_attendance_id', $firstPrevDayNightIn->id)
                    ->orWhere('clock_out_attendance_id', $matchingTargetDayNightOut->id)
                    ->exists();

                if ($shiftAlreadyExists) {
                    $this->info("Skipping night shift for PIN {$employeePin} ending on {$targetEndDate->toDateString()} because it was already processed.");
                    // Mark punches as used for this run to prevent them being processed as isolated punches.
                    $usedAttendanceIds->push($firstPrevDayNightIn->id);
                    $usedAttendanceIds->push($matchingTargetDayNightOut->id);
                    return; // Exit and do not create a duplicate shift.
                }
                // --- RECTIFICATION END ---

                $this->processCompleteShiftWrapper($employee, $previousDay, $firstPrevDayNightIn, $matchingTargetDayNightOut, $allEmployeePunches, $shiftsProcessed, true);
                $usedAttendanceIds->push($firstPrevDayNightIn->id);
                $usedAttendanceIds->push($matchingTargetDayNightOut->id);
            }
        }
    }

 private function processShiftsStartingOnTargetDay(Employee $employee, Carbon $targetDate, Carbon $nextDay, Collection $allEmployeePunches, Collection &$usedAttendanceIds, &$shiftsProcessed)
    {
        $employeePin = $employee->pin;
        $firstTargetDayIn = null;
        $lastMatchingOut = null;
        $isHumanErrorOverride = false; // Flag for human error cases

        // --- Attempt 1: Find a standard IN-OUT pair ---
        $potentialFirstIn = $this->getPunches($allEmployeePunches, $targetDate, 'in', $usedAttendanceIds)->sortBy('datetime')->first();

        if ($potentialFirstIn) {
            $potentialLastOut = $this->getPunches($allEmployeePunches, $targetDate, 'out', $usedAttendanceIds)
                                    ->filter(fn($co) => $co->datetime->greaterThan($potentialFirstIn->datetime))
                                    ->sortByDesc('datetime')->first();

            if (!$potentialLastOut) {
                // If no same-day OUT, look for a next-day clock-out for a night shift
                $potentialLastOut = $this->getPunches($allEmployeePunches, $nextDay, 'out', $usedAttendanceIds)
                                        ->filter(fn($co) => $co->datetime->hour < self::NEXT_DAY_CLOCKOUT_LOOKAHEAD_HOUR && $co->datetime->greaterThan($potentialFirstIn->datetime));
                $potentialLastOut = $potentialLastOut->sortBy('datetime')->first(); // Earliest next-day out
            }

            if ($potentialLastOut) {
                $firstTargetDayIn = $potentialFirstIn;
                $lastMatchingOut = $potentialLastOut;
            }
        }

        // --- Attempt 2: Human Error - IN-IN (if standard IN-OUT failed) ---
        if (!$firstTargetDayIn && $potentialFirstIn) { // Only attempt if an IN was found but no OUT was paired
            $humanErrorClockOutCandidate = $this->getPunches($allEmployeePunches, $targetDate, 'in', $usedAttendanceIds)
                                            ->filter(fn($punch) => $punch->datetime->greaterThan($potentialFirstIn->datetime))
                                            ->sortBy('datetime')->first();

            if ($humanErrorClockOutCandidate) {
                $firstTargetDayIn = $potentialFirstIn;
                $lastMatchingOut = $humanErrorClockOutCandidate;
                $isHumanErrorOverride = true;
                $this->warn("Detected IN-IN human error for {$employeePin} on {$targetDate->toDateString()}.");
            }
        }

        // --- Attempt 3: Human Error - OUT-OUT (if previous attempts failed) ---
        // This scenario means no valid IN was found to start a shift OR the first IN couldn't be paired.
        // But the very first UNUSED punch of the day is an OUT.
        if (!$firstTargetDayIn) { // Only proceed if no shift start has been established yet
            $firstUnusedPunchOnTargetDay = $allEmployeePunches->filter(function($punch) use ($targetDate, $usedAttendanceIds) {
                return $punch->datetime->isSameDay($targetDate) && !$usedAttendanceIds->contains($punch->id);
            })->sortBy('datetime')->first();

            if ($firstUnusedPunchOnTargetDay && str_starts_with($firstUnusedPunchOnTargetDay->pin, '2')) { // If the first unused punch is an OUT
                $subsequentOutCandidate = $allEmployeePunches->filter(function($punch) use ($firstUnusedPunchOnTargetDay, $targetDate, $usedAttendanceIds) {
                    return $punch->datetime->isSameDay($targetDate) &&
                           str_starts_with($punch->pin, '2') && // Ensure it's an OUT punch
                           $punch->datetime->greaterThan($firstUnusedPunchOnTargetDay->datetime) &&
                           !$usedAttendanceIds->contains($punch->id);
                })->sortByDesc('datetime')->first(); // Get the latest subsequent OUT

                if ($subsequentOutCandidate) {
                    $firstTargetDayIn = $firstUnusedPunchOnTargetDay; // Treat first OUT as IN
                    $lastMatchingOut = $subsequentOutCandidate;
                    $isHumanErrorOverride = true;
                    $this->warn("Detected OUT-OUT human error for {$employeePin} on {$targetDate->toDateString()}. Treating first OUT as IN.");
                }
            }
        }

        // --- Process the found shift (standard or human error) ---
        if ($firstTargetDayIn && $lastMatchingOut) {
            $isNightShift = !$lastMatchingOut->datetime->isSameDay($targetDate);
            $this->processCompleteShiftWrapper($employee, $targetDate, $firstTargetDayIn, $lastMatchingOut, $allEmployeePunches, $shiftsProcessed, $isNightShift, $isHumanErrorOverride);

            // Mark all punches between the start and end of this shift as used
            $shiftStartTime = $firstTargetDayIn->datetime;
            $shiftEndTime = $lastMatchingOut->datetime;
            $allPunchesWithinShift = $allEmployeePunches->filter(function ($punch) use ($shiftStartTime, $shiftEndTime) {
                return $punch->datetime->gte($shiftStartTime) && $punch->datetime->lte($shiftEndTime);
            });
            $allPunchesWithinShift->pluck('id')->each(fn($id) => $usedAttendanceIds->push($id));
            $usedAttendanceIds = $usedAttendanceIds->unique()->values();
        }
    }

    private function processIsolatedPunchesOnTargetDate(Employee $employee, Carbon $targetDate, Collection $allEmployeePunches, Collection &$usedAttendanceIds, &$shiftsProcessed)
    {
        $employeePin = $employee->pin;

        // --- NEW LOGIC START ---
        // Get the earliest clock-in found for any shift on this targetDate.
        // This helps identify a baseline for "normal" shift start, even if the primary shift has already consumed it.
        $earliestOverallClockInForDay = $allEmployeePunches
            ->filter(fn($punch) => $punch->datetime->isSameDay($targetDate) && str_starts_with($punch->pin, '1'))
            ->sortBy('datetime')
            ->first();
        // --- NEW LOGIC END ---

        // Check for isolated clock-ins on targetDate
        $isolatedTargetDayIn = $this->getPunches($allEmployeePunches, $targetDate, 'in', $usedAttendanceIds)->sortBy('datetime')->first();
        if ($isolatedTargetDayIn) {
            $isWeekend = $targetDate->isWeekend();
            $isHoliday = $this->isHoliday($targetDate);
            $this->processMissingClockOut($employeePin, $targetDate, $isolatedTargetDayIn, $isWeekend, $isHoliday, $shiftsProcessed);
            $usedAttendanceIds->push($isolatedTargetDayIn->id);
        }

        // Check for isolated clock-outs on targetDate
        $isolatedTargetDayOut = $this->getPunches($allEmployeePunches, $targetDate, 'out', $usedAttendanceIds)->sortByDesc('datetime')->first(); // Get the last one if multiple
        if ($isolatedTargetDayOut) {
            // --- NEW CONDITION START ---
            // If this isolated clock-out happened *before* the earliest clock-in found for the day,
            // or very close to it (within a few seconds/minutes, as an accidental punch),
            // it's likely an accidental early punch and should be ignored (not treated as missing clock-in).
            // Example: May 13th: Out at 06:57:47, In at 06:57:51. The OUT is before the first IN.
            // Using a small buffer here for robustness around the exact same timestamp.
            $bufferInSeconds = 10; // Allow for punches within 10 seconds before the earliest IN

            if ($earliestOverallClockInForDay &&
                $isolatedTargetDayOut->datetime->lessThanOrEqualTo($earliestOverallClockInForDay->datetime) &&
                $isolatedTargetDayOut->datetime->diffInSeconds($earliestOverallClockInForDay->datetime) <= $bufferInSeconds) {
                
                $this->warn("Ignoring an isolated clock-out for {$employeePin} at {$isolatedTargetDayOut->datetime->toDateTimeString()} as it occurred immediately before/at the first clock-in for the day, likely accidental.");
                $usedAttendanceIds->push($isolatedTargetDayOut->id); // Mark as used/handled to prevent re-processing
                return; // Don't create a missing_clockin record
            }
            // --- NEW CONDITION END ---

            $isWeekend = $targetDate->isWeekend();
            $isHoliday = $this->isHoliday($targetDate);
            $this->processMissingClockIn($employeePin, $targetDate, $isolatedTargetDayOut, $isWeekend, $isHoliday, $shiftsProcessed);
            $usedAttendanceIds->push($isolatedTargetDayOut->id);
        }
    }


    private function processCompleteShiftWrapper(Employee $employee, Carbon $shiftRecordDate, $clockInRecord, $clockOutRecord, Collection $allEmployeePunches, &$shiftsProcessed, bool $isPrevDayNightShift, bool $isHumanErrorOverride = false)
    {
        $shiftActualStartDate = $clockInRecord->datetime->copy()->startOfDay();
        $isWeekend = $shiftActualStartDate->isWeekend();
        $isHoliday = $this->isHoliday($shiftActualStartDate);
        $isSaturday = $shiftActualStartDate->isSaturday();
        $isSundayOrHoliday = $shiftActualStartDate->isSunday() || $isHoliday;

        // For human error, we need punches specific to the days the shift occurred on
        $clockInsForErrorCheck = $this->getPunches($allEmployeePunches, $clockInRecord->datetime->copy()->startOfDay(), null, new Collection()); // Fresh get, no used IDs for error check context
        $clockOutsForErrorCheck = $this->getPunches($allEmployeePunches, $clockOutRecord->datetime->copy()->startOfDay(), null, new Collection());

        $this->processCompleteShift(
            $employee->pin,
            $shiftRecordDate,
            $clockInRecord,
            $clockOutRecord,
            $clockInsForErrorCheck,
            $clockOutsForErrorCheck,
            $isWeekend,
            $isHoliday,
            $isSaturday,
            $isSundayOrHoliday,
            $shiftsProcessed,
            $isPrevDayNightShift,
            $shiftActualStartDate,
            $isHumanErrorOverride // Pass the flag down
        );
    }

    private function processCompleteShift(
        $employeePin,
        Carbon $shiftRecordDate,
        $firstClockIn,
        $lastClockOut,
        Collection $relevantClockInsForError,
        Collection $relevantClockOutsForError,
        $isWeekendBasedOnActualStart,
        $isHolidayBasedOnActualStart,
        $isSaturdayBasedOnActualStart,
        $isSundayOrHolidayBasedOnActualStart,
        &$shiftsProcessed,
        bool $isPrevDayNightShift,
        Carbon $shiftActualStartDate,
        bool $isHumanErrorOverride = false
    ) {
        $clockInTime = $firstClockIn->datetime;
        $clockOutTime = $lastClockOut->datetime;

        if ($clockOutTime->lessThanOrEqualTo($clockInTime)) {
            $notes = "Inverted punch times. In:{$clockInTime->toDateTimeString()}, Out:{$clockOutTime->toDateTimeString()}";
            $this->createShiftRecord(
                $employeePin,
                $shiftRecordDate,
                $firstClockIn->id,
                $lastClockOut->id,
                $clockInTime,
                $clockOutTime,
                0,
                'inverted_times',
                false,
                $notes,
                0,
                0.0,
                0.0,
                $this->isHoliday($shiftRecordDate),
                $shiftRecordDate->isWeekend(),
                0
            );
            $this->warn("Processed inverted times for {$employeePin}, shift recorded on {$shiftRecordDate->toDateString()}");
            $shiftsProcessed++;
            return;
        }

        $hasHumanError = $this->detectHumanError($relevantClockInsForError, $relevantClockOutsForError, $clockInTime, $clockOutTime);
        $hoursWorked = $clockOutTime->floatDiffInHours($clockInTime);
        $shiftTypeDetermined = 'unknown';

        if ($isWeekendBasedOnActualStart || $isHolidayBasedOnActualStart) {
            $shiftTypeDetermined = 'overtime_shift';
        } else {
            $shiftTypeDetermined = $this->determineShiftType($clockInTime, $clockOutTime, $shiftActualStartDate);
        }

        $latenessMinutes = $this->calculateLateness($clockInTime, $shiftTypeDetermined, $shiftActualStartDate, $isPrevDayNightShift);
        list($overtime1_5x, $overtime2_0x, $regularHours) = $this->calculateOvertimeAndHours($hoursWorked, $clockInTime, $clockOutTime, $shiftActualStartDate, $shiftTypeDetermined, $isSaturdayBasedOnActualStart, $isSundayOrHolidayBasedOnActualStart);

        $notes = $this->generateNotes($clockInTime, $clockOutTime, $shiftTypeDetermined, $hoursWorked, $hasHumanError, $latenessMinutes, $overtime1_5x, $overtime2_0x, $isHumanErrorOverride);

        $this->createShiftRecord(
            $employeePin,
            $shiftRecordDate,
            $firstClockIn->id,
            $lastClockOut->id,
            $clockInTime,
            $clockOutTime,
            $hoursWorked,
            $shiftTypeDetermined,
            true,
            $notes,
            $latenessMinutes,
            $overtime1_5x,
            $overtime2_0x,
            $this->isHoliday($shiftRecordDate),
            $shiftRecordDate->isWeekend(),
            $regularHours
        );

        $this->info("Processed complete shift for {$employeePin}, recorded on {$shiftRecordDate->toDateString()}. Type:{$shiftTypeDetermined}, Hours:" . round($hoursWorked, 2) . ",Regular:" . round($regularHours, 2) . ",OT1.5:" . round($overtime1_5x, 2) . ",OT2.0:" . round($overtime2_0x, 2));
        $shiftsProcessed++;
    }

    private function processMissingClockOut($employeePin, Carbon $shiftDate, $clockIn, $isWeekend, $isHoliday, &$shiftsProcessed)
    {
        $shiftType = '';
        if ($isWeekend && $this->isHoliday($shiftDate)) {
            $shiftType = 'holiday_incomplete'; // Holiday on weekend
        } elseif ($isWeekend) {
            $shiftType = 'weekend_incomplete';
        } elseif ($this->isHoliday($shiftDate)) {
            $shiftType = 'holiday_incomplete';
        } else {
            $shiftType = 'missing_clockout';
        }

        $latenessMinutes = $this->calculateLateness($clockIn->datetime, 'day', $clockIn->datetime->copy()->startOfDay(), false);
        $notes = "Missing clock-out. Clock-in:{$clockIn->datetime->toDateTimeString()}";

        $this->createShiftRecord(
            $employeePin,
            $shiftDate,
            $clockIn->id,
            null,
            $clockIn->datetime,
            null,
            0,
            $shiftType,
            false,
            $notes,
            $latenessMinutes,
            0.0,
            0.0,
            $isHoliday,
            $isWeekend,
            0
        );
        $this->warn("Missing clock-out for employee {$employeePin} on {$shiftDate->toDateString()}");
        $shiftsProcessed++;
    }

    private function processMissingClockIn($employeePin, Carbon $shiftDate, $clockOut, $isWeekend, $isHoliday, &$shiftsProcessed)
    {
        $shiftType = '';
        if ($isWeekend && $this->isHoliday($shiftDate)) {
            $shiftType = 'holiday_incomplete';
        } elseif ($isWeekend) {
            $shiftType = 'weekend_incomplete';
        } elseif ($this->isHoliday($shiftDate)) {
            $shiftType = 'holiday_incomplete';
        } else {
            $shiftType = 'missing_clockin';
        }

        $notes = "Missing clock-in. Clock-out:{$clockOut->datetime->toDateTimeString()}";

        $this->createShiftRecord(
            $employeePin,
            $shiftDate,
            null,
            $clockOut->id,
            null,
            $clockOut->datetime,
            0,
            $shiftType,
            false,
            $notes,
            0,
            0.0,
            0.0,
            $isHoliday,
            $isWeekend,
            0
        );
        $this->warn("Missing clock-in for employee {$employeePin} on {$shiftDate->toDateString()}");
        $shiftsProcessed++;
    }

    // Pass Carbon $shiftActualIn, $shiftActualOut for context
    private function detectHumanError(Collection $allClockInsOnDayOfIn, Collection $allClockOutsOnDayOfOut, Carbon $shiftActualIn, Carbon $shiftActualOut): bool
    {
        // Filter to punches on the specific day of the shift's clock-in
        $relevantClockIns = $allClockInsOnDayOfIn->filter(fn ($p) => $p->datetime->isSameDay($shiftActualIn));
        if ($relevantClockIns->count() > 1) {
            $times = $relevantClockIns->pluck('datetime')->sort();
            for ($i = 1; $i < $times->count(); $i++) {
                if ($times->get($i)->diffInHours($times->get($i - 1)) > 1) {
                    return true;
                }
            }
        }

        // Filter to punches on the specific day of the shift's clock-out
        $relevantClockOuts = $allClockOutsOnDayOfOut->filter(fn ($p) => $p->datetime->isSameDay($shiftActualOut));
        if ($relevantClockOuts->count() > 1) {
            $times = $relevantClockOuts->pluck('datetime')->sort();
            for ($i = 1; $i < $times->count(); $i++) {
                if ($times->get($i)->diffInHours($times->get($i - 1)) > 1) {
                    return true;
                }
            }
        }

        return false;
    }

    private function determineShiftType(Carbon $clockInTime, Carbon $clockOutTime, Carbon $shiftActualStartDate): string
    {
        // Core boundaries based on the actual start day of the shift
        $coreDayShiftStart = $shiftActualStartDate->copy()->setTime(self::DAY_SHIFT_START_HOUR, self::DAY_SHIFT_START_MINUTE); // 07:00
        $coreNightShiftStart = $shiftActualStartDate->copy()->setTime(self::NIGHT_SHIFT_START_HOUR, self::NIGHT_SHIFT_START_MINUTE); // 18:00

        // Buffered start for 'day' shift classification (e.g., allow clock-in from 06:45)
        $bufferedDayShiftStart = $coreDayShiftStart->copy()->subMinutes(self::DAY_SHIFT_START_BUFFER_MINUTES);

        if ($clockInTime->isSameDay($clockOutTime)) {
            // Shift started and ended on the $shiftActualStartDate.
            // Classify as 'day' if:
            // 1. It starts at or after the buffered day start time (e.g., 06:45).
            // 2. It starts *before* the defined night shift start time (e.g., before 18:00).
            // This prevents an evening shift like 19:00-23:00 from being called 'day'.
            if ($clockInTime->greaterThanOrEqualTo($bufferedDayShiftStart) && $clockInTime->lt($coreNightShiftStart)) {
                return 'day';
            }
            // Otherwise, it's an irregular same-day shift.
            // Examples: Starts too early (05:00-10:00) or is a purely evening shift (19:00-23:00).
            return 'irregular_sameday';
        } else {
            // Spans midnight. $clockInTime is on $shiftActualStartDate, $clockOutTime is on the next day.
            // Buffer for night shift end (e.g. NIGHT_SHIFT_END_HOUR + 4 hours from your original code)
            // This is to catch night shifts that run into overtime but are still fundamentally 'night' shifts.
            $expectedNightShiftNominalEnd = $shiftActualStartDate->copy()->addDay()->setTime(self::NIGHT_SHIFT_END_HOUR, self::NIGHT_SHIFT_END_MINUTE);
            $bufferedNightShiftActualEnd = $expectedNightShiftNominalEnd->copy()->addHours(4); // e.g., 07:00 + 4 hours = 11:00

            // Standard Night Shift starting on $shiftActualStartDate
            if (
                $clockInTime->isSameDay($shiftActualStartDate) && // Should always be true in this context of the function call
                $clockInTime->greaterThanOrEqualTo($coreNightShiftStart) && // Started at/after 18:00 on $shiftActualStartDate
                $clockOutTime->isSameDay($shiftActualStartDate->copy()->addDay()) && // Ends on the next calendar day
                $clockOutTime->lessThanOrEqualTo($bufferedNightShiftActualEnd) // And ends within a reasonable window (e.g. before 11:00 next day)
            ) {
                return 'night';
            }

            // This condition is for night shifts that started on a *previous day* where $shiftActualStartDate IS that previous day.
            // This is the context when called from `processNightShiftEndingOnTargetDay`.
            if (
                $clockInTime->isSameDay($shiftActualStartDate) && // Clock-in is on the (previous) $shiftActualStartDate
                $clockInTime->hour >= self::PREV_DAY_NIGHT_IN_AFTER_HOUR && // Started late on $shiftActualStartDate (e.g. after 17:00)
                $clockOutTime->isSameDay($shiftActualStartDate->copy()->addDay()) && // Ends on the next day relative to $shiftActualStartDate
                $clockOutTime->hour < self::TARGET_DAY_NIGHT_OUT_BEFORE_HOUR // Ends early morning (e.g. before 08:00)
            ) {
                return 'night';
            }

            return 'irregular_crossday';
        }
    }

    private function calculateLateness(Carbon $clockInTime, string $shiftType, Carbon $shiftActualStartDate, bool $isPrevDayNightShift): int
    {
        if ($shiftActualStartDate->isWeekend() || $this->isHoliday($shiftActualStartDate)) {
            return 0;
        }

        $expectedStart = null;
        if ($shiftType === 'day') {
            $expectedStart = $shiftActualStartDate->copy()->setTime(self::DAY_SHIFT_START_HOUR, self::DAY_SHIFT_START_MINUTE);
        } elseif ($shiftType === 'night') {
            // If it's a night shift that started on a previous day, its lateness is against PREV_DAY_NIGHT_IN_AFTER_HOUR
            // If it's a night shift starting on shiftActualStartDate, its lateness is against NIGHT_SHIFT_START_HOUR
            $expectedHour = $isPrevDayNightShift ? self::PREV_DAY_NIGHT_IN_AFTER_HOUR : self::NIGHT_SHIFT_START_HOUR;
            $expectedStart = $shiftActualStartDate->copy()->setTime($expectedHour, 0);
        } else {
            return 0; // No lateness for irregular, overtime_shift, incomplete, missing
        }

        return $clockInTime->greaterThan($expectedStart) ? $clockInTime->diffInMinutes($expectedStart) : 0;
    }

    private function calculateOvertimeAndHours(float $totalHoursWorkedInitially, Carbon $clockInTime, Carbon $clockOutTime, Carbon $shiftActualStartDate, string $shiftType, bool $isSaturday, bool $isSundayOrHoliday): array
    {
        if ($totalHoursWorkedInitially <= 0) {
            return [0.0, 0.0, 0.0]; // regular, ot1.5, ot2.0
        }

        $overtime1_5x = 0.0;
        $overtime2_0x = 0.0;
        $regularHours = 0.0;

        $currentDt = $clockInTime->copy();

        while ($currentDt < $clockOutTime) {
            $segmentEndDt = $currentDt->copy()->addMinute(); // Process minute by minute for precision

            if ($segmentEndDt > $clockOutTime) {
                $segmentEndDt = $clockOutTime->copy();
            }

            $segmentDurationHours = $segmentEndDt->diffInSeconds($currentDt) / 3600.0;

            if ($segmentDurationHours <= 0) {
                $currentDt = $segmentEndDt;
                continue;
            }

            $calendarDayOfSegment = $currentDt->copy()->startOfDay();
            $isHolidaySegment = $this->isHoliday($calendarDayOfSegment); // Use $isSaturday and $isSundayOrHoliday passed in, which are based on $shiftActualStartDate for the primary rate
            // but check segment's day for applying specific day's holiday status.

            $hourRateAppliedToOvertime = false;

            if ($calendarDayOfSegment->isSaturday() && !$isHolidaySegment) {
                // Actual Saturday
                $overtime1_5x += $segmentDurationHours;
                $hourRateAppliedToOvertime = true;
            } elseif ($calendarDayOfSegment->isSunday() || $isHolidaySegment) {
                // Actual Sunday or Holiday
                $overtime2_0x += $segmentDurationHours;
                $hourRateAppliedToOvertime = true;
            }

            if (!$hourRateAppliedToOvertime) {
                // Weekday segment
                $isRegularSegmentHour = true;

                if ($shiftType === 'day' && $calendarDayOfSegment->isSameDay($shiftActualStartDate)) {
                    $dayShiftStandardEnd = $shiftActualStartDate->copy()->setTime(self::DAY_SHIFT_END_HOUR, self::DAY_SHIFT_END_MINUTE);
                    if ($currentDt->greaterThanOrEqualTo($dayShiftStandardEnd)) {
                        $overtime1_5x += $segmentDurationHours;
                        $isRegularSegmentHour = false;
                    }
                } elseif ($shiftType === 'night') {
                    // Night shift OT is after 07:00 on the day the standard night shift portion ends.
                    // The standard night shift portion ends on $shiftActualStartDate->addDay()
                    $nightShiftStandardEndDay = $shiftActualStartDate->copy()->addDay();
                    $nightShiftStandardEndTime = $nightShiftStandardEndDay->copy()->setTime(self::NIGHT_SHIFT_END_HOUR, self::NIGHT_SHIFT_END_MINUTE);

                    if ($calendarDayOfSegment->isSameDay($nightShiftStandardEndDay) && $currentDt->greaterThanOrEqualTo($nightShiftStandardEndTime)) {
                        $overtime1_5x += $segmentDurationHours;
                        $isRegularSegmentHour = false;
                    }
                }

                if ($isRegularSegmentHour) {
                    $regularHours += $segmentDurationHours;
                }
            }

            $currentDt = $segmentEndDt;
        }

        // Recalculate total hours from components for consistency, if needed, or ensure derived regular hours is correct.
        // For now, we assume the minute-by-minute sum is accurate.
        // If $totalHoursWorkedInitially is the source of truth for total, then regular can be derived.
        $calculatedTotalOvertime = $overtime1_5x + $overtime2_0x;
        $derivedRegularHours = $totalHoursWorkedInitially - $calculatedTotalOvertime;

        // Prefer derived regular hours if consistent, otherwise use summed.
        // This ensures total_hours = regular + OT.
        $regularHours = ($derivedRegularHours >= -0.001) ? $derivedRegularHours : $regularHours; // allow small float diff
        if ($regularHours < 0) {
            $regularHours = 0;
        }

        return [
            round($overtime1_5x, 2), // ot1.5
            round($overtime2_0x, 2), // ot2.0
            round($regularHours, 2), // regular
        ];
    }

    private function generateNotes(Carbon $clockInTime, Carbon $clockOutTime, string $shiftType, float $hoursWorked, bool $hasHumanError = false, int $latenessMinutes = 0, float $overtime1_5x = 0, float $overtime2_0x = 0, bool $isHumanErrorOverride = false): string
    {
        $notes = [];
        $notes[] = "Type:" . ucfirst(str_replace('_', ' ', $shiftType));

        if ($clockInTime) {
            $notes[] = "In:{$clockInTime->toDateTimeString()}";
        }
        if ($clockOutTime) {
            $notes[] = "Out:{$clockOutTime->toDateTimeString()}";
        }
        $notes[] = "Hours:" . round($hoursWorked, 2);

        if ($latenessMinutes > 0) {
            $notes[] = "Late:{$latenessMinutes}min";
        }
        if ($overtime1_5x > 0) {
            $notes[] = "OT1.5x:" . round($overtime1_5x, 2) . "hrs";
        }
        if ($overtime2_0x > 0) {
            $notes[] = "OT2.0x:" . round($overtime2_0x, 2) . "hrs";
        }
        if ($isHumanErrorOverride) {
            $notes[] = "HumanError: Same pin type used for In/Out.";
        }
        if ($hasHumanError) {
            $notes[] = "HumanError Flagged (large gap between punches).";
        }

        return implode('; ', $notes);
    }

    private function createShiftRecord(
        $employeePin,
        Carbon $shiftRecordDate,
        $clockInId,
        $clockOutId,
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
        EmployeeShift::create([
            'employee_pin' => $employeePin,
            'shift_date' => $shiftRecordDate->toDateString(),
            'clock_in_attendance_id' => $clockInId,
            'clock_out_attendance_id' => $clockOutId,
            'clock_in_time' => $clockInTime,
            'clock_out_time' => $clockOutTime,
            'hours_worked' => round($hoursWorked, 2),
            'regular_hours_worked' => round($regularHours, 2),
            'shift_type' => $shiftType,
            'is_complete' => $isComplete,
            'notes' => $notes,
            'lateness_minutes' => $latenessMinutes,
            'overtime_hours_1_5x' => round($overtime1_5x, 2),
            'overtime_hours_2_0x' => round($overtime2_0x, 2),
            'is_holiday' => $isHolidayOnRecordDate,
            'is_weekend' => $isWeekendOnRecordDate,
        ]);
    }

    /**
     * Checks if a given date is a holiday.
     * Caches results for performance.
     *
     * @param Carbon $date
     * @return bool
     */
    protected function isHoliday(Carbon $date): bool
    {
        static $holidaysCache = [];
        $dateString = $date->toDateString();

        if (isset($holidaysCache[$dateString])) {
            return $holidaysCache[$dateString];
        }

        // Assuming Holiday model has 'start_date' and 'description' columns
        // and 'Public holiday' is a reliable indicator.
        $isActualHoliday = Holiday::whereDate('start_date', $date->toDateString())
            ->where('description', 'LIKE', '%Public holiday%')
            ->exists();

        $holidaysCache[$dateString] = $isActualHoliday;
        return $isActualHoliday;
    }
}