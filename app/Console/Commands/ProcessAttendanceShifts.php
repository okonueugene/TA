<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\EmployeeShift;
use App\Models\Holiday; // Import the Holiday model
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection; // Import Collection


class ProcessAttendanceShifts extends Command
{
    protected $signature =  'process:shifts {date? : The date to process (YYYY-MM-DD)}';
    protected $description = 'Process attendance records into employee shifts based on calculated shift days, handling night shifts and common errors, calculating overtime and identifying holidays.';

    // Define the hour that marks the cutoff for a shift day (e.g., 4 AM).
    // Attendance records before this hour on a calendar day are considered part of the previous shift day.
    // Adjust this hour based on when your night shifts typically end.
    const SHIFT_CUTOFF_HOUR = 4; // Example: 4:00 AM cutoff

    // Define the lookahead hour for missing clock-outs for potential standard night shifts.
    // If a clock-in is found without a clock-out within the calculated shift day,
    // look for a clock-out on the next calendar day up to this hour.
    const MISSING_CLOCKOUT_LOOKAHEAD_HOUR = 10; // Look up to 10 AM on the next calendar day

     // Define the time windows for the specific night shift pattern (Previous Day In, Current Day Out)
     const SPECIFIC_NIGHT_IN_START_HOUR = 16; // 4 PM
     const SPECIFIC_NIGHT_IN_END_HOUR = 19; // 7 PM
     const SPECIFIC_NIGHT_OUT_START_HOUR = 4; // 4 AM
     const SPECIFIC_NIGHT_OUT_END_HOUR = 9; // 9 AM

     // Define overtime cutoffs (calendar times)
     const OVERTIME_DAY_CUTOFF_HOUR = 17; // 5 PM (17:00)
     const OVERTIME_DAY_CUTOFF_MINUTE = 30; // 17:30
     const OVERTIME_NIGHT_CUTOFF_HOUR = 7; // 7 AM (07:00)


