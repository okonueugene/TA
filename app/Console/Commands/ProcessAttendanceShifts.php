<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\EmployeeShift;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection; // Import Collection

class ProcessAttendanceShifts extends Command
{
    protected $signature =  'process:shifts {date? : The date to process (YYYY-MM-DD)}';
    protected $description = 'Process attendance records into employee shifts based on calculated shift days, handling night shifts and common errors.';

    // Define the hour that marks the cutoff for a shift day (e.g., 4 AM).
    // Attendance records before this hour on a calendar day are considered part of the previous shift day.
    // Adjust this hour based on when your night shifts typically end.
    const SHIFT_CUTOFF_HOUR = 4; // Example: 4:00 AM cutoff

    // Define the lookahead hour for missing clock-outs for potential night shifts.
    // If a clock-in is found without a clock-out within the calculated shift day,
    // look for a clock-out on the next calendar day up to this hour.
    const MISSING_CLOCKOUT_LOOKAHEAD_HOUR = 10; // Look up to 10 AM on the next calendar day

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
        // and potential clock-outs on the day after the target shift day (up to the shift cutoff + lookahead).
        $fetchStart = $targetDate->copy()->subDay()->startOfDay(); // e.g., For target shift day 2025-04-19, fetch from 2025-04-18 00:00:00
        // The end of the fetch range needs to go far enough into the next calendar day
        // to capture the latest possible clock-out for a shift ending on the target shift day's calculated end time (which is the cutoff hour on the next day).
        // Plus, we need to fetch up to the MISSING_CLOCKOUT_LOOKAHEAD_HOUR on the day AFTER the calculated shift day
        // to check for missing night shift clock-outs as per your specific requirement.
        $fetchEnd = $targetDate->copy()->addDay()->startOfDay()->addHours(self::MISSING_CLOCKOUT_LOOKAHEAD_HOUR)->endOfMinute(); // e.g., For target shift day 2025-04-19, fetch up to 2025-04-20 10:59:59


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
                 $recordsForTargetShiftDay = $shiftsForEmployee[$targetDate->toDateString()];
                 $shiftDayDate = $targetDate->copy()->startOfDay(); // The calculated shift day date

                 $this->info("Processing calculated shift day {$shiftDayDate->toDateString()} for employee {$employeePin}");


                // --- Delete existing shifts for this calculated shift day and employee ---
                // This is crucial for idempotency: clear out old processed results for this shift day before creating new ones.
                EmployeeShift::where('employee_pin', $employeePin)
                    ->where('shift_date', $shiftDayDate->toDateString()) // Delete based on the calculated shift day
                    ->delete();
                 $this->info("Deleted existing shift records for {$employeePin} on {$shiftDayDate->toDateString()}.");


                // --- Apply Pairing Logic within this shift day group, based on desired rules ---
                // Get all clock-in ('1%') and clock-out ('2%') records for this calculated shift day, sorted by time.
                $clockInsForShiftDay = $recordsForTargetShiftDay->filter(fn($r) => str_starts_with($r->pin, '1'))->sortBy('datetime');
                $clockOutsForShiftDay = $recordsForTargetShiftDay->filter(fn($r) => str_starts_with($r->pin, '2'))->sortBy('datetime');
                // Also get all relevant punches (IN or OUT) for applying overall boundary logic if needed
                $allRelevantPunches = $recordsForTargetShiftDay->filter(fn($r) => str_starts_with($r->pin, '1') || str_starts_with($r->pin, '2'))->sortBy('datetime');


                // Attempt standard First '1%', Last '2%' pairing first
                $firstInRecordStandard = $clockInsForShiftDay->first();
                $lastOutRecordStandard = $clockOutsForShiftDay->last();

                $clockInTime = $firstInRecordStandard ? $firstInRecordStandard->datetime : null;
                $clockOutTime = $lastOutRecordStandard ? $lastOutRecordStandard->datetime : null;
                $clockInAttendanceId = $firstInRecordStandard ? $firstInRecordStandard->id : null;
                $clockOutAttendanceId = $lastOutRecordStandard ? $lastOutRecordStandard->id : null;


