<?php
namespace App\Utilities;

use Illuminate\Support\Collection;

class ShiftAnomalyDetector
{
// This module is a Specialized Rule Engine for identifying specific unusual patterns or durations in attendance data.

// Handles:

// Anomaly-Specific Constants: Defines thresholds like MAX_SHIFT_DURATION_HOURS, MINIMUM_SHIFT_DURATION_MINUTES, HUMAN_ERROR_PUNCH_GAP_HOURS, ACCIDENTAL_DOUBLE_PUNCH_MINUTES.

// Duration-Based Anomaly Checks: Provides methods to check if a shift is isUnusuallyLongShift or isTooShortShift.

// Accidental Double Punch Detection: Specifically identifies very short shifts resulting from inferred human errors (isAccidentalDoublePunch).

// General Human Error Pattern Detection: Detects detectHumanError by looking for large gaps between multiple punches of the same type on a single day, indicating potential forgotten punches or system errors.

// Does NOT Handle:

// Creating shift records.

// Core shift calculation (lateness, overtime).

// Fetching or managing attendance records.
    const MAX_SHIFT_DURATION_HOURS        = 16;
    const MINIMUM_SHIFT_DURATION_MINUTES  = 15;
    const HUMAN_ERROR_PUNCH_GAP_HOURS     = 1; // Used for general human error detection (e.g., long gap between same-type punches)
    const ACCIDENTAL_DOUBLE_PUNCH_MINUTES = 5; // Very short duration for human-error derived shifts

    /**
     * Checks if a shift's duration is unusually long.
     *
     * @param float $hoursWorked
     * @return bool
     */
    public static function isUnusuallyLongShift(float $hoursWorked): bool
    {
        return $hoursWorked > self::MAX_SHIFT_DURATION_HOURS;
    }

    /**
     * Checks if a shift's duration is too short to be considered valid.
     * This applies to logically complete shifts (IN-OUT).
     *
     * @param int $durationMinutes
     * @return bool
     */
    public static function isTooShortShift(int $durationMinutes): bool
    {
        return $durationMinutes < self::MINIMUM_SHIFT_DURATION_MINUTES;
    }

    /**
     * Checks if an IN-IN or OUT-OUT derived shift is extremely short, indicating an accidental double punch.
     *
     * @param int $durationMinutes
     * @return bool
     */
    public static function isAccidentalDoublePunch(int $durationMinutes): bool
    {
        return $durationMinutes < self::ACCIDENTAL_DOUBLE_PUNCH_MINUTES;
    }

    /**
     * Detects potential human error patterns in punch data, specifically large gaps between
     * punches of the same type on the same day. This examines *all* relevant punches for the day.
     *
     * @param Collection $allClockInsOnDay The collection of all clock-ins on the day to check.
     * @param Collection $allClockOutsOnDay The collection of all clock-outs on the day to check.
     * @return bool True if a human error pattern is detected.
     */
    public static function detectHumanError(Collection $allClockInsOnDay, Collection $allClockOutsOnDay): bool
    {
        // Check for significant gaps between clock-ins on the day
        if ($allClockInsOnDay->count() > 1) {
            $times = $allClockInsOnDay->pluck('datetime')->sort();
            for ($i = 1; $i < $times->count(); $i++) {
                if ($times->get($i)->diffInHours($times->get($i - 1)) > self::HUMAN_ERROR_PUNCH_GAP_HOURS) {
                    return true;
                }
            }
        }

        // Check for significant gaps between clock-outs on the day
        if ($allClockOutsOnDay->count() > 1) {
            $times = $allClockOutsOnDay->pluck('datetime')->sort();
            for ($i = 1; $i < $times->count(); $i++) {
                if ($times->get($i)->diffInHours($times->get($i - 1)) > self::HUMAN_ERROR_PUNCH_GAP_HOURS) {
                    return true;
                }
            }
        }
        return false;
    }
}
