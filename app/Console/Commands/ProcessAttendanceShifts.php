<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use App\Models\EmployeeShift;
use App\Models\Employee;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection; // Ensure Collection is imported

class ProcessAttendanceShifts extends Command
{
    protected $signature = 'process:shifts {date? : The date to process (YYYY-MM-DD)}';
    protected $description = 'Process attendance records into employee shifts with weekend/holiday overtime rules and night shift spanning';

    // Shift definitions
    const DAY_SHIFT_START_HOUR = 7;
    const DAY_SHIFT_START_MINUTE = 0;
    const DAY_SHIFT_END_HOUR = 18;
    const DAY_SHIFT_END_MINUTE = 0;

    const NIGHT_SHIFT_START_HOUR = 18;
    const NIGHT_SHIFT_START_MINUTE = 0;
    const NIGHT_SHIFT_END_HOUR = 7; // Nominal end time on the next day
    const NIGHT_SHIFT_END_MINUTE = 0;

    // How far into the next day to look for clock-outs for night shifts
    const NEXT_DAY_CLOCKOUT_LOOKAHEAD_HOUR = 10; // e.g., look up to 10:00 AM next day

    public function handle()
    {
        $targetDate = $this->argument('date') ? Carbon::parse($this->argument('date')) : Carbon::today();
        $this->info("Processing attendance shifts for: {$targetDate->toDateString()}");

        $shiftsProcessed = 0;

        DB::transaction(function () use ($targetDate, &$shiftsProcessed) {
            // Get all unique employee pins that have attendance records for the target date (clock-ins)
            // or surrounding period (clock-outs)
            $potentialPins = Attendance::whereDate('datetime', '>=', $targetDate->copy()->subDay()) // Consider pins from a slightly wider range initially
                ->whereDate('datetime', '<=', $targetDate->copy()->addDays(1))
                ->distinct()
                ->pluck('pin')
                ->map(function ($pin) {
                    if (strlen($pin) > 1) { // Basic check to avoid error on empty/short pins
                        return substr($pin, 1); // Remove first digit to get real employee pin
                    }
                    return null;
                })
                ->filter() // Remove nulls
                ->unique()
                ->values();

            if($potentialPins->isEmpty()){
                $this->info("No employee pins found with activity around {$targetDate->toDateString()}.");
                return;
            }

            $employees = Employee::whereIn('pin', $potentialPins)->get();

            if($employees->isEmpty()){
                $this->info("No employees found for the identified pins.");
                return;
            }

            foreach ($employees as $employee) {
                $this->processEmployeeShifts($employee, $targetDate, $shiftsProcessed);
            }
        });

        $this->info("Finished processing. Total shifts processed: {$shiftsProcessed}");
    }

    private function processEmployeeShifts($employee, $targetDate, &$shiftsProcessed)
    {
        $employeePin = $employee->pin;

        EmployeeShift::where('employee_pin', $employeePin)
            ->whereDate('shift_date', $targetDate->toDateString()) // Use toDateString for DB date comparison
            ->delete();

        // Fetch clock-ins for the targetDate
        $clockInsOnTargetDate = Attendance::where('pin', '1' . $employeePin)
            ->whereDate('datetime', $targetDate->toDateString())
            ->orderBy('datetime')
            ->get()
            ->map(function($record){ // Ensure datetime is Carbon
                $record->datetime = Carbon::parse($record->datetime);
                return $record;
            });

        // Fetch clock-outs from the start of targetDate up to the lookahead time on the next day
        $nextDayLookaheadEndTime = $targetDate->copy()->addDay()->setTime(self::NEXT_DAY_CLOCKOUT_LOOKAHEAD_HOUR, 0, 0);

        $allPotentialClockOuts = Attendance::where('pin', '2' . $employeePin)
            ->where('datetime', '>=', $targetDate->copy()->startOfDay())
            ->where('datetime', '<=', $nextDayLookaheadEndTime)
            ->orderBy('datetime')
            ->get()
            ->map(function($record){ // Ensure datetime is Carbon
                $record->datetime = Carbon::parse($record->datetime);
                return $record;
            });


        // Determine weekend/holiday status based on $targetDate (the primary day of the shift, usually clock-in day)
        $isWeekend = $targetDate->isWeekend();
        $isHoliday = $this->isHoliday($targetDate);
        $isSaturday = $targetDate->isSaturday();
        $isSundayOrHoliday = $targetDate->isSunday() || $isHoliday;

        $this->processShiftScenarios(
            $employeePin,
            $targetDate, // This is the reference date for the shift
            $clockInsOnTargetDate,
            $allPotentialClockOuts,
            $isWeekend,
            $isHoliday,
            $isSaturday,
            $isSundayOrHoliday,
            $shiftsProcessed
        );
    }

