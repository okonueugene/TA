<?php

namespace App\Helpers;

use App\Models\Holiday;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DateHelper
{
    public static function getBusinessDays(string $date = null): int
    {
        if ($date) {
            // If a date is passed, count from start of that month to that date
            $start = Carbon::parse($date)->startOfMonth();
            $end = Carbon::parse($date)->endOfDay(); // Include the full passed day
        } else {
            // If no date, get current full month
            $start = Carbon::now()->startOfMonth();
            $end = Carbon::now()->endOfMonth();
        }

        Log::debug("Calculating business days from {$start->toDateString()} to {$end->toDateString()}");

        $excludedDates = self::getHolidayDatesInRange($start, $end);
        Log::debug('Excluded (holiday) dates: ' . json_encode($excludedDates->values()));

        $period = CarbonPeriod::create($start, $end);
        $businessDates = collect();

        foreach ($period as $date) {
            $dateStr = $date->toDateString();
            if ($date->isWeekend()) {
                Log::debug("Skipping weekend: {$dateStr}");
                continue;
            }
            if (!$excludedDates->contains($dateStr)) {
                $businessDates->push($dateStr);
            }
        }

        Log::debug('Included (business) dates: ' . json_encode($businessDates));
        Log::debug('Total business days: ' . $businessDates->count());

        return $businessDates->count();
    }

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
                'summary' => $holiday->summary,
                'start_date' => $holiday->start_date,
                'end_date' => $holiday->end_date,
            ];
        })->toArray());

        $dates = collect();

        foreach ($holidays as $holiday) {
            $holidayStartDate = Carbon::parse($holiday->start_date)->startOfDay();
            $holidayEndDate = Carbon::parse($holiday->end_date)->startOfDay();
            $periodToExclude = null;

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
