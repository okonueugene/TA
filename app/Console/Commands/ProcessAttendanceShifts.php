<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use App\Models\EmployeeShift;
use App\Models\Employee;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ProcessAttendanceShifts extends Command
{
    protected $signature = 'process:shifts {date? : The date to process (YYYY-MM-DD). Shifts are recorded under this date.}';
    protected $description = 'Processes attendance, associating shifts with the given date (as start or end for night shifts).';

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

    public function handle()
    {
        $targetDate = $this->argument('date') ? Carbon::parse($this->argument('date'))->startOfDay() : Carbon::today()->startOfDay();
        $this->info("Processing attendance shifts to be recorded under date: {$targetDate->toDateString()}");
        $shiftsProcessed = 0;

        DB::transaction(function () use ($targetDate, &$shiftsProcessed) {
            $previousDay = $targetDate->copy()->subDay();
            $nextDay = $targetDate->copy()->addDay();

            $activityStartDate = $targetDate->copy()->subDays(1)->startOfDay(); // Fetch from previous day start
            $activityEndDate = $targetDate->copy()->addDays(1)->endOfDay();   // Fetch till next day end

            $potentialPins = Attendance::whereBetween('datetime', [$activityStartDate, $activityEndDate])
                ->distinct()
                ->pluck('pin')
                ->map(function ($pin) {
                    return strlen($pin) > 1 ? substr($pin, 1) : null;
                })
                ->filter()->unique()->values();

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
        $this->info("Finished processing. Total shifts processed: {$shiftsProcessed}");
    }

    private function processEmployeeShifts(Employee $employee, Carbon $targetDate, Carbon $previousDay, Carbon $nextDay, &$shiftsProcessed)
    {
        $employeePin = $employee->pin;
        $usedAttendanceIds = new Collection(); // Initialize here for each employee

        EmployeeShift::where('employee_pin', $employeePin)
            ->whereDate('shift_date', $targetDate->toDateString())
            ->delete();

        // Fetch all potentially relevant punches ONCE for this employee
        $allEmployeePunches = Attendance::where(function($q) use ($employeePin) {
                $q->where('pin', '1'.$employeePin)
                  ->orWhere('pin', '2'.$employeePin);
            })
            ->whereDate('datetime', '>=', $previousDay->toDateString())
            ->whereDate('datetime', '<=', $nextDay->toDateString())
            ->orderBy('datetime')->get()->map(function ($record) {
                $record->datetime = Carbon::parse($record->datetime); // Ensure Carbon
                return $record;
            });

        // --- 1. Process Night Shifts: Started Previous Day, Ending on TargetDate ---
        $this->processNightShiftEndingOnTargetDay(
            $employee, $targetDate, $previousDay, $allEmployeePunches, $usedAttendanceIds, $shiftsProcessed
        );

        // --- 2. Process Shifts Starting on TargetDate ---
        $this->processShiftsStartingOnTargetDay(
            $employee, $targetDate, $nextDay, $allEmployeePunches, $usedAttendanceIds, $shiftsProcessed
        );
        
        // --- 3. Process any remaining isolated punches on TargetDate ---
        $this->processIsolatedPunchesOnTargetDate(
            $employee, $targetDate, $allEmployeePunches, $usedAttendanceIds, $shiftsProcessed
        );
    }

    private function getPunches(Collection $allPunches, Carbon $day, ?string $type, Collection $usedAttendanceIds) {
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
            $matchingTargetDayNightOut = $targetDayEarlyClockOuts
                ->filter(fn ($co) => $co->datetime->greaterThan($firstPrevDayNightIn->datetime))
                ->sortBy('datetime')->first();

            if ($firstPrevDayNightIn && $matchingTargetDayNightOut) {
                $this->processCompleteShiftWrapper(
                    $employee, $previousDay,
                    $firstPrevDayNightIn, $matchingTargetDayNightOut,
                    $allEmployeePunches, // Pass all for context if needed by human error
                    $shiftsProcessed, true // isPrevDayNightShift = true
                );
                $usedAttendanceIds->push($firstPrevDayNightIn->id);
                $usedAttendanceIds->push($matchingTargetDayNightOut->id);
            }
        }
    }

    private function processShiftsStartingOnTargetDay(Employee $employee, Carbon $targetDate, Carbon $nextDay, Collection $allEmployeePunches, Collection &$usedAttendanceIds, &$shiftsProcessed)
    {
        $employeePin = $employee->pin;
        $targetDayClockIns = $this->getPunches($allEmployeePunches, $targetDate, 'in', $usedAttendanceIds);
        $firstTargetDayIn = $targetDayClockIns->sortBy('datetime')->first();

        if ($firstTargetDayIn) {
            $potentialClockOutsForTargetStart = new Collection();
            // Outs on target day (after firstTargetDayIn)
            $this->getPunches($allEmployeePunches, $targetDate, 'out', $usedAttendanceIds)
                ->filter(fn($co) => $co->datetime->greaterThan($firstTargetDayIn->datetime))
                ->each(fn($co) => $potentialClockOutsForTargetStart->push($co));

            // Outs on next day (within lookahead)
            $this->getPunches($allEmployeePunches, $nextDay, 'out', $usedAttendanceIds)
                ->filter(fn($co) => $co->datetime->hour < self::NEXT_DAY_CLOCKOUT_LOOKAHEAD_HOUR)
                ->each(fn($co) => $potentialClockOutsForTargetStart->push($co));

            $lastMatchingOut = $potentialClockOutsForTargetStart->sortByDesc('datetime')->first();

            if ($lastMatchingOut) {
                 $this->processCompleteShiftWrapper(
                    $employee, $targetDate, // Shift recorded under $targetDate
                    $firstTargetDayIn, $lastMatchingOut,
                    $allEmployeePunches,
                    $shiftsProcessed, false // isPrevDayNightShift = false
                );
                $usedAttendanceIds->push($firstTargetDayIn->id);
                $usedAttendanceIds->push($lastMatchingOut->id);
            } else {
                // Missing clock-out for shift starting on targetDate
                $isWeekend = $targetDate->isWeekend(); $isHoliday = $this->isHoliday($targetDate);
                $this->processMissingClockOut($employeePin, $targetDate, $firstTargetDayIn, $isWeekend, $isHoliday, $shiftsProcessed);
                $usedAttendanceIds->push($firstTargetDayIn->id);
            }
        }
    }
    
    private function processIsolatedPunchesOnTargetDate(Employee $employee, Carbon $targetDate, Collection $allEmployeePunches, Collection &$usedAttendanceIds, &$shiftsProcessed){
        $employeePin = $employee->pin;
        // Check for isolated clock-ins on targetDate
        $isolatedTargetDayIn = $this->getPunches($allEmployeePunches, $targetDate, 'in', $usedAttendanceIds)
                                    ->sortBy('datetime')->first();
        if($isolatedTargetDayIn){
            $isWeekend = $targetDate->isWeekend(); $isHoliday = $this->isHoliday($targetDate);
            $this->processMissingClockOut($employeePin, $targetDate, $isolatedTargetDayIn, $isWeekend, $isHoliday, $shiftsProcessed);
            $usedAttendanceIds->push($isolatedTargetDayIn->id);
        }

        // Check for isolated clock-outs on targetDate
        $isolatedTargetDayOut = $this->getPunches($allEmployeePunches, $targetDate, 'out', $usedAttendanceIds)
                                     ->sortByDesc('datetime')->first(); // Get the last one if multiple
        if($isolatedTargetDayOut){
            $isWeekend = $targetDate->isWeekend(); $isHoliday = $this->isHoliday($targetDate);
            $this->processMissingClockIn($employeePin, $targetDate, $isolatedTargetDayOut, $isWeekend, $isHoliday, $shiftsProcessed);
            $usedAttendanceIds->push($isolatedTargetDayOut->id);
        }
    }


    private function processCompleteShiftWrapper(
        Employee $employee, Carbon $shiftRecordDate,
        $clockInRecord, $clockOutRecord,
        Collection $allEmployeePunches, // Pass all for human error detection context
        &$shiftsProcessed, bool $isPrevDayNightShift
    ) {
        $shiftActualStartDate = $clockInRecord->datetime->copy()->startOfDay();
        $isWeekend = $shiftActualStartDate->isWeekend();
        $isHoliday = $this->isHoliday($shiftActualStartDate);
        $isSaturday = $shiftActualStartDate->isSaturday();
        $isSundayOrHoliday = $shiftActualStartDate->isSunday() || $isHoliday;

        // For human error, we need punches specific to the days the shift occurred on
        $clockInsForErrorCheck = $this->getPunches($allEmployeePunches, $clockInRecord->datetime->copy()->startOfDay(), 'in', new Collection()); // Fresh get, no used IDs for error check context
        $clockOutsForErrorCheck = $this->getPunches($allEmployeePunches, $clockOutRecord->datetime->copy()->startOfDay(), 'out', new Collection());


        $this->processCompleteShift(
            $employee->pin, $shiftRecordDate, $clockInRecord, $clockOutRecord,
            $clockInsForErrorCheck, $clockOutsForErrorCheck,
            $isWeekend, $isHoliday, $isSaturday, $isSundayOrHoliday, $shiftsProcessed,
            $isPrevDayNightShift, $shiftActualStartDate
        );
    }

    private function processCompleteShift(
        $employeePin, Carbon $shiftRecordDate, $firstClockIn, $lastClockOut,
        Collection $relevantClockInsForError, Collection $relevantClockOutsForError,
        $isWeekendBasedOnActualStart, $isHolidayBasedOnActualStart, $isSaturdayBasedOnActualStart, $isSundayOrHolidayBasedOnActualStart,
        &$shiftsProcessed, bool $isPrevDayNightShift, Carbon $shiftActualStartDate
    ) {
        $clockInTime = $firstClockIn->datetime;
        $clockOutTime = $lastClockOut->datetime;

        if ($clockOutTime->lessThanOrEqualTo($clockInTime)) {
            $notes = "Inverted punch times. In: {$clockInTime->toDateTimeString()}, Out: {$clockOutTime->toDateTimeString()}";
            $this->createShiftRecord($employeePin, $shiftRecordDate, $firstClockIn->id, $lastClockOut->id, $clockInTime, $clockOutTime, 0, 'inverted_times', false, $notes, 0, 0.0, 0.0, $this->isHoliday($shiftRecordDate), $shiftRecordDate->isWeekend(),0);
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
        [$overtime1_5x, $overtime2_0x, $regularHours] = $this->calculateOvertimeAndHours($hoursWorked, $clockInTime, $clockOutTime, $shiftActualStartDate, $shiftTypeDetermined, $isSaturdayBasedOnActualStart, $isSundayOrHolidayBasedOnActualStart);
        $notes = $this->generateNotes($clockInTime, $clockOutTime, $shiftTypeDetermined, $hoursWorked, $hasHumanError, $latenessMinutes, $overtime1_5x, $overtime2_0x);

        $this->createShiftRecord(
            $employeePin, $shiftRecordDate, $firstClockIn->id, $lastClockOut->id, $clockInTime, $clockOutTime,
            $hoursWorked, $shiftTypeDetermined, true, $notes, $latenessMinutes, $overtime1_5x, $overtime2_0x,
            $this->isHoliday($shiftRecordDate), $shiftRecordDate->isWeekend(), $regularHours
        );
        $this->info("Processed complete shift for {$employeePin}, recorded on {$shiftRecordDate->toDateString()}. Type: {$shiftTypeDetermined}, Hours: " . round($hoursWorked, 2) . ", Regular: " . round($regularHours,2) . ", OT1.5: ".round($overtime1_5x,2).", OT2.0: ".round($overtime2_0x,2) );
        $shiftsProcessed++;
    }

    private function processMissingClockOut($employeePin, Carbon $shiftDate, $clockIn, $isWeekend, $isHoliday, &$shiftsProcessed)
    {
        $shiftType = '';
        if($isWeekend && $this->isHoliday($shiftDate)) $shiftType = 'holiday_incomplete'; // Holiday on weekend
        elseif($isWeekend) $shiftType = 'weekend_incomplete';
        elseif($this->isHoliday($shiftDate)) $shiftType = 'holiday_incomplete';
        else $shiftType = 'missing_clockout';

        $latenessMinutes = $this->calculateLateness($clockIn->datetime, 'day', $clockIn->datetime->copy()->startOfDay(), false);
        $notes = "Missing clock-out. Clock-in: {$clockIn->datetime->toDateTimeString()}";
        $this->createShiftRecord($employeePin, $shiftDate, $clockIn->id, null, $clockIn->datetime, null, 0, $shiftType, false, $notes, $latenessMinutes, 0.0, 0.0, $isHoliday, $isWeekend,0);
        $this->warn("Missing clock-out for employee {$employeePin} on {$shiftDate->toDateString()}");
        $shiftsProcessed++;
    }

    private function processMissingClockIn($employeePin, Carbon $shiftDate, $clockOut, $isWeekend, $isHoliday, &$shiftsProcessed)
    {
        $shiftType = '';
        if($isWeekend && $this->isHoliday($shiftDate)) $shiftType = 'holiday_incomplete';
        elseif($isWeekend) $shiftType = 'weekend_incomplete';
        elseif($this->isHoliday($shiftDate)) $shiftType = 'holiday_incomplete';
        else $shiftType = 'missing_clockin';

        $notes = "Missing clock-in. Clock-out: {$clockOut->datetime->toDateTimeString()}";
        $this->createShiftRecord($employeePin, $shiftDate, null, $clockOut->id, null, $clockOut->datetime, 0, $shiftType, false, $notes, 0, 0.0, 0.0, $isHoliday, $isWeekend,0);
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
                if ($times->get($i)->diffInHours($times->get($i - 1)) > 1) return true;
            }
        }
        // Filter to punches on the specific day of the shift's clock-out
        $relevantClockOuts = $allClockOutsOnDayOfOut->filter(fn ($p) => $p->datetime->isSameDay($shiftActualOut));
        if ($relevantClockOuts->count() > 1) {
            $times = $relevantClockOuts->pluck('datetime')->sort();
            for ($i = 1; $i < $times->count(); $i++) {
                if ($times->get($i)->diffInHours($times->get($i - 1)) > 1) return true;
            }
        }
        return false;
    }

    private function determineShiftType(Carbon $clockInTime, Carbon $clockOutTime, Carbon $shiftActualStartDate): string
    {
        $dayShiftStart = $shiftActualStartDate->copy()->setTime(self::DAY_SHIFT_START_HOUR, self::DAY_SHIFT_START_MINUTE);
        $dayShiftEnd = $shiftActualStartDate->copy()->setTime(self::DAY_SHIFT_END_HOUR, self::DAY_SHIFT_END_MINUTE);
        $nightShiftStartOnActualDate = $shiftActualStartDate->copy()->setTime(self::NIGHT_SHIFT_START_HOUR, self::NIGHT_SHIFT_START_MINUTE);

        if ($clockInTime->isSameDay($clockOutTime)) {
            if ($clockInTime->isSameDay($shiftActualStartDate) && // Ensure it's on the actual start day
                $clockInTime->greaterThanOrEqualTo($dayShiftStart) &&
                $clockOutTime->lessThanOrEqualTo($dayShiftEnd)) {
                return 'day';
            }
            return 'irregular_sameday';
        } else { // Spans midnight
            if ($clockInTime->isSameDay($shiftActualStartDate) &&
                $clockInTime->greaterThanOrEqualTo($nightShiftStartOnActualDate) &&
                $clockOutTime->isSameDay($shiftActualStartDate->copy()->addDay()) && // Ends on the next calendar day
                $clockOutTime->hour < self::NIGHT_SHIFT_END_HOUR + 4 ) { // NIGHT_SHIFT_END_HOUR + buffer for overtime
                 return 'night';
            }
            // This case is for prev-day night shifts ending on target, already typed as 'night' before calling this.
            // If called for a prev-day start, $shiftActualStartDate is that prev day.
            if (!$clockInTime->isSameDay($shiftActualStartDate) && $clockInTime->hour >= self::PREV_DAY_NIGHT_IN_AFTER_HOUR &&
                 $clockOutTime->isSameDay($shiftActualStartDate->copy()->addDay()) && $clockOutTime->hour < self::TARGET_DAY_NIGHT_OUT_BEFORE_HOUR) {
                return 'night'; // This logic is mostly handled before calling determineShiftType for prev day starts
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

    private function calculateOvertimeAndHours(
        float $totalHoursWorkedInitially, Carbon $clockInTime, Carbon $clockOutTime, Carbon $shiftActualStartDate,
        string $shiftType, bool $isSaturday, bool $isSundayOrHoliday
    ): array {
        if ($totalHoursWorkedInitially <= 0) {
            return [0.0, 0.0, 0.0, 0.0]; // total, regular, ot1.5, ot2.0
        }
        $overtime1_5x = 0.0; $overtime2_0x = 0.0; $regularHours = 0.0;

        $currentDt = $clockInTime->copy();
        while ($currentDt < $clockOutTime) {
            $segmentEndDt = $currentDt->copy()->addMinute();
            if ($segmentEndDt > $clockOutTime) $segmentEndDt = $clockOutTime->copy();
            
            $segmentDurationHours = $segmentEndDt->diffInSeconds($currentDt) / 3600.0;
            if ($segmentDurationHours <= 0) { $currentDt = $segmentEndDt; continue; }

            $calendarDayOfSegment = $currentDt->copy()->startOfDay();
            $isHolidaySegment = $this->isHoliday($calendarDayOfSegment);
            // Use $isSaturday and $isSundayOrHoliday passed in, which are based on $shiftActualStartDate for the primary rate
            // but check segment's day for applying specific day's holiday status.
            
            $hourRateAppliedToOvertime = false;

            if ($calendarDayOfSegment->isSaturday() && !$isHolidaySegment) { // Actual Saturday
                $overtime1_5x += $segmentDurationHours; $hourRateAppliedToOvertime = true;
            } elseif ($calendarDayOfSegment->isSunday() || $isHolidaySegment) { // Actual Sunday or Holiday
                $overtime2_0x += $segmentDurationHours; $hourRateAppliedToOvertime = true;
            }

            if (!$hourRateAppliedToOvertime) { // Weekday segment
                $isRegularSegmentHour = true;
                if ($shiftType === 'day' && $calendarDayOfSegment->isSameDay($shiftActualStartDate)) {
                    $dayShiftStandardEnd = $shiftActualStartDate->copy()->setTime(self::DAY_SHIFT_END_HOUR, self::DAY_SHIFT_END_MINUTE);
                    if ($currentDt->greaterThanOrEqualTo($dayShiftStandardEnd)) {
                        $overtime1_5x += $segmentDurationHours; $isRegularSegmentHour = false;
                    }
                } elseif ($shiftType === 'night') {
                    // Night shift OT is after 07:00 on the day the standard night shift portion ends.
                    // The standard night shift portion ends on $shiftActualStartDate->addDay()
                    $nightShiftStandardEndDay = $shiftActualStartDate->copy()->addDay();
                    $nightShiftStandardEndTime = $nightShiftStandardEndDay->copy()->setTime(self::NIGHT_SHIFT_END_HOUR, self::NIGHT_SHIFT_END_MINUTE);
                    
                    if ($calendarDayOfSegment->isSameDay($nightShiftStandardEndDay) && $currentDt->greaterThanOrEqualTo($nightShiftStandardEndTime) ) {
                        $overtime1_5x += $segmentDurationHours; $isRegularSegmentHour = false;
                    }
                }
                if ($isRegularSegmentHour) $regularHours += $segmentDurationHours;
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
        if ($regularHours < 0) $regularHours = 0;


        return [
            round($totalHoursWorkedInitially, 2), // total
            round($regularHours, 2),             // regular
            round($overtime1_5x, 2),             // ot1.5
            round($overtime2_0x, 2)              // ot2.0
        ];
    }


    private function generateNotes( Carbon $clockInTime, Carbon $clockOutTime, string $shiftType, float $hoursWorked, bool $hasHumanError = false, int $latenessMinutes = 0, float $overtime1_5x = 0, float $overtime2_0x = 0): string
    {
        $notes = [];
        $notes[] = "Type: " . ucfirst(str_replace('_', ' ', $shiftType));
        if ($clockInTime) $notes[] = "In: {$clockInTime->toDateTimeString()}";
        if ($clockOutTime) $notes[] = "Out: {$clockOutTime->toDateTimeString()}";
        $notes[] = "Hours: " . round($hoursWorked, 2);
        if ($latenessMinutes > 0) $notes[] = "Late: {$latenessMinutes} min";
        if ($overtime1_5x > 0) $notes[] = "OT 1.5x: " . round($overtime1_5x, 2) . " hrs";
        if ($overtime2_0x > 0) $notes[] = "OT 2.0x: " . round($overtime2_0x, 2) . " hrs";
        if ($hasHumanError) $notes[] = "Human error flagged";
        return implode('; ', $notes);
    }

    private function createShiftRecord(
        $employeePin, Carbon $shiftRecordDate, $clockInId, $clockOutId,
        ?Carbon $clockInTime, ?Carbon $clockOutTime, float $hoursWorked,
        string $shiftType, bool $isComplete, string $notes, int $latenessMinutes,
        float $overtime1_5x, float $overtime2_0x, bool $isHolidayOnRecordDate, bool $isWeekendOnRecordDate,
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

    protected function isHoliday(Carbon $date): bool
    {
        static $holidaysCache = [];
        $dateString = $date->toDateString();
        if (isset($holidaysCache[$dateString])) {
            return $holidaysCache[$dateString];
        }
        $isActualHoliday = Holiday::whereDate('start_date', $date->toDateString())
            ->where('description', 'LIKE', '%Public holiday%')
            ->exists();
        $holidaysCache[$dateString] = $isActualHoliday;
        return $isActualHoliday;
    }
}