    private function processShiftScenarios(
        $employeePin, $shiftDate, Collection $clockInsFromTargetDay, Collection $allPotentialClockOuts,
        $isWeekend, $isHoliday, $isSaturday, $isSundayOrHoliday, &$shiftsProcessed
    ) {
        $firstClockInRecord = $clockInsFromTargetDay->first();

        if ($firstClockInRecord) {
            // $firstClockInRecord->datetime is already a Carbon instance
            $firstClockInTime = $firstClockInRecord->datetime;

            // Find the latest clock-out that is chronologically after the first clock-in
            $lastClockOutRecord = $allPotentialClockOuts
                ->filter(fn ($co) => $co->datetime->greaterThan($firstClockInTime))
                ->last();

            if ($lastClockOutRecord) {
                // We have a pair (firstClockInRecord, lastClockOutRecord).
                // For detectHumanError:
                // - Pass all clock-ins from the target day.
                // - Pass clock-outs that occurred on the same calendar day as the lastClockOutRecord.
                $clockOutsOnShiftEndDay = $allPotentialClockOuts->filter(fn ($co) =>
                    $co->datetime->isSameDay($lastClockOutRecord->datetime)
                );

                $this->processCompleteShift(
                    $employeePin, $shiftDate, $firstClockInRecord, $lastClockOutRecord,
                    $clockInsFromTargetDay, // All clock-ins from target day
                    $clockOutsOnShiftEndDay, // Clock-outs from the day the shift actually ended
                    $isWeekend, $isHoliday, $isSaturday, $isSundayOrHoliday, $shiftsProcessed
                );
            } else {
                // First clock-in exists, but no valid clock-out found (neither same day nor next day within lookahead)
                $this->processMissingClockOut($employeePin, $shiftDate, $firstClockInRecord, $isWeekend, $isHoliday, $shiftsProcessed);
            }
        } elseif ($allPotentialClockOuts->isNotEmpty()) {
            // No clock-in on $shiftDate, but some clock-outs exist in the fetched range.
            // Consider only those clock-outs that are on $shiftDate itself for "missing clock-in".
            $lastClockOutOfTargetDay = $allPotentialClockOuts
                ->filter(fn ($co) => $co->datetime->isSameDay($shiftDate))
                ->last();

            if ($lastClockOutOfTargetDay) {
                $this->processMissingClockIn($employeePin, $shiftDate, $lastClockOutOfTargetDay, $isWeekend, $isHoliday, $shiftsProcessed);
            } else {
                // No clock-ins on targetDate, and no clock-outs on targetDate either.
                $this->info("No clock-ins on {$shiftDate->toDateString()}, and no clock-outs on {$shiftDate->toDateString()} either for {$employeePin}.");
            }
        } else {
            // This case should ideally be caught earlier if both $clockInsOnTargetDate and $allPotentialClockOuts are empty.
             if($clockInsFromTargetDay->isEmpty()){ // Double check if truly no records
                $this->info("No attendance data found for {$employeePin} relevant to {$shiftDate->toDateString()}.");
             }
        }
    }

