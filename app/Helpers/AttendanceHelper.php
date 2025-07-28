<?php

namespace App\Helpers;

use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceHelper
{
//     This module is a Collection of Static Utility Functions and Constants related to attendance. It provides reusable, pure functions that perform specific calculations or data manipulations.

// Handles:

// Constant Definitions: Stores all core shift definitions (start/end hours), buffer times, and punch window thresholds.

// Punch Filtering/Selection: Provides methods to filter punches by date, type, and used status (getPunches).

// Duplicate Punch Removal: Implements the logic for filterDuplicatePunches to clean raw punch data.

// Punch Usage Tracking: Provides markPunchesAsUsed to update the usedAttendanceIds collection.

// Shift Type Determination: Contains the logic to categorize a complete shift as 'day', 'night', 'irregular_sameday', 'irregular_crossday'.

// Lateness Calculation: Computes lateness based on expected vs. actual clock-in times.

// Overtime and Regular Hours Calculation: Implements the detailed minute-by-minute logic to correctly allocate hours across regular, 1.5x, and 2.0x overtime, considering weekends and holidays.

// Notes Generation: Formats comprehensive human-readable notes for EmployeeShift records.

// Holiday Check: Determines if a given date is a holiday using a cached lookup.

// Does NOT Handle:

// Any database interactions beyond a simple Holiday lookup.

// The overall flow of processing an employee's shifts.

// Determining which punches form a shift (that's AttendanceProcessor's job).
    // --- Shift Definitions (Constants for easy configuration) ---
    const DAY_SHIFT_START_HOUR = 7;
    const DAY_SHIFT_START_MINUTE = 0;
    const DAY_SHIFT_END_HOUR = 18;
    const DAY_SHIFT_END_MINUTE = 0;

    const NIGHT_SHIFT_START_HOUR = 18;
    const NIGHT_SHIFT_START_MINUTE = 0;
    const NIGHT_SHIFT_END_HOUR = 7;
    const NIGHT_SHIFT_END_MINUTE = 0;

    // Buffers/thresholds for identifying shift boundaries and duplicates
    const PREV_DAY_NIGHT_IN_AFTER_HOUR = 17; // Clock-in on previous day after this hour is potential night shift
    const TARGET_DAY_NIGHT_OUT_BEFORE_HOUR = 8; // Clock-out on target day before this hour is potential night shift end
    const NEXT_DAY_CLOCKOUT_LOOKAHEAD_HOUR = 10; // For night shifts, look for clock-out up to this hour on the next day
    const DAY_SHIFT_START_BUFFER_MINUTES = 59; // Allow clock-in up to this many minutes before standard day shift start
    const DUPLICATE_PUNCH_WINDOW_MINUTES = 10; // Global buffer for considering punches of the same type as duplicates
    const MIN_HOURS_FOR_SAME_PIN_SHIFT = 4; // Threshold for detecting large gaps between same-type punches (human error)

    /**
     * Filters out duplicate consecutive punches of the same type (e.g., multiple clock-ins in a short period).
     * Keeps the first 'in' in a close cluster and the last 'out' in a close cluster.
     * This is robust to prevent issues from rapid, repeated punches.
     *
     * @param Collection $allPunches Raw punches for an employee, sorted by datetime.
     * @param string $employeePin Employee PIN for logging purposes (for info/warn messages).
     * @param callable|null $logger A callable function for logging messages (e.g., $this->info or $this->warn).
     * @return Collection Cleaned collection of punches.
     */
    public static function filterDuplicatePunches(Collection $allPunches, string $employeePin, ?callable $logger = null): Collection
    {
        $filtered = new Collection();
        $lastIn = null;
        $lastOut = null;

        foreach ($allPunches as $punch) {
            $isClockIn = str_starts_with($punch->pin, '1');
            $isClockOut = str_starts_with($punch->pin, '2');

            if ($isClockIn) {
                if ($lastIn && $punch->datetime->diffInMinutes($lastIn->datetime) <= self::DUPLICATE_PUNCH_WINDOW_MINUTES) {
                    if ($logger) {
                        $logger("info", "Ignoring duplicate clock-in [ID:{$punch->id}] for PIN {$employeePin} at {$punch->datetime->toDateTimeString()} (within " . self::DUPLICATE_PUNCH_WINDOW_MINUTES . " min of previous IN).");
                    }
                    continue; // Skip adding this punch
                }
                $filtered->push($punch);
                $lastIn = $punch;
                $lastOut = null; // Reset lastOut as an IN punch just occurred, starting a new potential sequence
            } elseif ($isClockOut) {
                if ($lastOut && $punch->datetime->diffInMinutes($lastOut->datetime) <= self::DUPLICATE_PUNCH_WINDOW_MINUTES) {
                    if ($logger) {
                        $logger("info", "Replacing previous clock-out [ID:{$lastOut->id}] with later clock-out [ID:{$punch->id}] for PIN {$employeePin} at {$punch->datetime->toDateTimeString()}.");
                    }
                    $filtered->pop(); // Remove the previous 'lastOut' from the filtered collection
                    $filtered->push($punch); // Add the current (later) 'out'
                    $lastOut = $punch;
                } else {
                    $filtered->push($punch);
                    $lastOut = $punch;
                }
                $lastIn = null; // Reset lastIn as an OUT punch just occurred, starting a new potential sequence
            } else {
                if ($logger) {
                    $logger("warn", "Unexpected punch PIN format for {$employeePin}: {$punch->pin} at {$punch->datetime->toDateTimeString()}. Adding without type assumption.");
                }
                $filtered->push($punch);
                $lastIn = null;
                $lastOut = null;
            }
        }
        return $filtered->sortBy('datetime'); // Ensure chronological order after filtering
    }

    /**
     * Filters punches from a given collection based on day, type, and whether they've been used.
     * This helper ensures we only consider relevant and unused punches.
     *
     * @param Collection $allPunches The comprehensive collection of employee punches (already filtered for duplicates).
     * @param Carbon $day The specific day to filter punches for.
     * @param string|null $type 'in', 'out', or null for any type.
     * @param Collection $usedAttendanceIds A collection of IDs of punches already used in a shift.
     * @return Collection Filtered punches.
     */
    public static function getPunches(Collection $allPunches, Carbon $day, ?string $type, Collection $usedAttendanceIds): Collection
    {
        return $allPunches->filter(function ($punch) use ($day, $type, $usedAttendanceIds) {
            // Immediately exclude if the punch has already been used in another shift
            if ($usedAttendanceIds->contains($punch->id)) {
                return false;
            }

            // Check if the punch falls on the specified day
            if (!$punch->datetime->isSameDay($day)) {
                return false;
            }

            // Check punch type if specified ('in' for '1XXXX', 'out' for '2XXXX')
            if ($type === 'in' && !str_starts_with($punch->pin, '1')) {
                return false;
            }
            if ($type === 'out' && !str_starts_with($punch->pin, '2')) {
                return false;
            }

            return true; // Punch meets all criteria
        });
    }

    /**
     * Helper to mark all punches within a given time range for an employee as 'used'.
     * This prevents these punches from being considered for other shifts.
     *
     * @param Collection $allEmployeePunches All punches for the employee (pre-filtered).
     * @param Carbon $startTime The start time of the shift.
     * @param Carbon $endTime The end time of the shift.
     * @param Collection $usedAttendanceIds The collection of used attendance IDs to update.
     */
    public static function markPunchesAsUsed(Collection $allEmployeePunches, Carbon $startTime, Carbon $endTime, Collection &$usedAttendanceIds)
    {
        $allPunchesWithinShift = $allEmployeePunches->filter(function ($punch) use ($startTime, $endTime) {
            return $punch->datetime->gte($startTime) && $punch->datetime->lte($endTime);
        });
        $allPunchesWithinShift->pluck('id')->each(fn ($id) => $usedAttendanceIds->push($id));
        $usedAttendanceIds = $usedAttendanceIds->unique()->values(); // Ensure unique IDs and reset keys
    }

    /**
     * Determines the primary type of shift (day, night, irregular, etc.) based on clock-in/out times
     * relative to defined shift windows.
     *
     * @param Carbon $clockInTime The actual clock-in time.
     * @param Carbon $clockOutTime The actual clock-out time.
     * @param Carbon $shiftActualStartDate The calendar date the shift actually started on.
     * @return string The determined shift type.
     */
    public static function determineShiftType(Carbon $clockInTime, Carbon $clockOutTime, Carbon $shiftActualStartDate): string
    {
        // Define standard shift boundaries for the actual shift start date
        $coreDayShiftStart = $shiftActualStartDate->copy()->setTime(self::DAY_SHIFT_START_HOUR, self::DAY_SHIFT_START_MINUTE);   // 07:00
        $coreNightShiftStart = $shiftActualStartDate->copy()->setTime(self::NIGHT_SHIFT_START_HOUR, self::NIGHT_SHIFT_START_MINUTE); // 18:00

        // Define buffered start times to account for early clock-ins within reasonable limits
        $bufferedDayShiftStart = $coreDayShiftStart->copy()->subMinutes(self::DAY_SHIFT_START_BUFFER_MINUTES); // e.g., 06:01 if buffer is 59 min
        $bufferedNightShiftStart = $coreNightShiftStart->copy()->subMinutes(self::DAY_SHIFT_START_BUFFER_MINUTES); // e.g., 17:01

        // Define expected night shift end time on the *next* day, plus a lookahead buffer
        $expectedNightShiftEndNextDay = $shiftActualStartDate->copy()->addDay()->setTime(self::NIGHT_SHIFT_END_HOUR, self::NIGHT_SHIFT_END_MINUTE);
        $bufferedNightShiftEnd = $expectedNightShiftEndNextDay->copy()->addHours(self::NEXT_DAY_CLOCKOUT_LOOKAHEAD_HOUR - self::NIGHT_SHIFT_END_HOUR); // e.g., extends to 10:00 AM next day

        // Logic for same-day shifts
        if ($clockInTime->isSameDay($clockOutTime)) {
            // If clock-in falls within the day shift window (considering buffer)
            if ($clockInTime->greaterThanOrEqualTo($bufferedDayShiftStart) && $clockInTime->lt($coreNightShiftStart)) {
                return 'day';
            }
            return 'irregular_sameday'; // Any other same-day shift (e.g., very late start, very early end)
        } else { // Logic for cross-day shifts
            // If clock-in falls within night shift window (considering buffer) AND
            // clock-out falls within the night shift end window on the next day
            if ($clockInTime->greaterThanOrEqualTo($bufferedNightShiftStart) && $clockOutTime->lessThanOrEqualTo($bufferedNightShiftEnd)) {
                return 'night';
            }
            return 'irregular_crossday'; // Any other cross-day shift
        }
    }

    /**
     * Calculates lateness in minutes for a clock-in against an expected standard shift start.
     * Lateness is only calculated for standard day/night shifts, not weekends/holidays or irregular shifts.
     *
     * @param Carbon $clockInTime The actual clock-in time.
     * @param string $shiftType The determined shift type ('day' or 'night').
     * @param Carbon $shiftActualStartDate The calendar date the shift actually started on.
     * @param bool $isPrevDayNightShift True if this is a night shift that started on the previous day.
     * @return int Lateness in minutes, 0 if not late or not applicable.
     */
    public static function calculateLateness(Carbon $clockInTime, string $shiftType, Carbon $shiftActualStartDate, bool $isPrevDayNightShift): int
    {
        // No lateness calculated for weekend or holiday shifts
        if (self::isHoliday($shiftActualStartDate) || $shiftActualStartDate->isWeekend()) {
            return 0;
        }

        $expectedStart = null;

        if ($shiftType === 'day') {
            $expectedStart = $shiftActualStartDate->copy()->setTime(self::DAY_SHIFT_START_HOUR, self::DAY_SHIFT_START_MINUTE);
        } elseif ($shiftType === 'night') {
            // For night shifts, the expected start depends on whether it's a "previous day" night in
            // (meaning it's an overnight shift, usually clocking in late afternoon/evening)
            // or a "current day" night in (starting within the night shift block).
            $expectedHour = $isPrevDayNightShift ? self::PREV_DAY_NIGHT_IN_AFTER_HOUR : self::NIGHT_SHIFT_START_HOUR;
            $expectedStart = $shiftActualStartDate->copy()->setTime($expectedHour, 0); // Assuming 0 minutes for simplicity if PREV_DAY_NIGHT_IN_AFTER_HOUR is just an hour threshold
        } else {
            return 0; // No lateness for irregular shifts or incomplete records
        }

        // Calculate lateness only if the actual clock-in is strictly after the expected start
        return $expectedStart && $clockInTime->greaterThan($expectedStart) ? $clockInTime->diffInMinutes($expectedStart) : 0;
    }

    /**
     * Calculates regular hours and overtime hours (1.5x, 2.0x) for a shift.
     * This method processes hours minute by minute for precise allocation across different pay rates
     * and calendar days.
     *
     * @param float $totalHoursWorkedInitially The total duration of the shift as a float.
     * @param Carbon $clockInTime The shift's clock-in time.
     * @param Carbon $clockOutTime The shift's clock-out time.
     * @param Carbon $shiftActualStartDate The calendar date the shift started.
     * @param string $shiftType The determined type of the shift.
     * @param bool $isSaturdayBasedOnActualStart True if the shift started on a Saturday.
     * @param bool $isSundayOrHolidayBasedOnActualStart True if the shift started on a Sunday or holiday.
     * @return array An array containing [overtime1_5x, overtime2_0x, regularHours].
     */
    public static function calculateOvertimeAndHours(
        float $totalHoursWorkedInitially,
        Carbon $clockInTime,
        Carbon $clockOutTime,
        Carbon $shiftActualStartDate,
        string $shiftType,
        bool $isSaturdayBasedOnActualStart,
        bool $isSundayOrHolidayBasedOnActualStart
    ): array {
        if ($totalHoursWorkedInitially <= 0) {
            return [0.0, 0.0, 0.0];
        }

        $overtime1_5x = 0.0;
        $overtime2_0x = 0.0;
        $regularHours = 0.0;

        $currentDt = $clockInTime->copy();
        $calendarDay = $currentDt->copy()->startOfDay(); // Initialize current calendar day for the segment

        while ($currentDt < $clockOutTime) {
            $segmentEndDt = $currentDt->copy()->addMinute(); // Process in minute segments
            if ($segmentEndDt > $clockOutTime) {
                $segmentEndDt = $clockOutTime->copy(); // Ensure last segment doesn't overshoot
            }

            $segmentDurationHours = $segmentEndDt->diffInSeconds($currentDt) / 3600.0;
            if ($segmentDurationHours <= 0) { // Should prevent infinite loops or issues with identical timestamps
                $currentDt = $segmentEndDt;
                continue;
            }

            // Update calendarDay only when the day changes for the current segment
            $newCalendarDay = $currentDt->copy()->startOfDay();
            if (!$calendarDay->equalTo($newCalendarDay)) {
                $calendarDay = $newCalendarDay;
            }

            $isHolidaySegment = self::isHoliday($calendarDay);
            $hourRateAppliedToOvertime = false; // Flag to ensure a segment is only classified once

            // Priority 1: 2.0x Overtime for Sunday or Holiday hours (on the actual segment day)
            if ($calendarDay->isSunday() || $isHolidaySegment) {
                $overtime2_0x += $segmentDurationHours;
                $hourRateAppliedToOvertime = true;
            }
            // Priority 2: 1.5x Overtime for Saturday hours (on the actual segment day), if not already 2.0x
            elseif ($calendarDay->isSaturday()) { // No need to check !$isHolidaySegment here due to prior if
                $overtime1_5x += $segmentDurationHours;
                $hourRateAppliedToOvertime = true;
            }

            // If not already applied to overtime based on the calendar day type (i.e., it's a weekday segment)
            if (!$hourRateAppliedToOvertime) {
                $isRegularSegmentHour = true; // Assume regular unless it's specific shift overtime

                // Day shift specific overtime: hours worked after standard day shift end time on the start day
                if ($shiftType === 'day' && $calendarDay->isSameDay($shiftActualStartDate)) {
                    $dayShiftStandardEnd = $shiftActualStartDate->copy()->setTime(self::DAY_SHIFT_END_HOUR, self::DAY_SHIFT_END_MINUTE);
                    if ($currentDt->greaterThanOrEqualTo($dayShiftStandardEnd)) {
                        $overtime1_5x += $segmentDurationHours;
                        $isRegularSegmentHour = false;
                    }
                }
                // Night shift specific overtime: hours worked after standard night shift end time (on the next day)
                elseif ($shiftType === 'night') {
                    $nightShiftStandardEndDay = $shiftActualStartDate->copy()->addDay(); // The calendar day the night shift is *expected* to end
                    $nightShiftStandardEndTime = $nightShiftStandardEndDay->copy()->setTime(self::NIGHT_SHIFT_END_HOUR, self::NIGHT_SHIFT_END_MINUTE);

                    // If segment falls on the night shift's expected end day AND after standard night shift end time
                    if ($calendarDay->isSameDay($nightShiftStandardEndDay) && $currentDt->greaterThanOrEqualTo($nightShiftStandardEndTime)) {
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

        // Final reconciliation: Recalculate regular hours to ensure sum of parts equals the total initially calculated duration.
        // This helps mitigate floating-point inaccuracies and ensures consistency.
        $calculatedTotalOvertime = $overtime1_5x + $overtime2_0x;
        $derivedRegularHours = $totalHoursWorkedInitially - $calculatedTotalOvertime;

        // Ensure regular hours are not negative due to floating-point math, and round precisely.
        $regularHours = max(0, round($derivedRegularHours, 2));

        return [
            round($overtime1_5x, 2), // Round overtime values for storage
            round($overtime2_0x, 2),
            $regularHours,
        ];
    }

    /**
     * Generates a concatenated string of comprehensive notes for the shift record,
     * including shift type, times, hours, lateness, and overtime details.
     *
     * @param Carbon $clockInTime
     * @param Carbon $clockOutTime
     * @param string $shiftType
     * @param float $hoursWorked
     * @param bool $hasHumanError True if general human error patterns were detected.
     * @param int $latenessMinutes Lateness in minutes.
     * @param float $overtime1_5x Hours at 1.5x rate.
     * @param float $overtime2_0x Hours at 2.0x rate.
     * @param bool $isHumanErrorOverride True if the shift was inferred from specific human error patterns (IN-IN, OUT-OUT).
     * @param string $anomalyNote Optional anomaly note to prepend.
     * @return string Formatted notes string.
     */
    public static function generateNotes(
        ?Carbon $clockInTime,
        ?Carbon $clockOutTime,
        string $shiftType,
        float $hoursWorked,
        bool $hasHumanError = false,
        int $latenessMinutes = 0,
        float $overtime1_5x = 0,
        float $overtime2_0x = 0,
        bool $isHumanErrorOverride = false,
        string $anomalyNote = ''
    ): string {
        $notes = [];
        if (!empty($anomalyNote)) {
            $notes[] = $anomalyNote;
        }
        $notes[] = "Type: " . ucfirst(str_replace('_', ' ', $shiftType)); // e.g., "Day", "Night", "Irregular Sameday"

        if ($clockInTime) {
            $notes[] = "In: {$clockInTime->toDateTimeString()}";
        }
        if ($clockOutTime) {
            $notes[] = "Out: {$clockOutTime->toDateTimeString()}";
        }

        // Only show total hours if it's a complete shift with non-zero hours
        if ($hoursWorked > 0 && $clockInTime && $clockOutTime) {
            $notes[] = "Total Hours: " . round($hoursWorked, 2);
        }

        if ($latenessMinutes > 0) {
            $notes[] = "Late: {$latenessMinutes} min";
        }
        if ($overtime1_5x > 0) {
            $notes[] = "OT 1.5x: " . round($overtime1_5x, 2) . " hrs";
        }
        if ($overtime2_0x > 0) {
            $notes[] = "OT 2.0x: " . round($overtime2_0x, 2) . " hrs";
        }
        if ($isHumanErrorOverride) {
            $notes[] = "Human Error: Inferred from same punch type sequence.";
        }
        if ($hasHumanError) {
            $notes[] = "Human Error Flagged (potential large gap between punches).";
        }

        return implode('; ', $notes); // Use semicolon and space for better readability
    }


    /**
     * Checks if a given date is a holiday.
     * Caches results in a static array for performance across multiple calls within the same command execution.
     *
     * @param Carbon $date The date to check.
     * @return bool True if the date is a holiday, false otherwise.
     */
    public static function isHoliday(Carbon $date): bool
    {
        static $holidaysCache = [];
        $dateString = $date->toDateString();

        if (isset($holidaysCache[$dateString])) {
            return $holidaysCache[$dateString];
        }

        // Assuming Holiday model has 'start_date' and 'description' columns
        // and 'Public holiday' in description is the indicator.
        $isActualHoliday = Holiday::whereDate('start_date', $date->toDateString())
                                ->where('description', 'LIKE', '%Public holiday%')
                                ->exists();
        $holidaysCache[$dateString] = $isActualHoliday;

        return $isActualHoliday;
    }
}