                $hoursWorked = null;
                $isComplete = false;
                $shiftType = 'unknown'; // Default type before determination
                $notes = null;


                // --- Determine the Shift Outcome based on rules ---

                // MODIFIED Rule Priority:
                // 1. Complete and valid standard pair ('1%' First, '2%' Last, Out after In)
                // 2. Human Error on Same Calendar Date (valid range) - Moved up in priority
                // 3. Standard Inverted Pair - Only if Rule 2 didn't apply
                // 4. Missing Clock-out, with Next Day Lookahead Check
                // 5. Missing Clock-in (Standard)
                // 6. Human Error Inverted Times


                // Rule 1: Complete and valid standard pair ('1%' First, '2%' Last, Out after In)
                if ($firstInRecordStandard && $lastOutRecordStandard && $clockOutTime->greaterThan($clockInTime)) {
                    $hoursWorked = $clockOutTime->floatDiffInHours($clockInTime);
                    $isComplete = true;
                    // Determine shift type ('day' or 'night') based on the calculated shift day vs punch times.
                    // If the clock-out time's calendar day is after the shiftDayDate's calendar day, it's a night shift.
                    $shiftType = ($clockOutTime->copy()->startOfDay()->greaterThan($shiftDayDate->copy()->startOfDay())) ? 'night' : 'day';

                    $notes = $this->generateNotes($clockInTime, $clockOutTime, $shiftType, $hoursWorked, $clockInAttendanceId, $clockOutAttendanceId);
                    $this->info("Processed complete shift for {$employeePin} on {$shiftDayDate->toDateString()}. Type: {$shiftType}, Hours: " . round($hoursWorked, 2));

                    $this->createShiftRecord(
                        $employeePin, $shiftDayDate,
                        $clockInAttendanceId, $clockOutAttendanceId,
                        $clockInTime, $clockOutTime,
                        $hoursWorked, $shiftType, $isComplete, $notes
                    );
                    $shiftsProcessed++;
                }
                // Rule 2 (MOVED UP): Human Error on Same Calendar Date (valid range)
                // Checks if standard pairing would fail (empty records or inverted times) BUT all punches are on the same calendar date.
                // Check that we have punches and that they're all on the same calendar date.
                elseif ($allRelevantPunches->count() >= 2 &&
                        $allRelevantPunches->every(fn($r) => $r->datetime->toDateString() === $shiftDayDate->toDateString()))
                {
                    // Check if the standard pair was NOT valid (Rule 1 didn't match)
                    $standardPairFailed = !$firstInRecordStandard || !$lastOutRecordStandard || 
                                          ($firstInRecordStandard && $lastOutRecordStandard && $clockOutTime->lessThanOrEqualTo($clockInTime));

                    if ($standardPairFailed) {
                        // Standard pairing failed, but all punches are on the same calendar date.
                        // Apply FILO to *all relevant punches* within this shift day group (which are all on the same date here).
                        // This captures the overall boundary for the day shift due to human error.
                        $firstPunchRecord = $allRelevantPunches->first();
                        $lastPunchRecord = $allRelevantPunches->last();

                        $firstPunchTime = $firstPunchRecord->datetime;
                        $lastPunchTime = $lastPunchRecord->datetime;
                        $firstPunchId = $firstPunchRecord->id;
                        $lastPunchId = $lastPunchRecord->id;

                        if ($lastPunchTime->greaterThan($firstPunchTime)) {
                            // Found a valid time range using overall first/last punch on the same date.
                            $hoursWorked = $lastPunchTime->floatDiffInHours($firstPunchTime);
                            $isComplete = true; // Consider complete if overall boundary is valid
                            $shiftType = 'human_error_day'; // Specific type for this scenario

                            // Use the IDs of the overall first and last punches for traceability
                            $clockInAttendanceId = $firstPunchId;
                            $clockOutAttendanceId = $lastPunchId;
                            $clockInTime = $firstPunchTime;
                            $clockOutTime = $lastPunchTime;

                            $notes = $this->generateNotes($clockInTime, $clockOutTime, $shiftType, $hoursWorked, $clockInAttendanceId, $clockOutAttendanceId);
                            $this->info("Processed human error day shift for {$employeePin} on {$shiftDayDate->toDateString()}. Hours: " . round($hoursWorked, 2) . ". Notes: {$notes}");

                            $this->createShiftRecord(
                                $employeePin, $shiftDayDate,
                                $clockInAttendanceId, $clockOutAttendanceId,
                                $clockInTime, $clockOutTime,
                                $hoursWorked, $shiftType, $isComplete, $notes
                            );
                            $shiftsProcessed++;
                        } else {
                            // Rule 6: Human error resulted in inverted times even using overall first/last punch on the same date.
                            $shiftType = 'human_error_inverted'; // Specific type for this inverted case
                            $isComplete = false;
                            $notes = $this->generateNotes($firstPunchTime, $lastPunchTime, $shiftType, 0, $firstPunchId, $lastPunchId); // HoursWorked is 0 for notes
                            $this->warn("Human error resulted in inverted times for {$employeePin} on calculated shift day {$shiftDayDate->toDateString()}. Notes: {$notes}");
                            
                            $this->createShiftRecord(
                                $employeePin, $shiftDayDate,
                                $firstPunchId, $lastPunchId,
                                $firstPunchTime, $lastPunchTime,
                                0, $shiftType, $isComplete, $notes
                            );
                            $shiftsProcessed++;
                        }
                    } else {
                        // This case should have been handled by Rule 1 (standard pair found).
                        // If we reached here, it indicates an unexpected scenario or logical flaw.
                        $this->error("Logical error: Employee {$employeePin} on calculated shift day {$shiftDayDate->toDateString()} had a standard pair, but was not handled by Rule 1.");
                        // No shift record created.
                    }
                }
                // Rule 3 (MOVED DOWN): Standard Inverted Pair ('1%' First, '2%' Last, Out <= In)
                // Only applies now if the Human Error on Same Calendar Date rule didn't apply
                elseif ($firstInRecordStandard && $lastOutRecordStandard && $clockOutTime->lessThanOrEqualTo($clockInTime)) {
                    $shiftType = 'inverted_times';
                    $isComplete = false; // It's not a complete valid shift

                    $notes = $this->generateNotes($clockInTime, $clockOutTime, $shiftType, 0, $clockInAttendanceId, $clockOutAttendanceId); // HoursWorked is 0 for notes

                    $this->warn("Inverted times found for employee {$employeePin} on calculated shift day {$shiftDayDate->toDateString()}. Clock-in: {$clockInTime}, Clock-out: {$clockOutTime}. Notes: {$notes}");

                    // Create a record to explicitly log this data issue.
                    $this->createShiftRecord(
                        $employeePin, $shiftDayDate,
                        $clockInAttendanceId, $clockOutAttendanceId,
                        $clockInTime, $clockOutTime,
                        0, // Hours Worked is 0 for an invalid pair
                        'inverted_times', // Indicate the type of issue
                        false, // Mark as incomplete
                        $notes
                    );
                    $shiftsProcessed++; // Count this as a processed shift record (logging the issue)
                }
                // Rule 4: Missing Clock-out, with Next Day Lookahead Check
                elseif ($firstInRecordStandard && !$lastOutRecordStandard) {
                    // Found a First Clock-in ('1%') for this shift day, but no Last Clock-out ('2%') within the same calculated shift day group.
                    // Now, specifically look for a clock-out on the *next calendar day* up to the lookahead hour.

                    // Define the lookahead window start (just after the clock-in time) and end (next calendar day at lookahead hour).
                    $lookaheadStart = $clockInTime->copy()->addSecond();
                    $lookaheadEnd = $shiftDayDate->copy()->addDay()->startOfDay()->addHours(self::MISSING_CLOCKOUT_LOOKAHEAD_HOUR); // End of lookahead window

                    // Fetch only the clock-out records for this employee within the lookahead window.
                    $lookaheadClockOut = Attendance::where('pin', '2' . $employeePin) // Look for clock-out pin
                        ->whereBetween('datetime', [$lookaheadStart, $lookaheadEnd]) // Look *after* the clock-in time, up to the lookahead end
                        ->orderBy('datetime') // Get the earliest one in the lookahead window
                        ->first();

                    if ($lookaheadClockOut) {
                        // Found a matching clock-out in the lookahead window on the next calendar day.
                        // This is likely a night shift ending the next day.
                        $clockOutTime = $lookaheadClockOut->datetime;
                        $clockOutAttendanceId = $lookaheadClockOut->id;

                        // Ensure the lookahead clock-out time is after the clock-in time (should be, given the fetch range, but safety check)
                        if ($clockOutTime->greaterThan($clockInTime)) {
                            $hoursWorked = $clockOutTime->floatDiffInHours($clockInTime);
                            $isComplete = true;
                            $shiftType = 'night'; // It spans to the next day, so it's a night shift

                            $notes = $this->generateNotes($clockInTime, $clockOutTime, $shiftType, $hoursWorked, $clockInAttendanceId, $clockOutAttendanceId);

                            $this->info("Processed missing clock-out (found next day) for {$employeePin} on calculated shift day {$shiftDayDate->toDateString()}. Type: Night, Hours: " . round($hoursWorked, 2));

                            $this->createShiftRecord(
                                $employeePin, $shiftDayDate, // Shift date is still the calculated shift day
                                $clockInAttendanceId, $clockOutAttendanceId,
                                $clockInTime, $clockOutTime,
                                $hoursWorked, $shiftType, $isComplete, $notes
                            );
                            $shiftsProcessed++;
                        } else {
                            // Unexpected scenario: Lookahead found an earlier or same time clock-out. Log as error.
                            $shiftType = 'lookahead_inverted';
                            $isComplete = false;
                            $notes = $this->generateNotes($clockInTime, $clockOutTime, $shiftType, 0, $clockInAttendanceId, $clockOutAttendanceId);
                            $this->error("Lookahead found inverted time for employee {$employeePin} on {$shiftDayDate->toDateString()}. In: {$clockInTime}, Lookahead Out: {$clockOutTime}. Notes: {$notes}");

                            // Create a record to log this specific lookahead issue
                            $this->createShiftRecord(
                                $employeePin, $shiftDayDate,
                                $clockInAttendanceId, $clockOutAttendanceId,
                                $clockInTime, $clockOutTime,
                                0, // Hours Worked
                                $shiftType, // 'lookahead_inverted'
                                $isComplete, // false
                                $notes
                            );
                            $shiftsProcessed++;
                        }
                    } else {
                        // Still missing clock-out even after the lookahead check.
                        // This is a genuinely incomplete shift or a long shift ending later than the lookahead.
                        $shiftType = 'missing_clockout';
                        $isComplete = false; // Still incomplete
                        $notes = $this->generateNotes($clockInTime, null, $shiftType, null, $clockInAttendanceId, null);
                        $this->warn("Missing clock-out for employee {$employeePin} on calculated shift day {$shiftDayDate->toDateString()} after lookahead. Notes: {$notes}");

                        $this->createShiftRecord(
                            $employeePin, $shiftDayDate,
                            $clockInAttendanceId, null, // No clock-out record ID found
                            $clockInTime, null, // No clock-out time found
                            null, $shiftType, $isComplete, $notes
                        );
                        $shiftsProcessed++;
                    }
                }
                // Rule 5: Missing Clock-in (Standard)
                elseif (!$firstInRecordStandard && $lastOutRecordStandard) {
                    // Found a Last Clock-out ('2%') but no First Clock-in ('1%') for this shift day using standard pairing.
                    $shiftType = 'missing_clockin';
                    $isComplete = false;
                    $notes = $this->generateNotes(null, $clockOutTime, $shiftType, null, null, $clockOutAttendanceId);
                    $this->warn("Missing clock-in for employee {$employeePin} on calculated shift day {$shiftDayDate->toDateString()}. Notes: {$notes}");

                    $this->createShiftRecord(
                        $employeePin, $shiftDayDate,
                        null, $clockOutAttendanceId, // No clock-in record ID found
                        null, $clockOutTime, // No clock-in time found
                        null, $shiftType, $isComplete, $notes
                    );
                    $shiftsProcessed++;
                }
                // Implicit Case: No relevant punches found for this shift day group or other unexpected combinations not covered by the rules.
                else {
                    // Handle any other unexpected scenarios or lack of relevant punches
                    if ($recordsForTargetShiftDay->count() > 0) {
                        // There were records, but they didn't fit any of the defined rules.
                        $this->warn("Unhandled punch pattern for employee {$employeePin} in group for calculated shift day {$shiftDayDate->toDateString()}. Total records in group: {$recordsForTargetShiftDay->count()}.");
                    } else {
                        // This case should ideally be caught by the initial check for the group's existence,
                        // but defensive logging is good.
                        $this->info("No relevant punches found for employee {$employeePin} in group for calculated shift day {$shiftDayDate->toDateString()}. Skipping.");
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
        // If the hour is before the cutoff hour, subtract one day to get the shift day.
        // Otherwise, the shift day is the same calendar day as the datetime.
        return $datetime->copy()->hour < self::SHIFT_CUTOFF_HOUR
            ? $datetime->copy()->subDay()->startOfDay() // Shift day is the previous calendar day (e.g., 2025-04-18 00:00:00 for a punch at 2025-04-19 03:00:00 with 4 AM cutoff)
            : $datetime->copy()->startOfDay(); // Shift day is the current calendar day (e.g., 2025-04-19 00:00:00 for a punch at 2025-04-19 05:00:00 with 4 AM cutoff)
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
        ?string $notes
    ): EmployeeShift
    {
        return EmployeeShift::updateOrCreate(
            [
                'employee_pin' => $employeePin,
                'shift_date' => $shiftDate->toDateString(), // Use the calculated shift day
            ],
            [
                'clock_in_attendance_id' => $clockInAttendanceId,
                'clock_out_attendance_id' => $clockOutAttendanceId,
                'clock_in_time' => $clockInTime,
                'clock_out_time' => $clockOutTime,
                'hours_worked' => $hoursWorked,
                'shift_type' => $shiftType,
                'is_complete' => $isComplete,
                'notes' => $notes
            ]
        );
    }

    /**
     * Generates a notes string for a shift record based on its type, duration, and times.
     * Includes attendance record IDs for traceability.
     *
     * @param Carbon|null $clockInTime
     * @param Carbon|null $clockOutTime
     * @param string $shiftType The type of shift (e.g., 'day', 'night', 'missing_clockin', 'inverted_times', 'human_error_day', 'human_error_inverted').
     * @param float|null $hoursWorked
     * @param int|null $clockInAttendanceId The ID of the attendance record used as clock-in.
     * @param int|null $clockOutAttendanceId The ID of the attendance record used as clock-out.
     * @return string|null
     */
    protected function generateNotes(?Carbon $clockInTime, ?Carbon $clockOutTime, string $shiftType, ?float $hoursWorked, ?int $clockInAttendanceId, ?int $clockOutAttendanceId): ?string
    {
        $notes = [];

        // Basic type notes
        switch ($shiftType) {
            case 'day':
                $notes[] = 'Day shift';
                break;
            case 'night':
                $notes[] = 'Night shift';
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

        // Duration-based notes (only for shifts where hours worked is calculated and makes sense)
        if ($hoursWorked !== null && !in_array($shiftType, ['inverted_times', 'lookahead_inverted', 'human_error_inverted'])) {
            if ($hoursWorked > 14) { // Example threshold for unusually long shift
                $notes[] = 'Unusually long duration (' . round($hoursWorked, 1) . ' hours)';
            } elseif ($hoursWorked > 0 && $hoursWorked < 1) { // Example threshold for very short duration
                $notes[] = 'Very short duration (' . round($hoursWorked * 60) . ' minutes)';
            }
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

        // Combine notes
        return !empty($notes) ? implode('; ', $notes) : null;
    }
}