    private function processCompleteShift(
        $employeePin, $shiftDate, $firstClockIn, $lastClockOut,
        Collection $allClockInsOnTargetDate, Collection $clockOutsOnShiftEndDay, // Used for detectHumanError
        $isWeekend, $isHoliday, $isSaturday, $isSundayOrHoliday, &$shiftsProcessed
    ) {
        // $firstClockIn->datetime and $lastClockOut->datetime are Carbon instances
        $clockInTime = $firstClockIn->datetime;
        $clockOutTime = $lastClockOut->datetime;

        if ($clockOutTime->lessThanOrEqualTo($clockInTime)) {
            // Handle inverted times explicitly if they reach here
            $notes = "Inverted punch times. In: {$clockInTime->toDateTimeString()}, Out: {$clockOutTime->toDateTimeString()}";
            $this->createShiftRecord($employeePin, $shiftDate, $firstClockIn->id, $lastClockOut->id, $clockInTime, $clockOutTime, 0, 'inverted_times', false, $notes, 0, 0.0, 0.0, $isHoliday, $isWeekend);
            $this->warn("Processed inverted times for {$employeePin} on {$shiftDate->toDateString()}");
            $shiftsProcessed++;
            return;
        }

        $hasHumanError = $this->detectHumanError($allClockInsOnTargetDate, $clockOutsOnShiftEndDay);
        $hoursWorked = $clockOutTime->floatDiffInHours($clockInTime);

        $shiftTypeDetermined = 'unknown'; // Default
        if ($isWeekend || $isHoliday) {
            $shiftTypeDetermined = 'overtime_shift';
        } else {
            $shiftTypeDetermined = $this->determineShiftType($clockInTime, $clockOutTime, $shiftDate);
        }

        $latenessMinutes = $this->calculateLateness($clockInTime, $shiftTypeDetermined, $shiftDate);
        [$overtime1_5x, $overtime2_0x] = $this->calculateOvertime($hoursWorked, $clockInTime, $clockOutTime, $shiftDate, $shiftTypeDetermined, $isSaturday, $isSundayOrHoliday);
        $notes = $this->generateNotes($clockInTime, $clockOutTime, $shiftTypeDetermined, $hoursWorked, $hasHumanError, $latenessMinutes, $overtime1_5x, $overtime2_0x);

        $this->createShiftRecord(
            $employeePin, $shiftDate, $firstClockIn->id, $lastClockOut->id, $clockInTime, $clockOutTime,
            $hoursWorked, $shiftTypeDetermined, true, $notes, $latenessMinutes, $overtime1_5x, $overtime2_0x,
            $isHoliday, $isWeekend
        );
        $this->info("Processed complete shift for {$employeePin} on {$shiftDate->toDateString()}. Type: {$shiftTypeDetermined}, Hours: " . round($hoursWorked, 2));
        $shiftsProcessed++;
    }

    private function processMissingClockOut($employeePin, $shiftDate, $clockIn, $isWeekend, $isHoliday, &$shiftsProcessed)
    {
        // $clockIn->datetime is Carbon
        $shiftType = ($isWeekend || $isHoliday) ? 'overtime_shift_incomplete' : 'missing_clockout';
        $latenessMinutes = $this->calculateLateness($clockIn->datetime, $shiftType, $shiftDate);
        $notes = "Missing clock-out. Clock-in: {$clockIn->datetime->toDateTimeString()}"; // Use toDateTimeString for full info
        $this->createShiftRecord($employeePin, $shiftDate, $clockIn->id, null, $clockIn->datetime, null, 0, $shiftType, false, $notes, $latenessMinutes, 0.0, 0.0, $isHoliday, $isWeekend);
        $this->warn("Missing clock-out for employee {$employeePin} on {$shiftDate->toDateString()}");
        $shiftsProcessed++;
    }

    private function processMissingClockIn($employeePin, $shiftDate, $clockOut, $isWeekend, $isHoliday, &$shiftsProcessed)
    {
        // $clockOut->datetime is Carbon
        $shiftType = ($isWeekend || $isHoliday) ? 'overtime_shift_incomplete' : 'missing_clockin';
        $notes = "Missing clock-in. Clock-out: {$clockOut->datetime->toDateTimeString()}"; // Use toDateTimeString for full info
        $this->createShiftRecord($employeePin, $shiftDate, null, $clockOut->id, null, $clockOut->datetime, 0, $shiftType, false, $notes, 0, 0.0, 0.0, $isHoliday, $isWeekend);
        $this->warn("Missing clock-in for employee {$employeePin} on {$shiftDate->toDateString()}");
        $shiftsProcessed++;
    }

