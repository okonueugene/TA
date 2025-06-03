<?php
namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Holiday;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProcessAttendanceShifts extends Command
{
    protected $signature   = 'process:shifts {date? : The date to process (YYYY-MM-DD), shifts ENDING or CONTAINED on this date}';
    protected $description = 'Process attendance records into employee shifts with weekend/holiday overtime rules and night shift spanning.';

    // Shift definitions
    const DAY_SHIFT_START_HOUR   = 7;
    const DAY_SHIFT_START_MINUTE = 0;
    const DAY_SHIFT_END_HOUR     = 18;
    const DAY_SHIFT_END_MINUTE   = 0;

    const NIGHT_SHIFT_START_HOUR   = 18; // Nominal start time for a night shift
    const NIGHT_SHIFT_START_MINUTE = 0;
    const NIGHT_SHIFT_END_HOUR     = 7; // Nominal end time on the next day
    const NIGHT_SHIFT_END_MINUTE   = 0;

                                                 // How far into the next day to look for clock-outs for night shifts THAT START ON THE TARGET DATE
    const NEXT_DAY_CLOCKOUT_LOOKAHEAD_HOUR = 10; // e.g., look up to 10:00 AM next day

                                                       // --- New constants for previous day start night shifts ---
    const PREV_DAY_NIGHT_SHIFT_START_AFTER_HOUR  = 17; // Starts after 17:00 on prev day
    const TARGET_DAY_NIGHT_SHIFT_END_BEFORE_HOUR = 8;  // Ends before 08:00 on target day

    public function handle()
    {
        // $targetDate is the date for which shifts are accounted.
        // For shifts spanning midnight (starting previous day), $targetDate is the END day.
        // For shifts starting and ending on the same day, $targetDate is that day.
        $targetDate = $this->argument('date') ? Carbon::parse($this->argument('date'))->startOfDay() : Carbon::today()->startOfDay();
        $this->info("Processing attendance shifts to be accounted for on: {$targetDate->toDateString()}");

        $shiftsProcessed = 0;

        DB::transaction(function () use ($targetDate, &$shiftsProcessed) {
            // Get all unique employee pins that MIGHT have activity relevant to $targetDate
            // Fetch wider initially: from 2 days before target (for prev-prev day clock-in if shift is very long, though less likely with current rules)
            // up to target day end + lookahead.
            $activityStartDate = $targetDate->copy()->subDays(2)->startOfDay();
            $activityEndDate   = $targetDate->copy()->addDay()->endOfDay(); // Covers lookaheads

            $potentialPins = Attendance::whereBetween('datetime', [$activityStartDate, $activityEndDate])
                ->distinct()
                ->pluck('pin')
                ->map(function ($pin) {
                    if (strlen($pin) > 1) {
                        return substr($pin, 1);
                    }
                    return null;
                })
                ->filter()->unique()->values();

            if ($potentialPins->isEmpty()) {
                $this->info("No employee pins found with activity around {$targetDate->toDateString()}.");
                return;
            }

            $employees = Employee::whereIn('pin', $potentialPins)->get();
            if ($employees->isEmpty()) {
                $this->info("No employees found for the identified pins.");
                return;
            }

            foreach ($employees as $employee) {
                $this->processEmployeeShifts($employee, $targetDate, $shiftsProcessed);
            }
        });

        $this->info("Finished processing. Total shifts created/updated: {$shiftsProcessed}");
    }

    private function processEmployeeShifts(Employee $employee, Carbon $targetDate, &$shiftsProcessed)
    {
        $employeePin = $employee->pin;

        // Critical: Delete existing shifts for this employee accounted for on $targetDate.
        // This ensures idempotency for this $targetDate's run.
        EmployeeShift::where('employee_pin', $employeePin)
            ->whereDate('shift_date', $targetDate->toDateString())
            ->delete();

        $this->info("Processing for Employee {$employeePin} for accounting date {$targetDate->toDateString()}");

        // --- Step 1: Try to process night shifts ENDING on $targetDate (started previous day) ---
        $processedPrevDayNightShift = $this->processNightShiftEndingOnTargetDay($employee, $targetDate, $shiftsProcessed);

        // --- Step 2: Process shifts STARTING on $targetDate ---
        // Only proceed if a dominant night shift ending today wasn't the primary focus,
        // or to allow for a second, distinct shift on the same day.
        // For simplicity in this iteration, we'll allow this to run regardless and it should pick up
        // distinct punches. Careful punch management would be needed for true "consumed" logic.
        // if (!$processedPrevDayNightShift) { // Option: only if previous didn't find anything
        $this->processShiftsStartingOnTargetDay($employee, $targetDate, $shiftsProcessed, $processedPrevDayNightShift);
        // }
    }

    private function processNightShiftEndingOnTargetDay(Employee $employee, Carbon $targetEndDate, &$shiftsProcessed): bool
    {
        $employeePin = $employee->pin;
        $previousDay = $targetEndDate->copy()->subDay();

        // 1. Find earliest clock-in on $previousDay after PREV_DAY_NIGHT_SHIFT_START_AFTER_HOUR
        $clockInRecord = Attendance::where('pin', '1' . $employeePin)
            ->whereDate('datetime', $previousDay->toDateString())
            ->whereTime('datetime', '>', self::PREV_DAY_NIGHT_SHIFT_START_AFTER_HOUR . ':00:00')
            ->orderBy('datetime', 'asc')
            ->first();

        if (! $clockInRecord) {
            return false;
        }

        $clockInTime = Carbon::parse($clockInRecord->datetime);

        // 2. Find latest clock-out on $targetEndDate before TARGET_DAY_NIGHT_SHIFT_END_BEFORE_HOUR,
        //    and after $clockInTime.
        $clockOutRecord = Attendance::where('pin', '2' . $employeePin)
            ->whereDate('datetime', $targetEndDate->toDateString())
            ->whereTime('datetime', '<', self::TARGET_DAY_NIGHT_SHIFT_END_BEFORE_HOUR . ':00:00')
            ->where('datetime', '>', $clockInTime->toDateTimeString()) // Ensure clock-out is after clock-in
            ->orderBy('datetime', 'desc')
            ->first();

        if (! $clockOutRecord) {
            return false;
        }

        $clockOutTime = Carbon::parse($clockOutRecord->datetime);

        if ($clockOutTime->lessThanOrEqualTo($clockInTime)) {
            $this->warn("Inverted punch times for potential prev-day night shift for {$employeePin}. In: {$clockInTime}, Out: {$clockOutTime}");
            return false;
        }

        $this->info("Found potential previous-day night shift for {$employeePin}: In {$clockInTime->toDateTimeString()} (on {$previousDay->toDateString()}), Out {$clockOutTime->toDateTimeString()} (on {$targetEndDate->toDateString()})");

                              // This is a night shift ending on $targetEndDate
        $shiftType = 'night'; // Explicitly a night shift

        // Lateness based on previous day's night shift nominal start
        $latenessMinutes = $this->calculateLateness($clockInTime, $shiftType, $previousDay);

        // Human error detection needs punches from the clock-in day and clock-out day
        $allClockInsOnPrevDay       = Attendance::where('pin', '1' . $employeePin)->whereDate('datetime', $previousDay->toDateString())->get()->map(fn($r) => $r->datetime = Carbon::parse($r->datetime));
        $allClockOutsOnTargetEndDay = Attendance::where('pin', '2' . $employeePin)->whereDate('datetime', $targetEndDate->toDateString())->get()->map(fn($r) => $r->datetime = Carbon::parse($r->datetime));
        $hasHumanError              = $this->detectHumanError($allClockInsOnPrevDay, $allClockOutsOnTargetEndDay, $clockInTime, $clockOutTime);

        $calculatedHours = $this->calculateOvertimeAndHours($clockInTime, $clockOutTime, $shiftType);

        $notes = $this->generateNotes($clockInTime, $clockOutTime, $shiftType, $calculatedHours['total_hours'], $hasHumanError, $latenessMinutes, $calculatedHours['overtime_1_5x'], $calculatedHours['overtime_2_0x']);
        $notes .= "; Shift started on {$previousDay->toDateString()}, recorded on end date {$targetEndDate->toDateString()}.";

        // isHoliday and isWeekend for the shift record refers to the $targetEndDate (the accounting date)
        $isHolidayForRecord = $this->isHoliday($targetEndDate);
        $isWeekendForRecord = $targetEndDate->isWeekend();

        $this->createShiftRecord(
            $employeePin, $targetEndDate, // Shift date is the END date
            $clockInRecord->id, $clockOutRecord->id,
            $clockInTime, $clockOutTime,
            $calculatedHours['total_hours'], $shiftType, true, $notes, $latenessMinutes,
            $calculatedHours['overtime_1_5x'], $calculatedHours['overtime_2_0x'],
            $isHolidayForRecord, $isWeekendForRecord, $calculatedHours['regular_hours']
        );
        $this->info("Processed Night Shift (started {$previousDay->toDateString()}) ending on {$targetEndDate->toDateString()} for {$employeePin}. Type: {$shiftType}, Hours: " . round($calculatedHours['total_hours'], 2));
        $shiftsProcessed++;
        return true;
    }

    private function processShiftsStartingOnTargetDay(Employee $employee, Carbon $targetDate, &$shiftsProcessed, bool $prevDayShiftProcessed)
    {
        $employeePin = $employee->pin;

        // Fetch clock-ins ON $targetDate
        $clockInsOnTargetDate = Attendance::where('pin', '1' . $employeePin)
            ->whereDate('datetime', $targetDate->toDateString())
            ->orderBy('datetime', 'asc')
            ->get()->map(function ($record) {
            $record->datetime = Carbon::parse($record->datetime);
            return $record;
        });

        $firstClockInRecord = $clockInsOnTargetDate->first();

        if (! $firstClockInRecord) {
            // No clock-in on $targetDate. Check for missing clock-in if there are clock-outs.
            $lastClockOutOnTargetDayOnly = Attendance::where('pin', '2' . $employeePin)
                ->whereDate('datetime', $targetDate->toDateString())
                ->orderBy('datetime', 'desc')->first();
            if ($lastClockOutOnTargetDayOnly) {
                $this->processMissingClockIn($employeePin, $targetDate, Carbon::parse($lastClockOutOnTargetDayOnly->datetime), $lastClockOutOnTargetDayOnly->id, $shiftsProcessed);
            } elseif (! $prevDayShiftProcessed) { // Only log if no other shift was processed for this date
                $this->info("No clock-ins found for {$employeePin} starting on {$targetDate->toDateString()}, and no prior day shift ended on this date.");
            }
            return;
        }

        $firstClockInTime = $firstClockInRecord->datetime;

        // Fetch potential clock-outs: from $firstClockInTime on $targetDate up to lookahead on the NEXT day
        $lookaheadEndTime      = $targetDate->copy()->addDay()->setTime(self::NEXT_DAY_CLOCKOUT_LOOKAHEAD_HOUR, 0, 0);
        $allPotentialClockOuts = Attendance::where('pin', '2' . $employeePin)
            ->where('datetime', '>=', $firstClockInTime->toDateTimeString()) // Clock-out must be after or at the same time as first clock-in
            ->where('datetime', '<=', $lookaheadEndTime->toDateTimeString())
            ->orderBy('datetime', 'asc') // Order ascending to find the last one easily if needed
            ->get()->map(function ($record) {
            $record->datetime = Carbon::parse($record->datetime);
            return $record;
        });

        $lastClockOutRecord = $allPotentialClockOuts->filter(fn($co) => $co->datetime->greaterThan($firstClockInTime))->last();

        if ($lastClockOutRecord) {
            // We have a pair (firstClockInRecord, lastClockOutRecord) for a shift starting on $targetDate
            $clockInTime  = $firstClockInTime;
            $clockOutTime = $lastClockOutRecord->datetime;

            if ($clockOutTime->lessThanOrEqualTo($clockInTime)) {
                $notes = "Inverted punch times. In:{$clockInTime->toDateTimeString()}, Out:{$clockOutTime->toDateTimeString()}";
                $this->createShiftRecord($employeePin, $targetDate, $firstClockInRecord->id, $lastClockOutRecord->id, $clockInTime, $clockOutTime, 0, 'inverted_times', false, $notes, 0, 0, 0, $this->isHoliday($targetDate), $targetDate->isWeekend(), 0);
                $this->warn("Processed inverted times for {$employeePin} on {$targetDate->toDateString()} for shift starting on target date.");
                $shiftsProcessed++;
                return;
            }

            $this->info("Found potential shift for {$employeePin} starting on {$targetDate->toDateString()}: In {$clockInTime->toDateTimeString()}, Out {$clockOutTime->toDateTimeString()}");

            // Determine shift type based on $targetDate (which is the start day here)
            $isHolidayOnTargetDate = $this->isHoliday($targetDate);
            $isWeekendOnTargetDate = $targetDate->isWeekend();
            $shiftType             = 'unknown';

            if ($isWeekendOnTargetDate || $isHolidayOnTargetDate) {
                $shiftType = 'overtime_shift';
            } else {
                $shiftType = $this->determineShiftType($clockInTime, $clockOutTime, $targetDate);
            }

            $latenessMinutes = $this->calculateLateness($clockInTime, $shiftType, $targetDate);

            // Human error detection
            // $clockOutsOnShiftEndDay needs to be actual outs on the day $lastClockOutRecord->datetime falls.
            $clockOutsOnActualEndDay = Attendance::where('pin', '2' . $employeePin)
                ->whereDate('datetime', $clockOutTime->toDateString())
                ->get()->map(fn($r) => $r->datetime = Carbon::parse($r->datetime));
            $hasHumanError = $this->detectHumanError($clockInsOnTargetDate, $clockOutsOnActualEndDay, $clockInTime, $clockOutTime);

            $calculatedHours = $this->calculateOvertimeAndHours($clockInTime, $clockOutTime, $shiftType);

            $notes = $this->generateNotes($clockInTime, $clockOutTime, $shiftType, $calculatedHours['total_hours'], $hasHumanError, $latenessMinutes, $calculatedHours['overtime_1_5x'], $calculatedHours['overtime_2_0x']);

            $this->createShiftRecord(
                $employeePin, $targetDate, // Shift date is $targetDate (start date)
                $firstClockInRecord->id, $lastClockOutRecord->id,
                $clockInTime, $clockOutTime,
                $calculatedHours['total_hours'], $shiftType, true, $notes, $latenessMinutes,
                $calculatedHours['overtime_1_5x'], $calculatedHours['overtime_2_0x'],
                $isHolidayOnTargetDate, $isWeekendOnTargetDate, $calculatedHours['regular_hours']
            );
            $this->info("Processed Complete Shift for {$employeePin} starting on {$targetDate->toDateString()}. Type: {$shiftType}, Hours: " . round($calculatedHours['total_hours'], 2));
            $shiftsProcessed++;

        } else {
            // First clock-in exists on $targetDate, but no valid clock-out found
            $this->processMissingClockOut($employeePin, $targetDate, $firstClockInRecord, $shiftsProcessed);
        }
    }

    private function processMissingClockOut($employeePin, Carbon $shiftDate, $clockInRecord, &$shiftsProcessed)
    {
        $clockInTime = Carbon::parse($clockInRecord->datetime);
        $isHoliday   = $this->isHoliday($shiftDate);
        $isWeekend   = $shiftDate->isWeekend();

        // For missing clock-out, determine shift type based on clock-in day properties
        $shiftType = ($isWeekend || $isHoliday) ? 'overtime_shift_incomplete' : 'missing_clockout';

        // Lateness can still be calculated based on the clock-in time and expected start for that day type
        $inferredShiftForLateness = $shiftType;
        if ($shiftType === 'missing_clockout') { // If it's a weekday missing clockout
                                                     // Try to infer if it was intended day or night for lateness calculation
            $dayShiftStart   = $shiftDate->copy()->setTime(self::DAY_SHIFT_START_HOUR, self::DAY_SHIFT_START_MINUTE);
            $nightShiftStart = $shiftDate->copy()->setTime(self::NIGHT_SHIFT_START_HOUR, self::NIGHT_SHIFT_START_MINUTE);
            if ($clockInTime->diffInMinutes($dayShiftStart, false) < $clockInTime->diffInMinutes($nightShiftStart, false)) {
                $inferredShiftForLateness = 'day';
            } else {
                $inferredShiftForLateness = 'night';
            }
        } elseif (str_contains($shiftType, 'overtime_shift')) {
            $inferredShiftForLateness = 'overtime_shift'; // No lateness for overtime shifts
        }

        $latenessMinutes = $this->calculateLateness($clockInTime, $inferredShiftForLateness, $shiftDate);
        $notes           = "Missing clock-out. Clock-in: {$clockInTime->toDateTimeString()}";

        $this->createShiftRecord(
            $employeePin, $shiftDate, $clockInRecord->id, null,
            $clockInTime, null, 0, $shiftType, false, $notes, $latenessMinutes,
            0.0, 0.0, $isHoliday, $isWeekend, 0
        );
        $this->warn("Missing clock-out for {$employeePin} on {$shiftDate->toDateString()}");
        $shiftsProcessed++;
    }

    private function processMissingClockIn($employeePin, Carbon $shiftDate, Carbon $clockOutTime, $clockOutId, &$shiftsProcessed)
    {
        $isHoliday = $this->isHoliday($shiftDate);
        $isWeekend = $shiftDate->isWeekend();
        $shiftType = ($isWeekend || $isHoliday) ? 'overtime_shift_incomplete' : 'missing_clockin';
        $notes     = "Missing clock-in. Clock-out: {$clockOutTime->toDateTimeString()}";

        $this->createShiftRecord(
            $employeePin, $shiftDate, null, $clockOutId,
            null, $clockOutTime, 0, $shiftType, false, $notes, 0,
            0.0, 0.0, $isHoliday, $isWeekend, 0
        );
        $this->warn("Missing clock-in for {$employeePin} on {$shiftDate->toDateString()}");
        $shiftsProcessed++;
    }

    private function detectHumanError(Collection $clockInsForDay, Collection $clockOutsForDay, Carbon $shiftActualIn, Carbon $shiftActualOut): bool
    {
        // Filter clock-ins to only those on the actual shift's clock-in day
        $relevantClockIns = $clockInsForDay->filter(fn($r) => $r->datetime->isSameDay($shiftActualIn));
        if ($relevantClockIns->count() > 1) {
            $timesIns = $relevantClockIns->pluck('datetime')->sort(); // $timesIns should be a collection of Carbon objects
            for ($i = 1; $i < $timesIns->count(); $i++) {
                $currentTime  = $timesIns->get($i);
                $previousTime = $timesIns->get($i - 1);

                if (! $currentTime instanceof \Carbon\Carbon) {
                    \Log::error('detectHumanError (INS): currentTime is not Carbon!', ['type' => gettype($currentTime), 'value' => $currentTime]);
                    continue;
                }
                if (! $previousTime instanceof \Carbon\Carbon) {
                    \Log::error('detectHumanError (INS): previousTime is not Carbon!', ['type' => gettype($previousTime), 'value' => $previousTime]);
                    continue;
                }

                if ($currentTime->diffInHours($previousTime) > 1) {
                    return true;
                }

            }
        }

        // Filter clock-outs to only those on the actual shift's clock-out day
        $relevantClockOuts = $clockOutsForDay->filter(fn($r) => $r->datetime->isSameDay($shiftActualOut));
        if ($relevantClockOuts->count() > 1) {
            $timesOuts = $relevantClockOuts->pluck('datetime')->sort(); // $timesOuts should be a collection of Carbon objects
            for ($i = 1; $i < $timesOuts->count(); $i++) {
                $currentTime  = $timesOuts->get($i);
                $previousTime = $timesOuts->get($i - 1);

                // --- THIS IS AROUND YOUR LINE 347 ---
                if (! $currentTime instanceof \Carbon\Carbon) {
                    \Log::error('detectHumanError (OUTS): currentTime is not Carbon!', ['type' => gettype($currentTime), 'value' => $currentTime]);
                    // Potentially throw an exception or handle error to prevent further issues
                    throw new \Exception('detectHumanError (OUTS): currentTime is not a Carbon object.');
                }
                if (! $previousTime instanceof \Carbon\Carbon) {
                    \Log::error('detectHumanError (OUTS): previousTime is not Carbon!', ['type' => gettype($previousTime), 'value' => $previousTime]);
                    // Potentially throw an exception
                    throw new \Exception('detectHumanError (OUTS): previousTime is not a Carbon object.');
                }

                \Log::debug("detectHumanError (OUTS): Comparing: Current: {$currentTime->toDateTimeString()}, Previous: {$previousTime->toDateTimeString()}");

                // This is the original line that likely corresponds to your line 347
                if ($currentTime->diffInHours($previousTime) > 1) {
                    return true;
                }
            }
        }
        return false;
    }

    private function determineShiftType(Carbon $clockInTime, Carbon $clockOutTime, Carbon $shiftStartDate): string
    {
        // This is called for non-weekend/non-holiday shifts.
        // $shiftStartDate is the day the shift began.
        if ($clockInTime->isSameDay($clockOutTime)) {
            // If starts and ends on the same calendar day, it's a 'day' shift.
            return 'day';
        } else {
            // If it crosses midnight, it's a 'night' shift.
            return 'night';
        }
    }

    private function calculateLateness(Carbon $clockInTime, string $shiftType, Carbon $dayOfShiftStart): int
    {
        // $dayOfShiftStart is the calendar day the shift was expected to start on.
        if (str_contains($shiftType, 'overtime_shift') || str_contains($shiftType, 'incomplete') || str_contains($shiftType, 'missing')) {
            // No lateness for overtime, incomplete, or missing punch shifts usually,
            // unless specific rules for 'missing_clockout' with inferred type.
            if ($shiftType === 'missing_clockout' && ($this->isHoliday($dayOfShiftStart) || $dayOfShiftStart->isWeekend())) {
                return 0;
            }

        }
        if ($this->isHoliday($dayOfShiftStart) || $dayOfShiftStart->isWeekend()) {
            return 0; // No lateness on weekends/holidays
        }

        $expectedStart = null;
        if (str_starts_with($shiftType, 'day') || $shiftType === 'missing_clockout_day_inferred') { // also handle inferred type for missing clockout
            $expectedStart = $dayOfShiftStart->copy()->setTime(self::DAY_SHIFT_START_HOUR, self::DAY_SHIFT_START_MINUTE);
        } elseif (str_starts_with($shiftType, 'night') || $shiftType === 'missing_clockout_night_inferred') {
            $expectedStart = $dayOfShiftStart->copy()->setTime(self::NIGHT_SHIFT_START_HOUR, self::NIGHT_SHIFT_START_MINUTE);
        } else {
            return 0; // No lateness for other types like 'unknown', 'irregular', specific 'overtime_shift'
        }

        if ($clockInTime->greaterThan($expectedStart)) {
            return $clockInTime->diffInMinutes($expectedStart);
        }
        return 0;
    }

    /**
     * Calculates total hours, regular hours, and overtime hours (1.5x and 2.0x).
     * Overtime is applied based on actual day (Sat, Sun, Holiday) or exceeding shift boundaries on weekdays.
     */
    private function calculateOvertimeAndHours(Carbon $clockInTime, Carbon $clockOutTime, string $shiftType): array
    {
        $totalHours = $clockOutTime->floatDiffInHours($clockInTime);
        if ($totalHours <= 0) {
            return ['total_hours' => 0, 'regular_hours' => 0, 'overtime_1_5x' => 0, 'overtime_2_0x' => 0];
        }

        $overtime1_5x = 0.0;
        $overtime2_0x = 0.0;
        $regularHours = 0.0;

                                                                             // Iterate through the shift duration, hour by hour, or segment by segment
        $period = new CarbonPeriod($clockInTime, '1 minute', $clockOutTime); // Iterate minute by minute for precision

        $currentDt = $clockInTime->copy();

        while ($currentDt < $clockOutTime) {
            $segmentEndDt = $currentDt->copy()->addMinute();
            if ($segmentEndDt > $clockOutTime) {
                $segmentEndDt = $clockOutTime->copy();
            }

            $segmentDurationHours = $segmentEndDt->diffInSeconds($currentDt) / 3600.0;
            if ($segmentDurationHours <= 0) { // Should not happen if $currentDt < $clockOutTime
                $currentDt = $segmentEndDt;
                continue;
            }

            $calendarDayOfSegment = $currentDt->copy()->startOfDay();
            $isHolidaySegment     = $this->isHoliday($calendarDayOfSegment);
            $isSaturdaySegment    = $calendarDayOfSegment->isSaturday();
            $isSundaySegment      = $calendarDayOfSegment->isSunday();

            $hourRateApplied = false;

                                                            // Rule 1: Saturdays, Sundays, and Kenyan Holidays are entirely overtime
            if ($isSaturdaySegment && ! $isHolidaySegment) { // Saturday (non-holiday) is 1.5x
                $overtime1_5x += $segmentDurationHours;
                $hourRateApplied = true;
            } elseif ($isSundaySegment || $isHolidaySegment) { // Sunday or Holiday is 2x
                $overtime2_0x += $segmentDurationHours;
                $hourRateApplied = true;
            }

                                          // Rule 2: Weekday overtime (if not already covered by Sat/Sun/Holiday OT)
            if (! $hourRateApplied) {      // It's a normal weekday
                $isRegularSegmentHour = true; // Assume regular unless it's OT

                if ($shiftType === 'day') {
                    $dayShiftStandardEnd = $calendarDayOfSegment->copy()->setTime(self::DAY_SHIFT_END_HOUR, self::DAY_SHIFT_END_MINUTE);
                    // If the current minute STARTS at or after 18:00
                    if ($currentDt->greaterThanOrEqualTo($dayShiftStandardEnd)) {
                        $overtime1_5x += $segmentDurationHours;
                        $isRegularSegmentHour = false;
                    }
                } elseif ($shiftType === 'night') {
                    // Night shift OT is after 07:00 on the day the standard night shift portion ends.
                    // Standard night shift is e.g., Mon 18:00 to Tue 07:00.
                    // So, on Tue (the end day), hours from 07:00 onwards are OT.
                    $nightShiftStandardEnd = $calendarDayOfSegment->copy()->setTime(self::NIGHT_SHIFT_END_HOUR, self::NIGHT_SHIFT_END_MINUTE);
                    // Check if the current segment is on the *actual clockOutTime's* calendar day
                    if ($currentDt->isSameDay($clockOutTime) && $currentDt->greaterThanOrEqualTo($nightShiftStandardEnd) && ! $clockInTime->isSameDay($clockOutTime)) {
                        // And clockIn was not on this same day (i.e. it's a true cross-day shift ending part)
                        $overtime1_5x += $segmentDurationHours;
                        $isRegularSegmentHour = false;
                    }
                }
                // Early clock-ins on weekdays before shift start are regular time, not OT.
                // e.g. Day shift, clock-in at 06:00. 06:00-07:00 is regular.
                // e.g. Night shift, clock-in at 17:00. 17:00-18:00 is regular.
                if ($isRegularSegmentHour) {
                    $regularHours += $segmentDurationHours;
                }
            }
            $currentDt = $segmentEndDt;
        }

        // Sanity check: ensure sum of regular and OT hours matches total hours (approx)
        $calculatedTotal = $regularHours + $overtime1_5x + $overtime2_0x;
        if (abs($calculatedTotal - $totalHours) > 0.01) { // Allow for small float precision diffs
                                                              // This might indicate an issue in logic, or just float math.
                                                              // For robustness, regular_hours can be derived:
            $regularHours = $totalHours - ($overtime1_5x + $overtime2_0x);
            if ($regularHours < 0) {
                $regularHours = 0;
            }
            // Should not happen
        }

        return [
            'total_hours'   => round($totalHours, 2),
            'regular_hours' => round($regularHours, 2),
            'overtime_1_5x' => round($overtime1_5x, 2),
            'overtime_2_0x' => round($overtime2_0x, 2),
        ];
    }

    private function generateNotes(Carbon $clockInTime, Carbon $clockOutTime, string $shiftType, float $hoursWorked, bool $hasHumanError = false, int $latenessMinutes = 0, float $overtime1_5x = 0, float $overtime2_0x = 0): string
    {
        $notes   = [];
        $notes[] = "Type: " . ucfirst(str_replace('_', ' ', $shiftType));
        if ($clockInTime) {
            $notes[] = "In: {$clockInTime->toDateTimeString()}";
        }

        if ($clockOutTime) {
            $notes[] = "Out: {$clockOutTime->toDateTimeString()}";
        }

        $notes[] = "Hours: " . round($hoursWorked, 2);

        if ($latenessMinutes > 0) {
            $notes[] = "Late by {$latenessMinutes} minutes";
        }

        if ($overtime1_5x > 0) {
            $notes[] = "Overtime 1.5x: " . round($overtime1_5x, 2) . " hours";
        }

        if ($overtime2_0x > 0) {
            $notes[] = "Overtime 2.0x: " . round($overtime2_0x, 2) . " hours";
        }

        if ($hasHumanError) {
            $notes[] = "Human error detected (multiple punches >1hr apart)";
        }

        return implode('; ', $notes);
    }

    private function createShiftRecord(
        $employeePin, Carbon $shiftDate, $clockInId, $clockOutId,
        ?Carbon $clockInTime, ?Carbon $clockOutTime,
        float $hoursWorked, string $shiftType, bool $isComplete, string $notes,
        int $latenessMinutes, float $overtime1_5x, float $overtime2_0x,
        bool $isHoliday, bool $isWeekend, float $regularHours
    ) {
        EmployeeShift::create([
            'employee_pin'            => $employeePin,
            'shift_date'              => $shiftDate->toDateString(), // The accounting date for the shift
            'clock_in_attendance_id'  => $clockInId,
            'clock_out_attendance_id' => $clockOutId,
            'clock_in_time'           => $clockInTime,
            'clock_out_time'          => $clockOutTime,
            'hours_worked'            => round($hoursWorked, 2),
            'regular_hours_worked'    => round($regularHours, 2), // New field
            'shift_type'              => $shiftType,
            'is_complete'             => $isComplete,
            'notes'                   => $notes,
            'lateness_minutes'        => $latenessMinutes,
            'overtime_hours_1_5x'     => round($overtime1_5x, 2),
            'overtime_hours_2_0x'     => round($overtime2_0x, 2),
            'is_holiday'              => $isHoliday, // Based on the shift_date (accounting date)
            'is_weekend'              => $isWeekend, // Based on the shift_date (accounting date)
        ]);
    }

    protected function isHoliday(Carbon $date): bool
    {
        static $holidaysCache = []; // Simple cache for the duration of the command run
        $dateString           = $date->toDateString();

        if (isset($holidaysCache[$dateString])) {
            return $holidaysCache[$dateString];
        }

        $isActualHoliday = Holiday::whereDate('start_date', $date->toDateString()) // Date matches the holiday's start date
            ->where('description', 'LIKE', '%Public holiday%')                         // Description indicates it's a public holiday
            ->exists();

        $holidaysCache[$dateString] = $isActualHoliday; // Cache the result

        return $isActualHoliday;
    }
}
