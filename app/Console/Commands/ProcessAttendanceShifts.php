<?php
namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\EmployeeShift;
use App\Models\Holiday;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProcessAttendanceShifts extends Command
{
    protected $signature   = 'process:shifts {date? : The date to process (YYYY-MM-DD)}';
    protected $description = 'Process attendance records into employee shifts based on calculated shift days, handling night shifts and common errors, calculating overtime and identifying holidays.';

                                 // Define the hour that marks the cutoff for a shift day (e.g., 4AM).
                                 // Attendance records before this hour on a calendar day are considered part of the previous shift day.
                                 // Adjust this hour based on when your night shifts typically end.
    const SHIFT_CUTOFF_HOUR = 4; // Example: 4:00 AM cutoff

                                                // Define the lookahead hour for missing clock-outs for potential standard night shifts.
                                                // If a clock-in is found without a clock-out within the calculated shift day,
                                                // look for a clock-out on the next calendar day up to this hour.
    const MISSING_CLOCKOUT_LOOKAHEAD_HOUR = 10; // Look up to 10 AM on the next calendar day

                                              // Define the time windows for the specific night shift pattern (PreviousDay In, CurrentDay Out)
    const SPECIFIC_NIGHT_IN_START_HOUR  = 16; // 4 PM
    const SPECIFIC_NIGHT_IN_END_HOUR    = 19; // 7 PM
    const SPECIFIC_NIGHT_OUT_START_HOUR = 4;  // 4 AM
    const SPECIFIC_NIGHT_OUT_END_HOUR   = 9;  // 9 AM

    // --- Shift Definitions ---
    // Day shift standard period: 07:00 to 18:00 (on the same calendar day)
    const DAY_SHIFT_STANDARD_START_HOUR   = 7;
    const DAY_SHIFT_STANDARD_START_MINUTE = 0;
    const DAY_SHIFT_STANDARD_END_HOUR     = 18;
    const DAY_SHIFT_STANDARD_END_MINUTE   = 0;

    // Night shift standard period: 18:00 (on calculated shift day) to 07:00 (next calendar day)
    const NIGHT_SHIFT_STANDARD_START_HOUR   = 18;
    const NIGHT_SHIFT_STANDARD_START_MINUTE = 0;
    const NIGHT_SHIFT_STANDARD_END_HOUR     = 7; // Next day
    const NIGHT_SHIFT_STANDARD_END_MINUTE   = 0;

    // --- Overtime Cutoff Times (based on shift end) ---
    // Overtime for day shift starts after 18:00 (on the same calendar day as shift_date)
    const OVERTIME_DAY_SHIFT_CUTOFF_HOUR   = 18;
    const OVERTIME_DAY_SHIFT_CUTOFF_MINUTE = 0;

    // Overtime for night shift starts after 07:00 (on the calendar day the shift *ends*)
    const OVERTIME_NIGHT_SHIFT_CUTOFF_HOUR   = 7;
    const OVERTIME_NIGHT_SHIFT_CUTOFF_MINUTE = 0;

    public function handle()
    {
        // Determine the target date for processing shifts.
        // The command will calculate and process shifts whose *calculated shift day*
        // fall on this target date.
        $targetDate = $this->argument('date')
        ? Carbon::parse($this->argument('date'))->startOfDay()
        : now()->subDay()->startOfDay(); // Default to the calculated shift day of yesterday's punches

        $this->info("Processing shifts for calculated shift day: {$targetDate->toDateString()}");

        // Define the date range for fetching raw attendance records.
        // We need a range wide enough to reliably capture all punches
        // belonging to shifts whose *calculated shift day* is the target date.
        // This includes potential clock-ins from the day before the target shift day
        // (especially for the specific night shift pattern) and potential clock-outs
        // on the day after the target shift day (up to the cutoff + lookahead).

                                                                                                                                   // To capture the specific night shift pattern (In previous day 16-19, Out current day 04-09),
                                                                                                                                   // the fetch start needs to go back far enough to get the previous day's check-ins.
                                                                                                                                   // The targetDate is a calculated shift day. The calendar day associated with the start
                                                                                                                                   // of a shift on this calculated shift day could be this date or the day before.
                                                                                                                                   // Let's fetch from the beginning of the calendar day BEFORE the calculated shift day,
                                                                                                                                   // and end at the lookahead hour on the calendar day AFTER the calculated shift day.
        $fetchStart = $targetDate->copy()->subDay()->startOfDay();                                                                 // e.g., For target shift day 2025-05-01, fetch from 2025-04-30 00:00:00
        $fetchEnd   = $targetDate->copy()->addDay()->startOfDay()->addHours(self::MISSING_CLOCKOUT_LOOKAHEAD_HOUR)->endOfMinute(); // e.g., For target shift day 2025-05-01, fetch up to 2025-05-02 10:00:59

        $this->info("Fetching attendance records between {$fetchStart->toDateTimeString()} and {$fetchEnd->toDateTimeString()}");

        // Fetch attendance records within the defined range, filtered by relevant pin prefixes ('1%'/'2%'), and ordered chronologically.
        $attendances = Attendance::whereBetween('datetime', [$fetchStart, $fetchEnd])
            ->where(function ($query) {
                // Filter for clock-in or clock-out pins
                $query->where('pin', 'like', '1%')
                    ->orWhere('pin', 'like', '2%');
            })
            ->orderBy('datetime') // Order by time is crucial for accurate pairing within groups
            ->get();

        $this->info("Fetched {$attendances->count()} relevant attendance records in the range.");

        // Group the fetched attendance records first by employee's real pin, and then by their calculated shift day.
        // The result is a Collection where keys are employee pins, and values are Collections
        // further grouped by calculated shift day.
        $groupedByEmployeeAndShiftDay = $attendances->groupBy(function ($record) {
            // Group by employee's real_pin first
            // Ensure the Attendance model has the getRealPinAttribute accessor defined:
            // public function getRealPinAttribute() { return substr($this->pin, 1); }
            return $record->real_pin;
        })->map(function ($employeeRecords) {
            // Then group each employee's records by their calculated shift day
            return $employeeRecords->groupBy(function ($record) {
                return $this->getShiftDay($record->datetime)->toDateString(); // Group by Carbon date string
            });
        });

        $shiftsProcessed = 0;

        // Use a database transaction for atomic operations when creating/updatingshifts
        DB::transaction(function () use ($groupedByEmployeeAndShiftDay, $targetDate, &$shiftsProcessed) {
            // Iterate through each employee and their attendance grouped by shift day
            foreach ($groupedByEmployeeAndShiftDay as $employeePin => $shiftsForEmployee) {
                // For this command run, we only process the shift day that matches the target date
                if (! isset($shiftsForEmployee[$targetDate->toDateString()])) {
                    // This employee did not have any attendance records whose calculated shift day
                    // falls on the target date. Skip them for this date's processing.
                    continue;
                }

                // Get the attendance records specifically for the target calculated shift day for this employee
                // This collection will be modified to exclude specific night shift ends
                $recordsForTargetShiftDay = $shiftsForEmployee[$targetDate->toDateString()];
                $shiftDayDate             = $targetDate->copy()->startOfDay(); // The calculated shift day date

                $this->info("Processing calculated shift day {$shiftDayDate->toDateString()} for employee {$employeePin}");

                // --- Delete existing shifts for this calculated shift day and employee ---
                // This is crucial for idempotency: clear out old processed results for this shift day before creating new ones.
                EmployeeShift::where('employee_pin', $employeePin)
                    ->where('shift_date', $shiftDayDate->toDateString()) // Delete based on the calculated shift day
                    ->delete();
                $this->info("Deleted existing shift records for {$employeePin} on {$shiftDayDate->toDateString()}.");

                                                         // --- Identify and Exclude Specific Night Shift Ends for the *Previous* Day's Shift ---
                                                         // These are clock-outs for the CURRENT calculated shift day whose *calendar time*
                                                         // fits the specific night shift end window (04:00-09:00) and have a matching
                                                         // clock-in in the previous calendar day's specific window (16:00-19:00).
                $punchesToExcludeIds = new Collection(); // Collect IDs of punches to exclude

                // Find early morning clock-outs for the current calculated shift day
                $potentialPreviousNightShiftEnds = $recordsForTargetShiftDay->filter(function ($record) use ($shiftDayDate) {
                    // Check if it's a clock-out punch ('2%')
                    // And if its calendar time is between 04:00 and 09:00 on the calculated shift day's calendar date
                    return str_starts_with($record->pin, '2')
                    && $record->datetime->copy()->isSameDay($shiftDayDate) // Check against the target calculated shift day's date
                    && $record->datetime->copy()->hour >= self::SPECIFIC_NIGHT_OUT_START_HOUR
                    && $record->datetime->copy()->hour < self::SPECIFIC_NIGHT_OUT_END_HOUR;
                });

                foreach ($potentialPreviousNightShiftEnds as $clockOutRecord) {
                                                                                                                                    // For each potential night shift end, look for a matching clock-in
                                                                                                                                    // on the *previous calendar day* within the specific time window.
                                                                                                                                    // Define the look-back window for the potential clock-in on the previous calendar day
                    $previousCalendarDay = $shiftDayDate->copy()->subDay()->startOfDay();                                           // The calendar day before the current calculated shift day
                    $lookbackStart       = $previousCalendarDay->copy()->addHours(self::SPECIFIC_NIGHT_IN_START_HOUR);              // Previous day 16:00
                    $lookbackEnd         = $previousCalendarDay->copy()->addHours(self::SPECIFIC_NIGHT_IN_END_HOUR)->endOfMinute(); // Previous day 19:00

                    // Look for a matching clock-in for this employee in the look-back window
                    // We need to search the entire attendance table for this, as the clock-in might
                    // not be in the current $recordsForTargetShiftDay collection (since its calculated shift day is the previous one).
                    $matchingClockIn = Attendance::where('pin', '1' . $employeePin)
                        ->whereBetween('datetime', [$lookbackStart, $lookbackEnd])
                        ->orderBy('datetime') // Get the earliest such checkin in the look-back window
                        ->first();

                    if ($matchingClockIn) {
                        // Found a clock-in on the previous calendar day within the specific window.
                        // This means the current clockOutRecord is the end of a night shift for the *previous* calculated shift day.
                        // It should be ignored when processing the *current* calculated shift day.
                        $this->info("Identified specific night shift pattern ending with checkout ID{$clockOutRecord->id}({$clockOutRecord->datetime->toDateTimeString()}) for employee {$employeePin}. Starting with checkin ID{$matchingClockIn->id}({$matchingClockIn->datetime->toDateTimeString()}) on the previous calendar day. This checkout will be ignored for pairing on calculated shift day {$shiftDayDate->toDateString()}.");
                        // Add this clock-out record's ID to the list of punches to exclude
                        $punchesToExcludeIds->push($clockOutRecord->id);
                    }
                }

                // Filter the collection of records for the target shift day, excluding the identified specific night shift ends
                $recordsForTargetShiftDay = $recordsForTargetShiftDay->filter(fn($record) => ! $punchesToExcludeIds->contains($record->id));

                // Re-filter clock-ins and clock-outs from the now filtered collection
                $clockInsForShiftDay  = $recordsForTargetShiftDay->filter(fn($r) => str_starts_with($r->pin, '1'))->sortBy('datetime');
                $clockOutsForShiftDay = $recordsForTargetShiftDay->filter(fn($r) => str_starts_with($r->pin, '2'))->sortBy('datetime');

                // Note: $allRelevantPunches needs to be regenerated from the filtered collection
                $allRelevantPunches = $recordsForTargetShiftDay->filter(fn($r) => str_starts_with($r->pin, '1') || str_starts_with($r->pin, '2'))->sortBy('datetime');

                // --- NOW apply the rest of the pairing rules (Rule 1, Rule 2, etc.) to the FILTERED records ---
                // These rules will now correctly ignore the early morning checkouts that were identified
                // as end of the previous day's specific night shifts.

                                                                        // Attempt standard First '1%', Last '2%' pairing first
                $firstInRecordStandard = $clockInsForShiftDay->first(); // Use filtered collection
                $lastOutRecordStandard = $clockOutsForShiftDay->last(); // Use filtered collection

                $clockInTime          = $firstInRecordStandard ? $firstInRecordStandard->datetime : null;
                $clockOutTime         = $lastOutRecordStandard ? $lastOutRecordStandard->datetime : null;
                $clockInAttendanceId  = $firstInRecordStandard ? $firstInRecordStandard->id : null;
                $clockOutAttendanceId = $lastOutRecordStandard ? $lastOutRecordStandard->id : null;

                $hoursWorked     = null;
                $isComplete      = false;
                $shiftType       = 'unknown';
                $notes           = null;
                $latenessMinutes = 0;   // Initialize lateness
                $overtime1_5x    = 0.0; // Initialize 1.5x overtime
                $overtime2_0x    = 0.0; // Initialize 2.0x overtime

                $isHolidayShift = $this->isHoliday($shiftDayDate);                          // Check if the calculated shift day is a holiday
                $isWeekendShift = $shiftDayDate->isSaturday() || $shiftDayDate->isSunday(); // Check if calculated shift day is a weekend

                // Rule 1: Complete and valid standard pair
                // This rule now applies to the FILTERED records
                if ($firstInRecordStandard && $lastOutRecordStandard && $clockOutTime->greaterThan($clockInTime)) {
                    $hoursWorked = $clockOutTime->floatDiffInHours($clockInTime);
                    $isComplete  = true;

                    // Determine shift type based on the clock-in time of the *current* shift
                    $dayShiftStartTarget   = $shiftDayDate->copy()->setTime(self::DAY_SHIFT_STANDARD_START_HOUR, self::DAY_SHIFT_STANDARD_START_MINUTE);
                    $nightShiftStartTarget = $shiftDayDate->copy()->setTime(self::NIGHT_SHIFT_STANDARD_START_HOUR, self::NIGHT_SHIFT_STANDARD_START_MINUTE);

                    if ($clockInTime->greaterThanOrEqualTo($dayShiftStartTarget) && $clockInTime->lessThan($nightShiftStartTarget)) {
                        $shiftType       = 'day';
                        $latenessMinutes = $this->calculateLateness($clockInTime, 'day');
                    } elseif ($clockInTime->greaterThanOrEqualTo($nightShiftStartTarget) || $clockInTime->lessThan($dayShiftStartTarget)) {
                        // This condition covers night shifts that start on the calculated shift day (e.g., 18:00)
                        // OR night shifts that start on the previous calendar day but whose calculated shift day is current (e.g., clocked in 02:00, shift day is previous)
                        // The 'getShiftDay' method correctly assigns the 'shiftDayDate' based on the 4AM cutoff.
                        // For a clock-in like 02:00 on May 2nd (calculated shift day May 1st), it should still be a 'night' shift type.
                        $shiftType       = 'night';
                        $latenessMinutes = $this->calculateLateness($clockInTime, 'night');
                    } else {
                        // Fallback for times outside standard day/night start, potentially invalid clock-in pattern
                        $shiftType = 'unknown_fixed_type';
                    }

                    // Calculate both overtime types
                    [$overtime1_5x, $overtime2_0x] = $this->calculateOvertime(
                        $hoursWorked, $clockInTime, $clockOutTime, $shiftDayDate, $shiftType
                    );

                    $notes = $this->generateNotes(
                        $clockInTime, $clockOutTime, $shiftType, $hoursWorked,
                        $clockInAttendanceId, $clockOutAttendanceId,
                        $latenessMinutes, $overtime1_5x, $overtime2_0x, $isHolidayShift, $isWeekendShift
                    );

                    $this->info("Processed complete shift for {$employeePin} on {$shiftDayDate->toDateString()}. Type:{$shiftType}, Hours:" . round($hoursWorked, 2) . ", Lateness:{$latenessMinutes}min, OT(1.5x):" . round($overtime1_5x, 1) . ", OT(2.0x):" . round($overtime2_0x, 1) . ", Holiday:" . ($isHolidayShift ? 'Yes' : 'No') . ", Weekend:" . ($isWeekendShift ? 'Yes' : 'No') . ". Notes:{$notes}");
                    $this->createShiftRecord(
                        $employeePin, $shiftDayDate, $clockInAttendanceId, $clockOutAttendanceId,
                        $clockInTime, $clockOutTime, $hoursWorked, $shiftType, $isComplete, $notes,
                        $latenessMinutes, $overtime1_5x, $overtime2_0x, $isHolidayShift, $isWeekendShift
                    );
                    $shiftsProcessed++;
                }
                // Rule 2: Human Error on Same Calendar Date
                // This rule handles cases where standard pairing fails but all punches are on the same calendar day
                // and there are at least two punches, taking the first and last.
                // The lateness and overtime calculation here will be based on the general fixed shift start closest to first punch.
                elseif ($allRelevantPunches->count() >= 2 && $allRelevantPunches->every(fn($r) => $r->datetime->toDateString() === $shiftDayDate->toDateString())) {
                    $standardPairFailed = ! $firstInRecordStandard || ! $lastOutRecordStandard || ($firstInRecordStandard && $lastOutRecordStandard && $clockOutTime->lessThanOrEqualTo($clockInTime));
                    if ($standardPairFailed) {
                        $firstPunchRecord = $allRelevantPunches->first();
                        $lastPunchRecord  = $allRelevantPunches->last();
                        $firstPunchTime   = $firstPunchRecord->datetime;
                        $lastPunchTime    = $lastPunchRecord->datetime;
                        $firstPunchId     = $firstPunchRecord->id;
                        $lastPunchId      = $lastPunchRecord->id;

                        if ($lastPunchTime->greaterThan($firstPunchTime)) {
                            $hoursWorked = $lastPunchTime->floatDiffInHours($firstPunchTime);
                            $isComplete  = true;

                            // Determine lateness and an inferred shift type based on which fixed shift start time is closest to the first punch
                            $dayShiftStartTarget   = $shiftDayDate->copy()->setTime(self::DAY_SHIFT_STANDARD_START_HOUR, self::DAY_SHIFT_STANDARD_START_MINUTE);
                            $nightShiftStartTarget = $shiftDayDate->copy()->setTime(self::NIGHT_SHIFT_STANDARD_START_HOUR, self::NIGHT_SHIFT_STANDARD_START_MINUTE);

                            $inferredShiftType = 'day'; // Default
                            if (abs($firstPunchTime->diffInMinutes($dayShiftStartTarget)) > abs($firstPunchTime->diffInMinutes($nightShiftStartTarget))) {
                                $inferredShiftType = 'night';
                            }
                            $latenessMinutes = $this->calculateLateness($firstPunchTime, $inferredShiftType);
                            $shiftType       = 'human_error_' . $inferredShiftType;

                            [$overtime1_5x, $overtime2_0x] = $this->calculateOvertime(
                                $hoursWorked, $firstPunchTime, $lastPunchTime, $shiftDayDate, $inferredShiftType
                            );

                            $clockInAttendanceId  = $firstPunchId;
                            $clockOutAttendanceId = $lastPunchId;
                            $clockInTime          = $firstPunchTime;
                            $clockOutTime         = $lastPunchTime;

                            $notes = $this->generateNotes(
                                $clockInTime, $clockOutTime, $shiftType, $hoursWorked,
                                $clockInAttendanceId, $clockOutAttendanceId,
                                $latenessMinutes, $overtime1_5x, $overtime2_0x, $isHolidayShift, $isWeekendShift
                            );
                            $this->info("Processed human error shift for {$employeePin} on {$shiftDayDate->toDateString()}. Type:{$shiftType}, Hours:" . round($hoursWorked, 2) . ", Lateness:{$latenessMinutes}min, OT(1.5x):" . round($overtime1_5x, 1) . ", OT(2.0x):" . round($overtime2_0x, 1) . ", Holiday:" . ($isHolidayShift ? 'Yes' : 'No') . ", Weekend:" . ($isWeekendShift ? 'Yes' : 'No') . ". Notes:{$notes}");
                            $this->createShiftRecord(
                                $employeePin, $shiftDayDate, $clockInAttendanceId, $clockOutAttendanceId,
                                $clockInTime, $clockOutTime, $hoursWorked, $shiftType, $isComplete, $notes,
                                $latenessMinutes, $overtime1_5x, $overtime2_0x, $isHolidayShift, $isWeekendShift
                            );
                            $shiftsProcessed++;
                        } else {
                            $shiftType  = 'human_error_inverted';
                            $isComplete = false;
                            // Lateness and overtime are 0 for inverted shifts
                            $notes = $this->generateNotes(
                                $firstPunchTime, $lastPunchTime, $shiftType, 0,
                                $firstPunchId, $lastPunchId,
                                0, 0.0, 0.0, $isHolidayShift, $isWeekendShift
                            );
                            $this->warn("Human error resulted in inverted times for {$employeePin} on calculated shift day {$shiftDayDate->toDateString()}. Notes:{$notes}");
                            $this->createShiftRecord(
                                $employeePin, $shiftDayDate, $firstPunchId, $lastPunchId,
                                $firstPunchTime, $lastPunchTime, 0, $shiftType, $isComplete, $notes,
                                0, 0.0, 0.0, $isHolidayShift, $isWeekendShift
                            );
                            $shiftsProcessed++;
                        }
                    } else {
                        $this->error("Logical error: Employee {$employeePin} on calculated shift day {$shiftDayDate->toDateString()} had a standard pair in filtered records, but was not handled by Rule 1.");
                    }
                }
                // Rule 3: Standard Inverted Pair
                elseif ($firstInRecordStandard && $lastOutRecordStandard && $clockOutTime->lessThanOrEqualTo($clockInTime)) {
                    $shiftType  = 'inverted_times';
                    $isComplete = false;
                    // Lateness and overtime are 0 for inverted shifts
                    $notes = $this->generateNotes(
                        $clockInTime, $clockOutTime, $shiftType, 0,
                        $clockInAttendanceId, $clockOutAttendanceId,
                        0, 0.0, 0.0, $isHolidayShift, $isWeekendShift
                    );
                    $this->warn("Inverted times found for employee {$employeePin} on calculated shift day {$shiftDayDate->toDateString()}. Clock-in:{$clockInTime}, Clock-out:{$clockOutTime}. Notes:{$notes}");
                    $this->createShiftRecord(
                        $employeePin, $shiftDayDate, $clockInAttendanceId, $clockOutAttendanceId,
                        $clockInTime, $clockOutTime, 0, $shiftType, $isComplete, $notes,
                        0, 0.0, 0.0, $isHolidayShift, $isWeekendShift
                    );
                    $shiftsProcessed++;
                }
                // Rule 4: Missing Clock-out, with Next Day Lookahead Check (Standard Night Shift)
                elseif ($firstInRecordStandard && ! $lastOutRecordStandard) {
                    $lookaheadStart    = $clockInTime->copy()->addSecond();
                    $lookaheadEnd      = $shiftDayDate->copy()->addDay()->startOfDay()->addHours(self::MISSING_CLOCKOUT_LOOKAHEAD_HOUR);
                    $lookaheadClockOut = Attendance::where('pin', '2' . $employeePin)
                        ->whereBetween('datetime', [$lookaheadStart, $lookaheadEnd])
                        ->orderBy('datetime')
                        ->first();

                    if ($lookaheadClockOut) {
                        $clockOutTime         = $lookaheadClockOut->datetime;
                        $clockOutAttendanceId = $lookaheadClockOut->id;
                        if ($clockOutTime->greaterThan($clockInTime)) {
                            $hoursWorked = $clockOutTime->floatDiffInHours($clockInTime);
                            $isComplete  = true;
                            $shiftType   = 'standard_night'; // Default to standard_night for this case

                            // Lateness for night shift
                            $latenessMinutes = $this->calculateLateness($clockInTime, 'night');

                            // Calculate both overtime types
                            [$overtime1_5x, $overtime2_0x] = $this->calculateOvertime(
                                $hoursWorked, $clockInTime, $clockOutTime, $shiftDayDate, $shiftType
                            );

                            $notes = $this->generateNotes(
                                $clockInTime, $clockOutTime, $shiftType, $hoursWorked,
                                $clockInAttendanceId, $clockOutAttendanceId,
                                $latenessMinutes, $overtime1_5x, $overtime2_0x, $isHolidayShift, $isWeekendShift
                            );
                            $this->info("Processed missing clock-out (found next day) for {$employeePin} on calculated shift day {$shiftDayDate->toDateString()}. Type:StandardNight, Hours:" . round($hoursWorked, 2) . ", Lateness:{$latenessMinutes}min, OT(1.5x):" . round($overtime1_5x, 1) . ", OT(2.0x):" . round($overtime2_0x, 1) . ", Holiday:" . ($isHolidayShift ? 'Yes' : 'No') . ", Weekend:" . ($isWeekendShift ? 'Yes' : 'No') . ". Notes:{$notes}");
                            $this->createShiftRecord(
                                $employeePin, $shiftDayDate, $clockInAttendanceId, $clockOutAttendanceId,
                                $clockInTime, $clockOutTime, $hoursWorked, $shiftType, $isComplete, $notes,
                                $latenessMinutes, $overtime1_5x, $overtime2_0x, $isHolidayShift, $isWeekendShift
                            );
                            $shiftsProcessed++;
                        } else {
                            $shiftType  = 'lookahead_inverted';
                            $isComplete = false;
                            // Lateness and overtime are 0 for inverted shifts
                            $notes = $this->generateNotes(
                                $clockInTime, $clockOutTime, $shiftType, 0,
                                $clockInAttendanceId, $clockOutAttendanceId,
                                0, 0.0, 0.0, $isHolidayShift, $isWeekendShift
                            );
                            $this->error("Lookahead found inverted time for employee {$employeePin} on {$shiftDayDate->toDateString()}. In:{$clockInTime}, Lookahead Out:{$clockOutTime}. Notes:{$notes}");
                            $this->createShiftRecord(
                                $employeePin, $shiftDayDate, $clockInAttendanceId, $clockOutAttendanceId,
                                $clockInTime, $clockOutTime, 0, $shiftType, $isComplete, $notes,
                                0, 0.0, 0.0, $isHolidayShift, $isWeekendShift
                            );
                            $shiftsProcessed++;
                        }
                    } else {
                        $shiftType  = 'missing_clockout';
                        $isComplete = false;
                                                    // Lateness for missing clock-out (based on clock-in)
                                                    // If no clock-out, we assume a day shift for lateness calculation if no other info.
                                                    // You might need more sophisticated logic here if a missing clock-out doesn't imply day shift.
                        $latenessMinutes       = 0; // Default to 0 for missing clock out unless more context implies lateness
                        $dayShiftStartTarget   = $shiftDayDate->copy()->setTime(self::DAY_SHIFT_STANDARD_START_HOUR, self::DAY_SHIFT_STANDARD_START_MINUTE);
                        $nightShiftStartTarget = $shiftDayDate->copy()->setTime(self::NIGHT_SHIFT_STANDARD_START_HOUR, self::NIGHT_SHIFT_STANDARD_START_MINUTE);

                        // Infer shift type for lateness calculation if there's only a clock-in
                        $inferredShiftTypeForLateness = 'day';
                        if ($clockInTime && abs($clockInTime->diffInMinutes($dayShiftStartTarget)) > abs($clockInTime->diffInMinutes($nightShiftStartTarget))) {
                            $inferredShiftTypeForLateness = 'night';
                        }
                        if ($clockInTime) {
                            $latenessMinutes = $this->calculateLateness($clockInTime, $inferredShiftTypeForLateness);
                        }

                        $notes = $this->generateNotes(
                            $clockInTime, null, $shiftType, 0,
                            $clockInAttendanceId, null,
                            $latenessMinutes, 0.0, 0.0, $isHolidayShift, $isWeekendShift
                        );
                        $this->warn("Missing clock-out for employee {$employeePin} on calculated shift day {$shiftDayDate->toDateString()}. Clock-in:{$clockInTime}. Notes:{$notes}");
                        $this->createShiftRecord(
                            $employeePin, $shiftDayDate, $clockInAttendanceId, null,
                            $clockInTime, null, 0, $shiftType, $isComplete, $notes,
                            $latenessMinutes, 0.0, 0.0, $isHolidayShift, $isWeekendShift
                        );
                        $shiftsProcessed++;
                    }
                }
                // Rule 5: Missing Clock-in (Only Clock-out)
                // This rule will capture cases where an employee only has a clock-out for the target shift day
                // and no matching clock-in could be found, including the previous day's specific night shift logic.
                elseif (! $firstInRecordStandard && $lastOutRecordStandard) {
                    $shiftType  = 'missing_clockin';
                    $isComplete = false;
                    // Lateness not applicable here as no clock-in
                    $notes = $this->generateNotes(
                        null, $clockOutTime, $shiftType, 0,
                        null, $clockOutAttendanceId,
                        0, 0.0, 0.0, $isHolidayShift, $isWeekendShift
                    );
                    $this->warn("Missing clock-in for employee {$employeePin} on calculated shift day {$shiftDayDate->toDateString()}. Clock-out:{$clockOutTime}. Notes:{$notes}");
                    $this->createShiftRecord(
                        $employeePin, $shiftDayDate, null, $clockOutAttendanceId,
                        null, $clockOutTime, 0, $shiftType, $isComplete, $notes,
                        0, 0.0, 0.0, $isHolidayShift, $isWeekendShift
                    );
                    $shiftsProcessed++;
                }
                // Rule 6: No Relevant Punches or Unhandled Scenario
                else {
                    // This should theoretically not be hit if all edge cases are covered.
                    // Or it signifies truly no attendance for the day.
                    // We log this as an error/info for debugging.
                    if ($recordsForTargetShiftDay->isEmpty()) {
                        $this->info("No relevant attendance records for employee {$employeePin} on calculated shift day {$shiftDayDate->toDateString()}.");
                    } else {
                        // All other records on shiftDayDate were either already processed as part of a previous day's specific night shift
                        // or represent an unhandled combination of punches for the current shift day.
                        // For instance, multiple clock-ins, multiple clock-outs in a complex pattern
                        // not covered by the 'first in, last out' or 'human error' rules.
                        $notes = "Unhandled attendance pattern: " . $recordsForTargetShiftDay->pluck('pin', 'datetime')->toJson();
                        $this->warn("Unhandled attendance records for employee {$employeePin} on calculated shift day {$shiftDayDate->toDateString()}. Notes:{$notes}");

                        // Optionally, save an 'unhandled' shift record if there are records,
                        // but no valid shift could be formed.
                        $firstRecord = $recordsForTargetShiftDay->first();
                        $lastRecord  = $recordsForTargetShiftDay->last();
                        $this->createShiftRecord(
                            $employeePin, $shiftDayDate,
                            $firstRecord ? $firstRecord->id : null,
                            $lastRecord ? $lastRecord->id : null,
                            $firstRecord ? $firstRecord->datetime : null,
                            $lastRecord ? $lastRecord->datetime : null,
                            0, // 0 hours as it's unhandled
                            'unhandled_pattern',
                            false, // Not complete
                            $notes,
                            0, 0.0, 0.0, $isHolidayShift, $isWeekendShift // No lateness/OT for unhandled
                        );
                        $shiftsProcessed++;
                    }
                }
            }
        }); // End of DB transaction

        $this->info("Finished processing. Total shifts processed: {$shiftsProcessed}");
    }

    /**
     * Determines the calculated shift day for a given attendance record datetime.
     * Shifts are assigned to the calendar day before 4 AM (SHIFT_CUTOFF_HOUR).
     * E.g., a punch at 02:00 AM on May 2nd belongs to the shift day of May 1st.
     * A punch at 05:00 AM on May 2nd belongs to the shift day of May 2nd.
     *
     * @param Carbon $datetime The datetime of the attendance record.
     * @return Carbon The Carbon instance representing the calculated shift day (start of day).
     */
    protected function getShiftDay(Carbon $datetime): Carbon
    {
        // If the datetime is before the SHIFT_CUTOFF_HOUR, it belongs to the previous calendar day's shift.
        if ($datetime->hour < self::SHIFT_CUTOFF_HOUR) {
            return $datetime->copy()->subDay()->startOfDay();
        }
        // Otherwise, it belongs to the current calendar day's shift.
        return $datetime->copy()->startOfDay();
    }

    /**
     * Calculates lateness in minutes based on the clock-in time and shift type.
     *
     * @param Carbon $clockInTime The actual clock-in time.
     * @param string $shiftType The determined shift type ('day' or 'night').
     * @return int Lateness in minutes.
     */
    protected function calculateLateness(Carbon $clockInTime, string $shiftType): int
    {
        $standardStartTime = null;

        if ($shiftType === 'day') {
            $standardStartTime = $clockInTime->copy()->setTime(self::DAY_SHIFT_STANDARD_START_HOUR, self::DAY_SHIFT_STANDARD_START_MINUTE, 0);
        } elseif ($shiftType === 'night') {
            // Night shift can start on the previous calendar day.
            // Ensure the standard start time is on the *same calendar day as the clock-in*
            // for the purpose of lateness calculation.
            $standardStartTime = $clockInTime->copy()->setTime(self::NIGHT_SHIFT_STANDARD_START_HOUR, self::NIGHT_SHIFT_STANDARD_START_MINUTE, 0);
            // If the clock-in time is like 02:00 AM, it's still part of a night shift,
            // but the 'standard start time' for lateness comparison should still be 18:00
            // on the *previous* calendar day.
            // Example: clock_in 2025-05-02 02:00 (calculated shift day 2025-05-01)
            // standard night start for this shift day is 2025-05-01 18:00
            // If clockInTime is earlier than standardStartTime, it indicates it's from previous day for lateness,
            // or if later than morning cutoff
            if ($clockInTime->hour < self::SHIFT_CUTOFF_HOUR && $standardStartTime->hour >= self::NIGHT_SHIFT_STANDARD_START_HOUR) {
                // If the clock-in is early morning (e.g., 02:00) and the standard start is evening (18:00),
                // the standard start should be on the *previous calendar day* for accurate lateness.
                $standardStartTime->subDay();
            }
        }

        if ($standardStartTime && $clockInTime->greaterThan($standardStartTime)) {
            $lateness = $clockInTime->diffInMinutes($standardStartTime);
            return max(0, $lateness); // Ensure lateness is not negative
        }

        return 0; // Not late
    }

    /**
     * Calculates overtime hours based on total hours, shift type, and specific rules.
     * Overtime categories:
     * 1.5x: Normal working day evening (after 18:00/07:00 next day) or Saturday
     * 2.0x: Sundays and Public Holidays (all hours)
     *
     * @param float  $totalHoursWorked Total hours worked in the shift.
     * @param Carbon $clockInTime The actual clock-in time of the shift.
     * @param Carbon $clockOutTime The actual clock-out time of the shift.
     * @param Carbon $shiftDayDate The calculated shift day (Carbon instance).
     * @param string $shiftType The determined type of the shift ('day' or 'night').
     * @return array [overtime1_5x_hours, overtime2_0x_hours]
     */
    protected function calculateOvertime(
        float $totalHoursWorked,
        Carbon $clockInTime,
        Carbon $clockOutTime,
        Carbon $shiftDayDate, // This is the calculated shift day
        string $shiftType
    ): array {
        $overtime1_5x_hours = 0.0;
        $overtime2_0x_hours = 0.0;

        $isHoliday  = $this->isHoliday($shiftDayDate);
        $isSaturday = $shiftDayDate->isSaturday();
        $isSunday   = $shiftDayDate->isSunday();

        // Rule 1: All hours on Sundays or Public Holidays are 2.0x
        if ($isHoliday || $isSunday) {
            $overtime2_0x_hours = $totalHoursWorked;
            return [0.0, round($overtime2_0x_hours, 1)]; // No 1.5x when all hours are 2.0x
        }

        // Rule 2: All hours on Saturdays are 2.0x
        // NOTE: Your previous request said 1.5x for Saturday. Now you state 2.0x.
        // I am implementing 2.0x as per your latest request for Saturday.
        if ($isSaturday) {
            $overtime2_0x_hours = $totalHoursWorked;
            return [0.0, round($overtime2_0x_hours, 1)]; // No 1.5x on Saturdays if all are 2.0x
        }

                                                                                                                                           // Rule 3: Weekdays (Monday - Friday) - Time-based 1.5x Overtime
                                                                                                                                           // Any clock-in before 07:00 OR clock-out after 18:00
                                                                                                                                           // This logic applies only if it's NOT a Holiday, Saturday, or Sunday.
        if ($shiftDayDate->isWeekday()) {                                                                                                  // Check if it's Mon-Fri
            $dayShiftStartTarget = $shiftDayDate->copy()->setTime(self::DAY_SHIFT_STANDARD_START_HOUR, self::DAY_SHIFT_STANDARD_START_MINUTE); // 07:00
            $dayShiftEndTarget   = $shiftDayDate->copy()->setTime(self::DAY_SHIFT_STANDARD_END_HOUR, self::DAY_SHIFT_STANDARD_END_MINUTE);     // 18:00

            // Determine effective start and end for regular hours
            $regularHoursStart = $clockInTime;
            $regularHoursEnd   = $clockOutTime;

            // Overtime for clock-in before 07:00
            if ($clockInTime->lessThan($dayShiftStartTarget)) {
                // Hours from clock-in until 07:00 are 1.5x overtime
                $overtime1_5x_hours += $dayShiftStartTarget->floatDiffInHours($clockInTime);
                $regularHoursStart = $dayShiftStartTarget; // Regular hours start from 07:00
            }

            // Overtime for clock-out after 18:00
            // This is trickier for night shifts that clock out next day.
            // We need to compare the clock-out time with the 18:00 of the *calendar day the clock-out occurs*.
            // NO, the rule is "clockout after on (m,tu,wed,thur,fri) is 1.5x".
            // This means if a night shift starts on Mon 18:00 and ends Tue 07:00, no overtime.
            // But if a day shift starts Mon 08:00 and ends Mon 19:00, 1 hour is overtime.
            // And if a night shift starts Sun 18:00 (2.0x already handled) and ends Mon 08:00,
            // then the hours before 07:00 on Monday are regular, and after 07:00 are standard,
            // but not overtime by this rule.

            // Let's interpret "clockout after 18:00 on (m,tu,wed,thur,fri)"
            // This applies to the *calendar day* of the clock-out.

            // Overtime for clock-out after 18:00 on the same calendar day as the shift date (for day shifts)
            // OR overtime for clock-out after 18:00 on the *previous* calendar day (for night shifts ending early the next day, if they pass 18:00 on the prior day).
            // This rule seems to imply a single calendar day context for the 18:00 cutoff.
            // Let's assume the 18:00 cutoff applies to the *calendar day* of the main shift portion or clock-out.

            // Calculate hours worked *after* 18:00 on the calendar day of the clock-out.
            // Example: Shift from Mon 10:00 to Mon 19:00 (1 hour OT)
            // Example: Shift from Mon 18:00 to Tue 07:00 (0 OT by this rule, as Tue 07:00 is not after Tue 18:00)

            // For `day` type shifts (start and end on the same primary shift day)
            if ($shiftType === 'day' || str_contains($shiftType, 'day')) {
                $dayShiftOvertimeCutoff = $shiftDayDate->copy()->setTime(
                    self::OVERTIME_DAY_SHIFT_CUTOFF_HOUR,
                    self::OVERTIME_DAY_SHIFT_CUTOFF_MINUTE// 18:00
                );

                if ($clockOutTime->greaterThan($dayShiftOvertimeCutoff)) {
                    // If clock-in was also after cutoff (unlikely for day shift, but for safety)
                    $effectiveOvertimeStart = $dayShiftOvertimeCutoff;
                    if ($clockInTime->greaterThan($effectiveOvertimeStart)) {
                        $effectiveOvertimeStart = $clockInTime;
                    }
                    $overtime1_5x_hours += $clockOutTime->floatDiffInHours($effectiveOvertimeStart);
                    $regularHoursEnd = $effectiveOvertimeStart; // Regular hours end at 18:00
                }
            }
            // For `night` type shifts: only clock-in before 07:00 on primary shift day is OT.
            // Clock-out after 18:00 is not a concern for night shifts that end next day,
            // as they end before 18:00 on their end day.
            // If a night shift *extends* beyond 18:00 on its end day (e.g., starts Mon 18:00, ends Tue 19:00),
            // that's what this part covers:
            elseif ($shiftType === 'night' || str_contains($shiftType, 'night')) {
                $nightShiftOvertimeCutoffDay  = $clockOutTime->copy()->startOfDay(); // Calendar day of clock-out
                $nightShiftOvertimeCutoffTime = $nightShiftOvertimeCutoffDay->setTime(
                    self::OVERTIME_DAY_SHIFT_CUTOFF_HOUR, // Use 18:00 from day shift cutoff for this
                    self::OVERTIME_DAY_SHIFT_CUTOFF_MINUTE
                );

                // If the night shift extends past 18:00 on its *calendar end day*
                if ($clockOutTime->greaterThan($nightShiftOvertimeCutoffTime)) {
                    $effectiveOvertimeStart = $nightShiftOvertimeCutoffTime;
                    // If the clock-in was after this cutoff, then all hours are OT.
                    // This handles a very late start and very late end.
                    if ($clockInTime->greaterThan($effectiveOvertimeStart)) {
                        $effectiveOvertimeStart = $clockInTime;
                    }
                    $overtime1_5x_hours += $clockOutTime->floatDiffInHours($effectiveOvertimeStart);
                    $regularHoursEnd = $effectiveOvertimeStart;
                }
            }

            // IMPORTANT: The wording "any clockin before or clockout after on(m,tu,wed,thur,fri) is 1.5x overtime"
            // implies that any regular hours that fall outside the 07:00-18:00 window on a weekday are OT.
            // This effectively means there are no "regular" hours outside that window.
            // My previous iteration was trying to preserve a standard 8-hour shift.
            // This current interpretation aligns better with your latest statement.

            // Consider the 'regular' period for a weekday shift: 07:00 to 18:00.
            // If the shift spans this period, the part within this period is regular.
            // Anything outside is overtime.

            // Hours within the 07:00-18:00 window (on the same calendar day) are regular.
            // Hours before 07:00 are OT.
            // Hours after 18:00 are OT.

            // Let's recalculate based on segments:
            $currentOvertime = 0.0;
            $currentRegular  = 0.0;

            // Period 1: Before 07:00 (on clock-in day)
            $sevenAMOnInDay = $clockInTime->copy()->startOfDay()->setTime(7, 0);
            if ($clockInTime->lessThan($sevenAMOnInDay)) {
                $overtimeSegmentEnd = $clockOutTime->lessThan($sevenAMOnInDay) ? $clockOutTime : $sevenAMOnInDay;
                $currentOvertime += $overtimeSegmentEnd->floatDiffInHours($clockInTime);
            }

            // Period 2: After 18:00 (on clock-out day)
            $eighteenPMOnOutDay = $clockOutTime->copy()->startOfDay()->setTime(18, 0);
            if ($clockOutTime->greaterThan($eighteenPMOnOutDay)) {
                $overtimeSegmentStart = $clockInTime->greaterThan($eighteenPMOnOutDay) ? $clockInTime : $eighteenPMOnOutDay;
                $currentOvertime += $clockOutTime->floatDiffInHours($overtimeSegmentStart);
            }

            // Now, how to calculate regular hours?
            // Regular hours are simply total hours worked minus the identified overtime segments.
            // This implies that the total hours worked must equal sum of regular + overtime.
            // This simplified approach ensures that any part outside the 07:00-18:00 window on Mon-Fri is 1.5x.

            // This method is simpler and directly implements the time-window based overtime.
            $overtime1_5x_hours = $currentOvertime;
        }

        // Apply any 2.0x overtime threshold for total hours (e.g., total hours over 12)
        // This is applied *after* 1.5x calculation, and deducts from 1.5x.
        // Given your clear rules, this might not be needed if 1.5x is only for specific time windows.
        // If you still have a rule like "any total hours > 12 are 2.0x regardless of when they occur on weekdays",
        // then this should be included. Otherwise, remove it.
        /*
    $overtimeThreshold2xHours = 12; // Example: Any hours worked over 12 total hours are 2.0x
    if ($totalHoursWorked > $overtimeThreshold2xHours) {
        $hoursFor2xRate = $totalHoursWorked - $overtimeThreshold2xHours;
        // Ensure we don't reduce 1.5x overtime below zero
        $overtime1_5x_hours = max(0, $overtime1_5x_hours - $hoursFor2xRate);
        $overtime2_0x_hours += $hoursFor2xRate;
    }
    */

        return [
            max(0.0, round($overtime1_5x_hours, 1)), // Ensure no negative overtime
            max(0.0, round($overtime2_0x_hours, 1)),
        ];
    }

    /**
     * Checks if a given date is a holiday.
     * Requires a 'Holiday' model and table with a 'date' column.
     *
     * @param Carbon $date The date to check.
     * @return bool True if it's a holiday, false otherwise.
     */
    protected function isHoliday(Carbon $date): bool
    {
        // Check if the date is exactly the start_date of a Holiday record
        // AND the description contains "Public holiday"
        return Holiday::whereDate('start_date', '=', $date->toDateString())
            ->where('description', 'LIKE', '%Public holiday%') // Check if description contains "Public holiday"
            ->exists();
    }

    /**
     * Creates or updates an EmployeeShift record.
     *
     * @param string      $employeePin
     * @param Carbon      $shiftDayDate
     * @param int|null    $clockInAttendanceId
     * @param int|null    $clockOutAttendanceId
     * @param Carbon|null $clockInTime
     * @param Carbon|null $clockOutTime
     * @param float       $hoursWorked
     * @param string      $shiftType
     * @param bool        $isComplete
     * @param string|null $notes
     * @param int         $latenessMinutes
     * @param float       $overtime1_5x
     * @param float       $overtime2_0x
     * @param bool        $isHoliday
     * @param bool        $isWeekend
     * @return EmployeeShift
     */
    protected function createShiftRecord(
        string $employeePin,
        Carbon $shiftDayDate,
        ?int $clockInAttendanceId,
        ?int $clockOutAttendanceId,
        ?Carbon $clockInTime,
        ?Carbon $clockOutTime,
        float $hoursWorked,
        string $shiftType,
        bool $isComplete,
        ?string $notes,
        int $latenessMinutes,
        float $overtime1_5x,
        float $overtime2_0x,
        bool $isHoliday,
        bool $isWeekend
    ): EmployeeShift {
        return EmployeeShift::create([
            'employee_pin'            => $employeePin,
            'shift_date'              => $shiftDayDate->toDateString(),
            'clock_in_attendance_id'  => $clockInAttendanceId,
            'clock_out_attendance_id' => $clockOutAttendanceId,
            'clock_in_time'           => $clockInTime,
            'clock_out_time'          => $clockOutTime,
            'hours_worked'            => round($hoursWorked, 2), // Store with 2 decimal places
            'shift_type'              => $shiftType,
            'is_complete'             => $isComplete,
            'notes'                   => $notes,
            'lateness_minutes'        => $latenessMinutes,
            'overtime_hours_1_5x'     => round($overtime1_5x, 1), // Store with 1 decimal place
            'overtime_hours_2_0x'     => round($overtime2_0x, 1), // Store with 1 decimal place
            'is_holiday'              => $isHoliday,
            'is_weekend'              => $isWeekend,
        ]);
    }

    /**
     * Generates a descriptive note for the shift.
     *
     * @param Carbon|null $clockInTime
     * @param Carbon|null $clockOutTime
     * @param string      $shiftType
     * @param float       $hoursWorked
     * @param int|null    $clockInId
     * @param int|null    $clockOutId
     * @param int         $latenessMinutes
     * @param float       $overtime1_5x
     * @param float       $overtime2_0x
     * @param bool        $isHoliday
     * @param bool        $isWeekend
     * @return string
     */
    protected function generateNotes(
        ?Carbon $clockInTime,
        ?Carbon $clockOutTime,
        string $shiftType,
        float $hoursWorked,
        ?int $clockInId,
        ?int $clockOutId,
        int $latenessMinutes,
        float $overtime1_5x,
        float $overtime2_0x,
        bool $isHoliday,
        bool $isWeekend
    ): string {
        $inTimeStr  = $clockInTime ? $clockInTime->format('H:i') : 'N/A';
        $outTimeStr = $clockOutTime ? $clockOutTime->format('H:i') : 'N/A';
        $inDateStr  = $clockInTime ? $clockInTime->format('Y-m-d') : 'N/A';
        $outDateStr = $clockOutTime ? $clockOutTime->format('Y-m-d') : 'N/A';

        $notes = "Shift Type: {$shiftType}. ";
        $notes .= "Clock-in: {$inDateStr} {$inTimeStr} (ID:{$clockInId}). ";
        $notes .= "Clock-out: {$outDateStr} {$outTimeStr} (ID:{$clockOutId}). ";
        $notes .= "Hours: " . round($hoursWorked, 2) . ". ";
        $notes .= "Lateness: {$latenessMinutes}min. ";
        $notes .= "OT(1.5x): " . round($overtime1_5x, 1) . "h. ";
        $notes .= "OT(2.0x): " . round($overtime2_0x, 1) . "h. ";
        $notes .= "Holiday: " . ($isHoliday ? 'Yes' : 'No') . ". ";
        $notes .= "Weekend: " . ($isWeekend ? 'Yes' : 'No') . ".";

        return $notes;
    }
}
