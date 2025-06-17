<?php
namespace App\Helpers;

use App\Models\Holiday;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class DateHelper
{
    /**
     * Calculates business days based on the arguments provided.
     *
     * Can be called in three ways:
     * 1. getBusinessDays(int $year, int $month) - Gets total business days for that entire month.
     * 2. getBusinessDays(string $date)          - Gets business days from start of that month to the given date.
     * 3. getBusinessDays()                      - Gets total business days for the current month.
     *
     * @param mixed ...$args The arguments for calculation.
     * @return int The number of business days.
     */
    public static function getBusinessDays(...$args): int
    {
        $start = null;
        $end   = null;

        // NEW: Handle different ways of calling the function
        switch (count($args)) {
            case 2:
                // Scenario: getBusinessDays(2025, 5)
                // Calculates for the entire month of May 2025.
                $year  = $args[0];
                $month = $args[1];
                $start = Carbon::create($year, $month)->startOfMonth();
                $end   = Carbon::create($year, $month)->endOfMonth();
                break;

            case 1:
                // Scenario: getBusinessDays('2025-05-15') or getBusinessDays(today())
                // If a date is passed, count from start of that month to that date.
                $date  = $args[0];
                $start = Carbon::parse($date)->startOfMonth();
                $end   = Carbon::parse($date)->endOfDay(); // Include the full passed day
                break;

            case 0:
                // Scenario: getBusinessDays()
                // If no date, get the full current month.
                $start = Carbon::now()->startOfMonth();
                $end   = Carbon::now()->endOfMonth();
                break;

            default:
                throw new InvalidArgumentException('getBusinessDays accepts 0, 1, or 2 arguments.');
        }

        Log::debug("Calculating business days from {$start->toDateString()} to {$end->toDateString()}");

        $excludedDates = self::getHolidayDatesInRange($start, $end);
        Log::debug('Excluded (holiday) dates: ' . json_encode($excludedDates->values()));

        $period        = CarbonPeriod::create($start, $end);
        $businessDates = collect();

        foreach ($period as $date) {
            $dateStr = $date->toDateString();
            if ($date->isWeekend()) {
                Log::debug("Skipping weekend: {$dateStr}");
                continue;
            }
            if (! $excludedDates->contains($dateStr)) {
                $businessDates->push($dateStr);
            }
        }

        Log::debug('Included (business) dates: ' . json_encode($businessDates));
        Log::debug('Total business days: ' . $businessDates->count());

        return $businessDates->count();
    }

    /**
     * Retrieves holiday dates within a range using the original logic.
     * NOTE: This is the original function, restored as requested.
     */
    protected static function getHolidayDatesInRange(Carbon $start, Carbon $end): Collection
    {
        $holidays = Holiday::where('description', 'Public holiday')
            ->where(function ($query) use ($start, $end) {
                $query->whereDate('start_date', '<=', $end->toDateString())
                    ->whereDate('end_date', '>=', $start->toDateString());
            })
            ->get();

        Log::debug("Matching holidays from DB:", $holidays->map(function ($holiday) {
            return [
                'summary'    => $holiday->summary,
                'start_date' => $holiday->start_date,
                'end_date'   => $holiday->end_date,
            ];
        })->toArray());

        $dates = collect();

        foreach ($holidays as $holiday) {
            $holidayStartDate = Carbon::parse($holiday->start_date)->startOfDay();
            $holidayEndDate   = Carbon::parse($holiday->end_date)->startOfDay();
            $periodToExclude  = null;

            // --- Logic: Treat ANY multi-day holiday as a single day exclusion ---
            if ($holidayStartDate->lt($holidayEndDate)) {
                $periodToExclude = CarbonPeriod::create($holidayStartDate, $holidayStartDate);
                Log::debug("Overriding multi-day holiday ('{$holiday->summary}') primary exclusion to start day only: " . $holidayStartDate->toDateString());
            } else {
                $periodToExclude = CarbonPeriod::create($holidayStartDate, $holidayEndDate);
                Log::debug("Using default holiday exclusion period ('{$holiday->summary}'): {$holidayStartDate->toDateString()} to {$holidayEndDate->toDateString()}");
            }

            if ($periodToExclude) {
                foreach ($periodToExclude as $date) {
                    $dates->push($date->toDateString());
                }
            }

            // --- Carry-forward logic: If holiday starts on Sunday 30/31, also exclude Monday ---
            if ($holidayStartDate->isSunday() && in_array($holidayStartDate->day, [30, 31])) {
                $nextDay = $holidayStartDate->copy()->addDay()->startOfDay();
                if ($nextDay->between($start->startOfDay(), $end->startOfDay(), true)) {
                    $dates->push($nextDay->toDateString());
                    Log::debug("Carry-forward applied: '{$holiday->summary}' starts on Sunday ({$holidayStartDate->toDateString()}), also excluding: " . $nextDay->toDateString());
                } else {
                    Log::debug("Carry-forward condition met for '{$holiday->summary}', but next day ({$nextDay->toDateString()}) is outside report range ({$start->toDateString()} - {$end->toDateString()}).");
                }
            } else {
                Log::debug("Carry-forward (start date check) condition not met for '{$holiday->summary}' (Start date {$holidayStartDate->toDateString()} is not Sunday 30/31).");
            }
            // ----------------------------------------------------------
        }

        return $dates->unique();
    }
}