    public function handle()
    {
        // Determine the target date for processing shifts.
        // The command will calculate and process shifts whose *calculated shift day*
        // falls on this target date.
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
        $fetchStart = $targetDate->copy()->subDay()->startOfDay(); // e.g., For target shift day 2025-05-01, fetch from 2025-04-30 00:00:00
        $fetchEnd = $targetDate->copy()->addDay()->startOfDay()->addHours(self::MISSING_CLOCKOUT_LOOKAHEAD_HOUR)->endOfMinute(); // e.g., For target shift day 2025-05-01, fetch up to 2025-05-02 10:00:59


        $this->info("Fetching attendance records between {$fetchStart->toDateTimeString()} and {$fetchEnd->toDateTimeString()}");

        // Fetch attendance records within the defined range, filtered by relevant pin prefixes ('1%'/'2%'), and ordered chronologically.
        $attendances = Attendance::whereBetween('datetime', [$fetchStart, $fetchEnd])
            ->where(function ($query) { // Filter for clock-in or clock-out pins
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

        // Use a database transaction for atomic operations when creating/updating shifts
        DB::transaction(function () use ($groupedByEmployeeAndShiftDay, $targetDate, &$shiftsProcessed) {
            // Iterate through each employee and their attendance grouped by shift day
            foreach ($groupedByEmployeeAndShiftDay as $employeePin => $shiftsForEmployee) {
                // For this command run, we only process the shift day that matches the target date
                if (!isset($shiftsForEmployee[$targetDate->toDateString()])) {
                    // This employee did not have any attendance records whose calculated shift day
                    // falls on the target date. Skip them for this date's processing.
                    continue;
                }

                // Get the attendance records specifically for the target calculated shift day for this employee
                // This collection will be modified to exclude specific night shift ends
                $recordsForTargetShiftDay = $shiftsForEmployee[$targetDate->toDateString()];
                $shiftDayDate = $targetDate->copy()->startOfDay(); // The calculated shift day date

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
                $potentialPreviousNightShiftEnds = $recordsForTargetShiftDay->filter(function($record) use ($shiftDayDate) {
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
                    $previousCalendarDay = $shiftDayDate->copy()->subDay()->startOfDay(); // The calendar day before the current calculated shift day
                    $lookbackStart = $previousCalendarDay->copy()->addHours(self::SPECIFIC_NIGHT_IN_START_HOUR); // Previous day 16:00
                    $lookbackEnd = $previousCalendarDay->copy()->addHours(self::SPECIFIC_NIGHT_IN_END_HOUR)->endOfMinute(); // Previous day 19:00

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

                        $this->info("Identified specific night shift pattern ending with checkout ID {$clockOutRecord->id} ({$clockOutRecord->datetime->toDateTimeString()}) for employee {$employeePin}. Starting with checkin ID {$matchingClockIn->id} ({$matchingClockIn->datetime->toDateTimeString()}) on the previous calendar day. This checkout will be ignored for pairing on calculated shift day {$shiftDayDate->toDateString()}.");

                        // Add this clock-out record's ID to the list of punches to exclude
                        $punchesToExcludeIds->push($clockOutRecord->id);
                    }
                }

                // Filter the collection of records for the target shift day, excluding the identified specific night shift ends
                $recordsForTargetShiftDay = $recordsForTargetShiftDay->filter(fn($record) => !$punchesToExcludeIds->contains($record->id));

                // Re-filter clock-ins and clock-outs from the now filtered collection
                $clockInsForShiftDay = $recordsForTargetShiftDay->filter(fn($r) => str_starts_with($r->pin, '1'))->sortBy('datetime');
                $clockOutsForShiftDay = $recordsForTargetShiftDay->filter(fn($r) => str_starts_with($r->pin, '2'))->sortBy('datetime');
                 // Note: $allRelevantPunches needs to be regenerated from the filtered collection
                 $allRelevantPunches = $recordsForTargetShiftDay->filter(fn($r) => str_starts_with($r->pin, '1') || str_starts_with($r->pin, '2'))->sortBy('datetime');


                // --- NOW apply the rest of the pairing rules (Rule 1, Rule 2, etc.) to the FILTERED records ---
                // These rules will now correctly ignore the early morning checkouts that were identified
                // as endsof the previous day's specific night shifts.

                // Attempt standard First '1%', Last '2%' pairing first
                $firstInRecordStandard = $clockInsForShiftDay->first(); // Use filtered collection
                $lastOutRecordStandard = $clockOutsForShiftDay->last(); // Use filtered collection

                $clockInTime = $firstInRecordStandard ? $firstInRecordStandard->datetime : null;
                $clockOutTime = $lastOutRecordStandard ? $lastOutRecordStandard->datetime : null;
                $clockInAttendanceId = $firstInRecordStandard ? $firstInRecordStandard->id : null;
                $clockOutAttendanceId = $lastOutRecordStandard ? $lastOutRecordStandard->id : null;


                $hoursWorked = null;
                $isComplete = false;
                $shiftType = 'unknown';
                $notes = null;
                $overtimeHours = 0.0; // Initialize overtime hours for this potential shift record
                $isHolidayShift = $this->isHoliday($shiftDayDate); // Check if the calculated shift day is a holiday


                // Rule 1: Complete and valid standard pair
                // This rule now applies to the FILTERED records
                if ($firstInRecordStandard && $lastOutRecordStandard && $clockOutTime->greaterThan($clockInTime)) {
                    $hoursWorked = $clockOutTime->floatDiffInHours($clockInTime);
                    $isComplete = true;

                    $shiftType = ($clockOutTime->copy()->startOfDay()->greaterThan($shiftDayDate->copy()->startOfDay())) ? 'standard_night' : 'day';

                    // Calculate overtime for this complete shift
                    $overtimeHours = $this->calculateOvertime($clockInTime, $clockOutTime, $shiftType);

                    $notes = $this->generateNotes($clockInTime, $clockOutTime, $shiftType, $hoursWorked, $clockInAttendanceId, $clockOutAttendanceId, $overtimeHours, $isHolidayShift);

                    $this->info("Processed complete shift for {$employeePin} on {$shiftDayDate->toDateString()}. Type: {$shiftType}, Hours: " . round($hoursWorked, 2) . ", Overtime: " . round($overtimeHours, 2) . ", Holiday: " . ($isHolidayShift ? 'Yes' : 'No') . ". Notes: {$notes}");

                    $this->createShiftRecord(
                        $employeePin, $shiftDayDate,
                        $clockInAttendanceId, $clockOutAttendanceId,
                        $clockInTime, $clockOutTime,
                        $hoursWorked, $shiftType, $isComplete, $notes,
                        $overtimeHours, $isHolidayShift
                    );
                    $shiftsProcessed++;
                }
                // Rule 2: Human Error on Same Calendar Date
                elseif ($allRelevantPunches->count() >= 2 &&
                         $allRelevantPunches->every(fn($r) => $r->datetime->toDateString() === $shiftDayDate->toDateString()))
                {
                    $standardPairFailed = !$firstInRecordStandard || !$lastOutRecordStandard ||
                                          ($firstInRecordStandard && $lastOutRecordStandard && $clockOutTime->lessThanOrEqualTo($clockInTime));

                    if ($standardPairFailed) {
                         $firstPunchRecord = $allRelevantPunches->first();
                         $lastPunchRecord = $allRelevantPunches->last();

                         $firstPunchTime = $firstPunchRecord->datetime;
                         $lastPunchTime = $lastPunchRecord->datetime;
                         $firstPunchId = $firstPunchRecord->id;
                         $lastPunchId = $lastPunchRecord->id;

                         if ($lastPunchTime->greaterThan($firstPunchTime)) {
                             $hoursWorked = $lastPunchTime->floatDiffInHours($firstPunchTime);
                             $isComplete = true;
                             $shiftType = 'human_error_day';

                             // Calculate overtime for human error day shifts if they have a valid duration
                             $overtimeHours = $this->calculateOvertime($firstPunchTime, $lastPunchTime, 'day'); // Assume 'day' rules for human_error_day

                             $clockInAttendanceId = $firstPunchId;
                             $clockOutAttendanceId = $lastPunchId;
                             $clockInTime = $firstPunchTime;
                             $clockOutTime = $lastPunchTime;


                             $notes = $this->generateNotes($clockInTime, $clockOutTime, $shiftType, $hoursWorked, $clockInAttendanceId, $clockOutAttendanceId, $overtimeHours, $isHolidayShift);
                             $this->info("Processed human error day shift for {$employeePin} on {$shiftDayDate->toDateString()}. Hours: " . round($hoursWorked, 2) . ", Overtime: " . round($overtimeHours, 2) . ", Holiday: " . ($isHolidayShift ? 'Yes' : 'No') . ". Notes: {$notes}");

                             $this->createShiftRecord(
                                 $employeePin, $shiftDayDate,
                                 $clockInAttendanceId, $clockOutAttendanceId,
                                 $clockInTime, $clockOutTime,
                                 $hoursWorked, $shiftType, $isComplete, $notes,
                                 $overtimeHours, $isHolidayShift
                             );
                             $shiftsProcessed++;
                         } else {
                             $shiftType = 'human_error_inverted';
                             $isComplete = false;
                             $overtimeHours = 0.0; // No overtime for inverted
                             $notes = $this->generateNotes($firstPunchTime, $lastPunchTime, $shiftType, 0, $firstPunchId, $lastPunchId, $overtimeHours, $isHolidayShift);
                             $this->warn("Human error resulted in inverted times for {$employeePin} on calculated shift day {$shiftDayDate->toDateString()}. Notes: {$notes}");

                             $this->createShiftRecord(
                                 $employeePin, $shiftDayDate,
                                 $firstPunchId, $lastPunchId,
                                 $firstPunchTime, $lastPunchTime,
                                 0, $shiftType, $isComplete, $notes,
                                 $overtimeHours, $isHolidayShift
                             );
                             $shiftsProcessed++;
                         }
                    } else {
                         $this->error("Logical error: Employee {$employeePin} on calculated shift day {$shiftDayDate->toDateString()} had a standard pair in filtered records, but was not handled by Rule 1.");
                    }
                }
                // Rule 3: Standard Inverted Pair
                elseif ($firstInRecordStandard && $lastOutRecordStandard && $clockOutTime->lessThanOrEqualTo($clockInTime)) {
                    $shiftType = 'inverted_times';
                    $isComplete = false;
                    $overtimeHours = 0.0; // No overtime for inverted

                    $notes = $this->generateNotes($clockInTime, $clockOutTime, $shiftType, 0, $clockInAttendanceId, $clockOutAttendanceId, $overtimeHours, $isHolidayShift);

                    $this->warn("Inverted times found for employee {$employeePin} on calculated shift day {$shiftDayDate->toDateString()}. Clock-in: {$clockInTime}, Clock-out: {$clockOutTime}. Notes: {$notes}");

                    $this->createShiftRecord(
                        $employeePin, $shiftDayDate,
                        $clockInAttendanceId, $clockOutAttendanceId,
                        $clockInTime, $clockOutTime,
                        0, $shiftType, $isComplete, $notes,
                        $overtimeHours, $isHolidayShift
                    );
                    $shiftsProcessed++;
                }
                // Rule 4: Missing Clock-out, with Next Day Lookahead Check (Standard Night Shift)
                elseif ($firstInRecordStandard && !$lastOutRecordStandard) {
                    $lookaheadStart = $clockInTime->copy()->addSecond();
                    $lookaheadEnd = $shiftDayDate->copy()->addDay()->startOfDay()->addHours(self::MISSING_CLOCKOUT_LOOKAHEAD_HOUR);

                    $lookaheadClockOut = Attendance::where('pin', '2' . $employeePin)
                        ->whereBetween('datetime', [$lookaheadStart, $lookaheadEnd])
                        ->orderBy('datetime')
                        ->first();

                    if ($lookaheadClockOut) {
                        $clockOutTime = $lookaheadClockOut->datetime;
                        $clockOutAttendanceId = $lookaheadClockOut->id;

                        if ($clockOutTime->greaterThan($clockInTime)) {
                            $hoursWorked = $clockOutTime->floatDiffInHours($clockInTime);
                            $isComplete = true;
                            $shiftType = 'standard_night';

                            // Calculate overtime for standard night shifts
                            $overtimeHours = $this->calculateOvertime($clockInTime, $clockOutTime, $shiftType);

                            $notes = $this->generateNotes($clockInTime, $clockOutTime, $shiftType, $hoursWorked, $clockInAttendanceId, $clockOutAttendanceId, $overtimeHours, $isHolidayShift);

                            $this->info("Processed missing clock-out (found next day) for {$employeePin} on calculated shift day {$shiftDayDate->toDateString()}. Type: Standard Night, Hours: " . round($hoursWorked, 2) . ", Overtime: " . round($overtimeHours, 2) . ", Holiday: " . ($isHolidayShift ? 'Yes' : 'No') . ". Notes: {$notes}");

                            $this->createShiftRecord(
                                $employeePin, $shiftDayDate,
                                $clockInAttendanceId, $clockOutAttendanceId,
                                $clockInTime, $clockOutTime,
                                $hoursWorked, $shiftType, $isComplete, $notes,
                                $overtimeHours, $isHolidayShift
                            );
                            $shiftsProcessed++;
                        } else {
                            $shiftType = 'lookahead_inverted';
                            $isComplete = false;
                            $overtimeHours = 0.0;
                            $notes = $this->generateNotes($clockInTime, $clockOutTime, $shiftType, 0, $clockInAttendanceId, $clockOutAttendanceId, $overtimeHours, $isHolidayShift);
                            $this->error("Lookahead found inverted time for employee {$employeePin} on {$shiftDayDate->toDateString()}. In: {$clockInTime}, Lookahead Out: {$clockOutTime}. Notes: {$notes}");

                            $this->createShiftRecord(
                                $employeePin, $shiftDayDate,
                                $clockInAttendanceId, $clockOutAttendanceId,
                                $clockInTime, $clockOutTime,
                                0, $shiftType, $isComplete, $notes,
                                $overtimeHours, $isHolidayShift
                            );
                            $shiftsProcessed++;
                        }
                    } else {
                        $shiftType = 'missing_clockout';
                        $isComplete = false;
                        $overtimeHours = 0.0;
                        $notes = $this->generateNotes($clockInTime, null, $shiftType, null, $clockInAttendanceId, null, $overtimeHours, $isHolidayShift);
                        $this->warn("Missing clock-out for employee {$employeePin} on calculated shift day {$shiftDayDate->toDateString()} after lookahead. Notes: {$notes}");

                        $this->createShiftRecord(
                            $employeePin, $shiftDayDate,
                            $clockInAttendanceId, null,
                            $clockInTime, null,
                            null, $shiftType, $isComplete, $notes,
                             $overtimeHours, $isHolidayShift
                        );
                        $shiftsProcessed++;
                    }
                }
                // Rule 5: Missing Clock-in (Standard)
                elseif (!$firstInRecordStandard && $lastOutRecordStandard) {
                    $shiftType = 'missing_clockin';
                    $isComplete = false;
                    $overtimeHours = 0.0;
                    $notes = $this->generateNotes(null, $clockOutTime, $shiftType, null, null, $clockOutAttendanceId, $overtimeHours, $isHolidayShift);
                    $this->warn("Missing clock-in for employee {$employeePin} on calculated shift day {$shiftDayDate->toDateString()}. Notes: {$notes}");

                    $this->createShiftRecord(
                        $employeePin, $shiftDayDate,
                        null, $clockOutAttendanceId,
                        null, $clockOutTime,
                        null, $shiftType, $isComplete, $notes,
                         $overtimeHours, $isHolidayShift
                    );
                    $shiftsProcessed++;
                }
                // Implicit Case: No relevant punches found after filtering or other unhandled combinations
                else {
                    if ($recordsForTargetShiftDay->count() > 0) {
                        $this->warn("Unhandled punch pattern for employee {$employeePin} in filtered group for calculated shift day {$shiftDayDate->toDateString()}. Total records in filtered group: {$recordsForTargetShiftDay->count()}.");
                    } else {
                       $this->info("No relevant punches found for employee {$employeePin} in filtered group for calculated shift day {$shiftDayDate->toDateString()}. Skipping.");
                    }
                    // No shift record created for these cases.
                }

            } // End foreach $groupedByEmployeeAndShiftDay
        }); // End DB::transaction

        $this->info("Processing completed!");
        $this->info("{$shiftsProcessed} shifts processed (created or updated) for calculated shift day: {$targetDate->toDateString()}.");
    }

    /**
     * Calculates the calendar date of the shift day for a given timestamp based on cutoff hour.
     * Attendance records before the cutoff hour on a calendar day are considered part of the previous shift day.
     *
     * @param Carbon $datetime The attendance record's datetime (Carbon instance).
     * @return Carbon The Carbon date representing the start of the calculated shift day.
     */
    protected function getShiftDay(Carbon $datetime): Carbon
    {
        return $datetime->copy()->hour < self::SHIFT_CUTOFF_HOUR
            ? $datetime->copy()->subDay()->startOfDay()
            : $datetime->copy()->startOfDay();
    }

     /**
      * Checks if a given date is a holiday.
      * Assumes a Holiday model with 'start_date' and 'end_date' columns.
      *
      * @param Carbon $date The date to check (should be the calculated shift day).
      * @return bool
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
      * Calculates overtime hours for a complete shift based on shift type and cutoffs.
      *
      * @param Carbon $clockInTime
      * @param Carbon $clockOutTime
      * @param string $shiftType
      * @return float The calculated overtime hours.
      */
     protected function calculateOvertime(Carbon $clockInTime, Carbon $clockOutTime, string $shiftType): float
     {
         $overtimeHours = 0.0;

         // Overtime calculation only applies to complete shifts (called from Rule 1 & Rule 2 valid cases)

         if ($shiftType === 'day' || $shiftType === 'human_error_day') {
             // Day shift overtime cutoff is 17:30 (on the clock-out day's calendar date)
             $dayOvertimeCutoff = $clockOutTime->copy()->startOfDay()->setTime(self::OVERTIME_DAY_CUTOFF_HOUR, self::OVERTIME_DAY_CUTOFF_MINUTE);

             if ($clockOutTime->greaterThan($dayOvertimeCutoff)) {
                 $overtimeHours = $clockOutTime->floatDiffInHours($dayOvertimeCutoff);
             }
         } elseif ($shiftType === 'standard_night' || $shiftType === 'specific_pattern_night') {
             // Night shift overtime cutoff is 07:00 (on the clock-out day's calendar date)
             $nightOvertimeCutoff = $clockOutTime->copy()->startOfDay()->setTime(self::OVERTIME_NIGHT_CUTOFF_HOUR, 0);

             // Check if the clock out time is after the 07:00 cutoff on its calendar day
             // We need to be careful if a shift ends exactly at 07:00:00. Using greaterThanOrEqual might be better.
             // Let's stick to greaterThan for now based on "over 0700".
             if ($clockOutTime->greaterThan($nightOvertimeCutoff)) {
                 $overtimeHours = $clockOutTime->floatDiffInHours($nightOvertimeCutoff);
             }
         }

         // Ensure overtime hours is not negative
         return max(0.0, $overtimeHours);
     }


    /**
     * Creates or updates an EmployeeShift record.
     *
     * @param string $employeePin
     * @param Carbon $shiftDate The calculated shift day date.
     * @param int|null $clockInAttendanceId
     * @param int|null $clockOutAttendanceId
     * @param Carbon|null $clockInTime
     * @param Carbon|null $clockOutTime
     * @param float|null $hoursWorked
     * @param string $shiftType
     * @param bool $isComplete
     * @param string|null $notes
     * @param float $overtimeHours
     * @param bool $isHoliday
     * @return EmployeeShift The created or updated shift record.
     */
    protected function createShiftRecord(
        string $employeePin,
        Carbon $shiftDate,
        ?int $clockInAttendanceId,
        ?int $clockOutAttendanceId,
        ?Carbon $clockInTime,
        ?Carbon $clockOutTime,
        ?float $hoursWorked,
        string $shiftType,
        bool $isComplete,
        ?string $notes,
        float $overtimeHours, // Add new parameter
        bool $isHoliday // Add new parameter
    ): EmployeeShift
    {
        return EmployeeShift::updateOrCreate(
            [
                'employee_pin' => $employeePin,
                'shift_date' => $shiftDate->toDateString(),
            ],
            [
                'clock_in_attendance_id' => $clockInAttendanceId,
                'clock_out_attendance_id' => $clockOutAttendanceId,
                'clock_in_time' => $clockInTime,
                'clock_out_time' => $clockOutTime,
                'hours_worked' => $hoursWorked,
                'shift_type' => $shiftType,
                'is_complete' => $isComplete,
                'notes' => $notes,
                'overtime_hours' => (float) round($overtimeHours, 2), // Store rounded overtime
                'is_holiday' => $isHoliday // Store holiday status
            ]
        );
    }

    /**
     * Generates a notes string for a shift record.
     *
     * @param Carbon|null $clockInTime
     * @param Carbon|null $clockOutTime
     * @param string $shiftType The type of shift.
     * @param float|null $hoursWorked
     * @param int|null $clockInAttendanceId
     * @param int|null $clockOutAttendanceId
     * @param float $overtimeHours
     * @param bool $isHoliday
     * @return string|null
     */
    protected function generateNotes(?Carbon $clockInTime, ?Carbon $clockOutTime, string $shiftType, ?float $hoursWorked, ?int $clockInAttendanceId, ?int $clockOutAttendanceId, float $overtimeHours, bool $isHoliday): ?string
    {
        $notes = [];

        // Basic type notes
        switch ($shiftType) {
            case 'day':
                $notes[] = 'Day shift';
                break;
            case 'standard_night':
                $notes[] = 'Standard Night shift';
                break;
             case 'specific_pattern_night':
                 $notes[] = 'Specific Night shift pattern matched';
                break;
            case 'missing_clockin':
                $notes[] = 'Missing clock-in';
                break;
            case 'missing_clockout':
                $notes[] = 'Missing clock-out';
                break;
            case 'inverted_times':
                $notes[] = 'Inverted times (std pair)';
                break;
            case 'lookahead_inverted':
                $notes[] = 'Lookahead found inverted time';
                break;
            case 'human_error_day':
                $notes[] = 'Human error - Day shift';
                break;
            case 'human_error_inverted':
                $notes[] = 'Human error - Inverted times';
                break;
            default:
                $notes[] = ucfirst(str_replace('_', ' ', $shiftType)) . ' shift';
        }

         // Add Holiday status note
         if ($isHoliday) {
             $notes[] = 'Holiday shift';
         }


        // Duration-based notes
        if ($hoursWorked !== null && !in_array($shiftType, ['inverted_times', 'lookahead_inverted', 'human_error_inverted'])) {
             $notes[] = 'Duration: ' . round($hoursWorked, 2) . ' hours';
        }

         // Add Overtime note
         if ($overtimeHours > 0) {
             $notes[] = 'Overtime: ' . round($overtimeHours, 2) . ' hours';
         }


        // Add times to notes for clarity if present
        if ($clockInTime) {
            $notes[] = 'In: ' . $clockInTime->format('Y-m-d H:i:s');
        }
        if ($clockOutTime) {
            $notes[] = 'Out: ' . $clockOutTime->format('Y-m-d H:i:s');
        }

        // Add attendance record IDs for traceability
        if ($clockInAttendanceId) {
            $notes[] = 'In ID: ' . $clockInAttendanceId;
        }
        if ($clockOutAttendanceId) {
            $notes[] = 'Out ID: ' . $clockOutAttendanceId;
        }

        return !empty($notes) ? implode('; ', $notes) : null;
    }
}