    private function detectHumanError(Collection $clockIns, Collection $clockOuts): bool
    {
        // Assumes $clockIns contains all clock-ins for the shift's start day
        // Assumes $clockOuts contains all clock-outs for the shift's end day
        if ($clockIns->count() > 1) {
            $times = $clockIns->pluck('datetime')->sort(); // datetime is already Carbon
            for ($i = 1; $i < $times->count(); $i++) {
                if ($times->get($i)->diffInHours($times->get($i - 1)) > 1) {
                    return true;
                }
            }
        }
        if ($clockOuts->count() > 1) {
            $times = $clockOuts->pluck('datetime')->sort(); // datetime is already Carbon
            for ($i = 1; $i < $times->count(); $i++) {
                if ($times->get($i)->diffInHours($times->get($i - 1)) > 1) {
                    return true;
                }
            }
        }
        return false;
    }

    private function determineShiftType(Carbon $clockInTime, Carbon $clockOutTime, Carbon $shiftDate): string
    {
        // $shiftDate is the primary date of the shift (usually clock-in day)
        // Day shift definition (07:00 - 18:00 on the same day)
        $dayShiftStart = $shiftDate->copy()->setTime(self::DAY_SHIFT_START_HOUR, self::DAY_SHIFT_START_MINUTE);
        $dayShiftEnd = $shiftDate->copy()->setTime(self::DAY_SHIFT_END_HOUR, self::DAY_SHIFT_END_MINUTE);

        // Night shift definition (e.g., 18:00 on $shiftDate to 07:00 on $shiftDate->addDay())
        $nightShiftStartOnShiftDate = $shiftDate->copy()->setTime(self::NIGHT_SHIFT_START_HOUR, self::NIGHT_SHIFT_START_MINUTE);
        // $nightShiftEndOnNextDay = $shiftDate->copy()->addDay()->setTime(self::NIGHT_SHIFT_END_HOUR, self::NIGHT_SHIFT_END_MINUTE); // Not directly used here for classification but for understanding

        if ($clockInTime->isSameDay($clockOutTime)) {
            // Shift is contained within a single calendar day
            if ($clockInTime->greaterThanOrEqualTo($dayShiftStart) && $clockOutTime->lessThanOrEqualTo($dayShiftEnd)) {
                return 'day';
            }
            // Could be an evening shift on the same day, e.g., 19:00-23:00.
            // As per your rules, if not spanning midnight, it's not a 'night' shift.
            // We can call it 'irregular_day' or just 'day' if it doesn't fit the strict 7-18 window.
            // Let's default to 'day' for same-day shifts not strictly within 7-18, or create 'irregular_day'.
            // For now, if it's same day and not 7-18, let's call it 'day' (as it's not 'night').
            return 'day'; // Or 'irregular_sameday' if you need more granularity
        } else {
            // Shift spans midnight (clockInTime and clockOutTime are on different days)
            // Check if it conforms to the night shift pattern (e.g., starts >= 18:00 on clock-in day)
            if ($clockInTime->greaterThanOrEqualTo($nightShiftStartOnShiftDate)) {
                 // And typically ends before NIGHT_SHIFT_END_HOUR on the next day.
                 // The check !$clockInTime->isSameDay($clockOutTime) is the primary indicator.
                return 'night';
            }
            // If it spans midnight but doesn't start late enough on day 1, it's an irregular cross-day shift.
            return 'irregular_crossday'; // Or handle as per specific business rules
        }
    }

    private function calculateLateness(Carbon $clockInTime, string $shiftType, Carbon $shiftDate): int
    {
        if ($isWeekend = $shiftDate->isWeekend() || $this->isHoliday($shiftDate)) { // Recalculate for context
             return 0; // No lateness on weekends/holidays
        }

        $expectedStart = null;
        if ($shiftType === 'day') {
            $expectedStart = $shiftDate->copy()->setTime(self::DAY_SHIFT_START_HOUR, self::DAY_SHIFT_START_MINUTE);
        } elseif ($shiftType === 'night') {
            // Night shift's expected start is on its clock-in day ($shiftDate)
            $expectedStart = $shiftDate->copy()->setTime(self::NIGHT_SHIFT_START_HOUR, self::NIGHT_SHIFT_START_MINUTE);
        } else {
            return 0; // No lateness for overtime_shifts, incomplete, or irregular shifts
        }

        return $clockInTime->greaterThan($expectedStart) ? $clockInTime->diffInMinutes($expectedStart) : 0;
    }

