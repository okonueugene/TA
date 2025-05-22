<?php

namespace App\Exports;
use App\Models\Employee;
use App\Models\Holiday;
use App\Helpers\DateHelper; // Make sure this helper is available
use Carbon\Carbon;
use Illuminate\Support\Collection; // Use Illuminate\Support\Collection for FromCollection
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class AttendanceExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
 protected $month;
    protected $year;
    protected $searchValue;
    protected $totalWorkDaysInMonth; // To pass this calculated value

    public function __construct($month, $year, $searchValue = null)
    {
        $this->month = $month;
        $this->year = $year;
        $this->searchValue = $searchValue;

        // Calculate total work days once in the constructor
        $startDate = Carbon::createFromDate($this->year, $this->month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $holidays = Holiday::whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])->get();
        $this->totalWorkDaysInMonth = DateHelper::getBusinessDays($this->year, $this->month, $holidays);
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $startDate = Carbon::createFromDate($this->year, $this->month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $query = Employee::select([
            'employees.pin',
            'employees.empname',
            'employees.empoccupation',
            'employees.team',
            DB::raw('COUNT(DISTINCT employee_shifts.shift_date) as days_present'),
            DB::raw("COUNT(CASE WHEN employee_shifts.shift_type = 'day' THEN 1 ELSE NULL END) as day_shifts"),
            DB::raw("COUNT(CASE WHEN employee_shifts.shift_type IN ('standard_night', 'specific_pattern_night') THEN 1 ELSE NULL END) as night_shifts"),
            DB::raw("COUNT(CASE WHEN employee_shifts.shift_type = 'missing_clockout' THEN 1 ELSE NULL END) as missing_clockouts"),
            DB::raw("COUNT(CASE WHEN employee_shifts.shift_type = 'missing_clockin' THEN 1 ELSE NULL END) as missing_clockins"),
            DB::raw("COUNT(CASE WHEN employee_shifts.shift_type = 'day' AND employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END) as holiday_day_shifts"),
            DB::raw("COUNT(CASE WHEN employee_shifts.shift_type IN ('standard_night', 'specific_pattern_night') AND employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END) as holiday_night_shifts"),
            DB::raw("COUNT(CASE WHEN employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END) as total_holiday_shifts"),
            DB::raw("COUNT(CASE WHEN employee_shifts.is_complete = 0 THEN 1 ELSE NULL END) as incomplete_shifts"),
            DB::raw("COUNT(CASE WHEN employee_shifts.is_complete = 0 AND employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END) as incomplete_holiday_shifts"),
            DB::raw('SUM(CASE WHEN employee_shifts.is_complete = 1 THEN employee_shifts.overtime_hours ELSE 0 END) as total_overtime_hours'),
            DB::raw('SUM(CASE WHEN employee_shifts.is_complete = 1 THEN employee_shifts.hours_worked ELSE 0 END) as total_total_hours'),
            DB::raw("COUNT(CASE WHEN employee_shifts.shift_type IN ('inverted_times', 'lookahead_inverted', 'human_error_day', 'human_error_inverted', 'unhandled_pattern') THEN 1 ELSE NULL END) as other_errors"),
        ])
        ->leftJoin('employee_shifts', function($join) use ($startDate, $endDate) {
            $join->on('employees.pin', '=', 'employee_shifts.employee_pin')
                 ->whereBetween('employee_shifts.shift_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
        })
        ->groupBy(
            'employees.pin',
            'employees.empname',
            'employees.empoccupation',
            'employees.team'
        );

        // Apply search filter if present (case-insensitive)
        if (!empty($this->searchValue)) {
            $searchValue = $this->searchValue; // Use the stored search value
            $query->where(function($q) use ($searchValue) {
                $q->where(DB::raw('LOWER(employees.empname)'), 'like', '%' . strtolower($searchValue) . '%')
                  ->orWhere(DB::raw('LOWER(employees.pin)'), 'like', '%' . strtolower($searchValue) . '%')
                  ->orWhere(DB::raw('LOWER(employees.empoccupation)'), 'like', '%' . strtolower($searchValue) . '%')
                  ->orWhere(DB::raw('LOWER(employees.team)'), 'like', '%' . strtolower($searchValue) . '%');
            });
        } else {
            // Apply havingRaw only when no search is active
            $query->havingRaw('COUNT(employee_shifts.id) > 0');
        }

        $query->orderBy('employees.empname', 'asc');

        return $query->get(); // Return the collection
    }

    public function headings(): array
    {
        return [
            'PIN',
            'Employee Name',
            'Occupation',
            'Team',
            'Days Present',
            'Day Shifts',
            'Night Shifts',
            'Missing Clockouts',
            'Missing Clockins',
            'Holiday Day Shifts',
            'Holiday Night Shifts',
            'Total Holiday Shifts',
            'Incomplete Shifts',
            'Incomplete Holiday Shifts',
            'Total Overtime Hours',
            'Total Hours Worked',
            'Other Errors',
            'Total Work Days in Month' // Added this heading
        ];
    }

    public function map($row): array
    {
        // $row is an object returned by the query, cast it to array for easier access if needed
        // Or access properties directly like $row->pin
        return [
            $row->pin,
            ucwords($row->empname),
            ucwords($row->empoccupation),
            $row->team ?? 'N/A',
            $row->days_present ?? 0,
            $row->day_shifts ?? 0,
            $row->night_shifts ?? 0,
            $row->missing_clockouts ?? 0,
            $row->missing_clockins ?? 0,
            $row->holiday_day_shifts ?? 0,
            $row->holiday_night_shifts ?? 0,
            $row->total_holiday_shifts ?? 0,
            $row->incomplete_shifts ?? 0,
            $row->incomplete_holiday_shifts ?? 0,
            round($row->total_overtime_hours ?? 0.0, 2),
            round($row->total_total_hours ?? 0.0, 2),
            $row->other_errors ?? 0,
            $this->totalWorkDaysInMonth // Use the pre-calculated value
        ];
    }
}