    private function calculateOvertime(
        float $hoursWorked, Carbon $clockInTime, Carbon $clockOutTime, Carbon $shiftDate,
        string $shiftType, bool $isSaturday, bool $isSundayOrHoliday
    ): array {
        $overtime1_5x = 0.0;
        $overtime2_0x = 0.0;

        if ($isSaturday) {
            $overtime1_5x = $hoursWorked;
        } elseif ($isSundayOrHoliday) {
            $overtime2_0x = $hoursWorked;
        } else { // Weekday overtime
            if ($shiftType === 'day') {
                $overtimeCutoff = $shiftDate->copy()->setTime(self::DAY_SHIFT_END_HOUR, self::DAY_SHIFT_END_MINUTE);
                if ($clockOutTime->isSameDay($shiftDate) && $clockOutTime->greaterThan($overtimeCutoff)) {
                    $overtime1_5x = $clockOutTime->floatDiffInHours($overtimeCutoff);
                }
            } elseif ($shiftType === 'night') {
                // Night shift overtime is for hours worked after NIGHT_SHIFT_END_HOUR on the *clock-out day*.
                $overtimeCutoff = $clockOutTime->copy()->startOfDay()->setTime(self::NIGHT_SHIFT_END_HOUR, self::NIGHT_SHIFT_END_MINUTE);
                if ($clockOutTime->greaterThan($overtimeCutoff)) { // $clockOutTime itself is on the next day
                    $overtime1_5x = $clockOutTime->floatDiffInHours($overtimeCutoff);
                }
            }
            // No specific overtime for 'irregular_weekday' or 'irregular_crossday' based on current rules,
            // beyond what might be covered by daily/weekly total hours rules (not implemented here).
        }
        return [max(0.0, $overtime1_5x), max(0.0, $overtime2_0x)];
    }

    private function generateNotes(
        Carbon $clockInTime, Carbon $clockOutTime, string $shiftType, float $hoursWorked,
        bool $hasHumanError = false, int $latenessMinutes = 0, float $overtime1_5x = 0, float $overtime2_0x = 0
    ): string {
        $notes = [];
        $notes[] = "Type: " . ucfirst(str_replace('_', ' ', $shiftType)); // More readable shift type
        if ($clockInTime) $notes[] = "In: {$clockInTime->toDateTimeString()}"; // Full datetime
        if ($clockOutTime) $notes[] = "Out: {$clockOutTime->toDateTimeString()}"; // Full datetime
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
            $notes[] = "Human error detected (multiple punches >1hr apart on start/end day)";
        }
        return implode('; ', $notes); // Use semicolon and space for better readability
    }

    private function createShiftRecord(
        $employeePin, Carbon $shiftDate, $clockInId, $clockOutId,
        ?Carbon $clockInTime, ?Carbon $clockOutTime, float $hoursWorked,
        string $shiftType, bool $isComplete, string $notes, int $latenessMinutes,
        float $overtime1_5x, float $overtime2_0x, bool $isHoliday, bool $isWeekend
    ) {
        EmployeeShift::create([
            'employee_pin' => $employeePin,
            'shift_date' => $shiftDate->toDateString(), // The reference date of the shift
            'clock_in_attendance_id' => $clockInId,
            'clock_out_attendance_id' => $clockOutId,
            'clock_in_time' => $clockInTime,
            'clock_out_time' => $clockOutTime,
            'hours_worked' => round($hoursWorked, 2),
            'shift_type' => $shiftType,
            'is_complete' => $isComplete,
            'notes' => $notes,
            'lateness_minutes' => $latenessMinutes,
            'overtime_hours_1_5x' => round($overtime1_5x, 2),
            'overtime_hours_2_0x' => round($overtime2_0x, 2),
            'is_holiday' => $isHoliday,
            'is_weekend' => $isWeekend,
        ]);
    }

    protected function isHoliday(Carbon $date): bool
    {
        return Holiday::whereDate('start_date', $date->toDateString()) // Ensure date-only comparison
            ->where('description', 'LIKE', '%Public holiday%')
            ->exists();
    }
}
