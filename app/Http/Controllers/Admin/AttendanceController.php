<?php
namespace App\Http\Controllers\Admin;


use Carbon\Carbon;
use App\Models\Holiday;
use App\Models\Employee;
use App\Models\Attendance;
use App\Helpers\DateHelper;
use Illuminate\Http\Request;
use App\Exports\AttendanceExport;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        // Determine the target month and year from request or default to current
        $currentMonth = $request->get('month', now()->month);
        $currentYear  = $request->get('year', now()->year);

        // Calculate date range for the month
        $startDate = Carbon::createFromDate($currentYear, $currentMonth, 1)->startOfMonth();
        $endDate   = $startDate->copy()->endOfMonth();

        // Fetch holidays for the month if DateHelper requires them
        $holidays = Holiday::whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get();

        // Calculate Total Work Days for the month (excluding holidays)
        $totalWorkDaysInMonth = DateHelper::getBusinessDays($currentYear, $currentMonth, $holidays);

        // If it's an AJAX request from DataTables, return the JSON data
        if ($request->ajax()) {
                                        // Use Query Builder for efficient aggregation
                                        // We need to select employee details and aggregate shift data
            $query = Employee::select([ // Changed $data to $query for consistency with adding filters later
                'employees.pin',
                'employees.empname',
                'employees.empoccupation',
                'employees.team',
                // Aggregate calculations from employee_shifts table
                DB::raw('COUNT(DISTINCT employee_shifts.shift_date) as days_present'),
                DB::raw("COUNT(CASE WHEN employee_shifts.shift_type = 'day' THEN 1 ELSE NULL END) as day_shifts"),
                DB::raw("COUNT(CASE WHEN employee_shifts.shift_type IN ('standard_night', 'specific_pattern_night') THEN 1 ELSE NULL END) as night_shifts"),

                // NEW: Count missing clock-outs and clock-ins separately
                DB::raw("COUNT(CASE WHEN employee_shifts.shift_type = 'missing_clockout' THEN 1 ELSE NULL END) as missing_clockouts"),
                DB::raw("COUNT(CASE WHEN employee_shifts.shift_type = 'missing_clockin' THEN 1 ELSE NULL END) as missing_clockins"),

                // Holiday related counts
                DB::raw("COUNT(CASE WHEN employee_shifts.shift_type = 'day' AND employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END) as holiday_day_shifts"),
                DB::raw("COUNT(CASE WHEN employee_shifts.shift_type IN ('standard_night', 'specific_pattern_night') AND employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END) as holiday_night_shifts"),
                DB::raw("COUNT(CASE WHEN employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END) as total_holiday_shifts"), // Total shifts on a holiday, regardless of type

                // NEW: Incomplete Shifts
                DB::raw("COUNT(CASE WHEN employee_shifts.is_complete = 0 THEN 1 ELSE NULL END) as incomplete_shifts"),
                // NEW: Incomplete Shifts on Holidays
                DB::raw("COUNT(CASE WHEN employee_shifts.is_complete = 0 AND employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END) as incomplete_holiday_shifts"),

                // Summing overtime hours only for complete shifts
                DB::raw('SUM(CASE WHEN employee_shifts.is_complete = 1 THEN employee_shifts.overtime_hours ELSE 0 END) as total_overtime_hours'),
                // Summing total hours worked only for complete shifts
                DB::raw('SUM(CASE WHEN employee_shifts.is_complete = 1 THEN employee_shifts.hours_worked ELSE 0 END) as total_total_hours'),

                // Other errors, excluding missing_clockin as it's now separate
                DB::raw("COUNT(CASE WHEN employee_shifts.shift_type IN ('inverted_times', 'lookahead_inverted', 'human_error_day', 'human_error_inverted', 'unhandled_pattern') THEN 1 ELSE NULL END) as other_errors"),
            ])
            // Changed back to LEFT JOIN to ensure all employees are considered,
            // even if they have no shifts or only specific types of shifts in the month.
            // The havingRaw clause below will then filter out employees with no shift data in the range.
                ->leftJoin('employee_shifts', function ($join) use ($startDate, $endDate) {
                    $join->on('employees.pin', '=', 'employee_shifts.employee_pin')
                        ->whereBetween('employee_shifts.shift_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
                })
            // Group the results by employee details to get aggregates per employee
                ->groupBy(
                    'employees.pin',
                    'employees.empname',
                    'employees.empoccupation',
                    'employees.team'
                );

            // Add HAVING clause to filter out employees with no shifts in the month
            // This ensures only employees who have at least one joined shift are shown.
            $query->havingRaw('COUNT(employee_shifts.id) > 0');

            // --- Enable Search Functionality ---
            if ($request->filled('search.value')) {
                $searchValue = $request->input('search.value');
                $query->where(function ($q) use ($searchValue) {
                    $q->where('employees.empname', 'like', '%' . $searchValue . '%')
                        ->orWhere('employees.pin', 'like', '%' . $searchValue . '%')
                        ->orWhere('employees.empoccupation', 'like', '%' . $searchValue . '%')
                        ->orWhere('employees.team', 'like', '%' . $searchValue . '%');
                });
            }

            // Optional: Add an order by clause if you want default sorting
            $query->orderBy('employees.empname', 'asc');

            $data = $query->get(); // Execute the query and get the results

            // Now process the aggregated data with DataTables
            return DataTables::of($data)
            // Add the static total work days for the month
                ->addColumn('total_work_days_in_month', function ($row) use ($totalWorkDaysInMonth) {
                    return $totalWorkDaysInMonth;
                })
            // The following columns are already calculated in the DB query (aliases match)
            // We just need to define them for DataTables
                ->addColumn('days_present', function ($row) {
                    return $row->days_present ?? 0;
                })
                ->addColumn('day_shifts', function ($row) {
                    return $row->day_shifts ?? 0;
                })
                ->addColumn('night_shifts', function ($row) {
                    return $row->night_shifts ?? 0;
                })
            // Add the Missing Clockouts column
                ->addColumn('missing_clockouts', function ($row) {
                    return $row->missing_clockouts ?? 0;
                })
            // NEW: Add the Missing Clockins column
                ->addColumn('missing_clockins', function ($row) {
                    return $row->missing_clockins ?? 0;
                })
                ->addColumn('holiday_day_shifts', function ($row) {
                    return $row->holiday_day_shifts ?? 0;
                })
                ->addColumn('holiday_night_shifts', function ($row) {
                    return $row->holiday_night_shifts ?? 0;
                })
            // Add the general total_holiday_shifts column for display
                ->addColumn('total_holiday_shifts', function ($row) {
                    return $row->total_holiday_shifts ?? 0;
                })
            // NEW: Add the incomplete shifts columns for display
                ->addColumn('incomplete_shifts', function ($row) {
                    return $row->incomplete_shifts ?? 0;
                })
                ->addColumn('incomplete_holiday_shifts', function ($row) {
                    return $row->incomplete_holiday_shifts ?? 0;
                })
                ->addColumn('overtime_hours', function ($row) {
                    return round($row->total_overtime_hours ?? 0.0, 2);
                })
                ->addColumn('total_hours', function ($row) {
                    return round($row->total_total_hours ?? 0.0, 2);
                })
            // Optional: Add the other errors column if you included it in the select
                ->addColumn('other_errors', function ($row) {
                    return $row->other_errors ?? 0;
                })
            // Edit existing columns for formatting (already present in your code)
                ->editColumn('empname', function ($row) {
                    return ucwords($row->empname);
                })
                ->editColumn('empoccupation', function ($row) {
                    return ucwords($row->empoccupation);
                })
                ->editColumn('team', function ($row) {
                    return $row->team ?? 'N/A';
                })
            // Add action column (already present in your code)
                ->addColumn('action', function ($employee) use ($currentMonth, $currentYear) {
                    $html = '
                    <div class="btn-group">
                        <a href="' . action('App\Http\Controllers\Admin\AttendanceController@show', [$employee->pin, 'month' => $currentMonth, 'year' => $currentYear]) . '" class="btn btn-sm btn-primary"><em class="icon ni ni-eye"></em></a>
                    </div>';
                    return $html;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        $title = "Attendance";
        // Pass month and year to the view for potential month/year selection dropdowns/links
        return view('admin.attendance.index', compact('title', 'currentYear', 'currentMonth'));
    }

    public function show($pin)
    {

        $currentMonth = Carbon::now()->subMonth()->month;
        $currentYear  = now()->year;

        $employee    = Employee::where('pin', $pin)->firstOrFail();
        $attendances = Attendance::whereIn('pin', ['1' . $pin, '2' . $pin])
            ->whereYear('datetime', $currentYear)
            ->orderBy('datetime', 'desc')
            ->get()
            ->groupBy(function ($attendance) {
                return date('Y-m-d', strtotime($attendance->datetime));
            });

        $title = "Attendance - " . ucwords($employee->empname);

        return view('admin.attendance.show', compact('employee', 'attendances', 'title'));

    }

    /**
     * Exports attendance data to Excel/CSV using Maatwebsite\Excel.
     */
    public function export(Request $request)
    {
        $currentMonth = $request->get('month', now()->month);
        $currentYear  = $request->get('year', now()->year);
        $searchValue  = $request->get('search_value'); // Get search value from query parameters

        $filename = 'attendance_report_' . Carbon::createFromDate($currentYear, $currentMonth)->format('Y_m') . '.xlsx';
        // For CSV, change the second parameter to 'csv'
        // Excel::download(new AttendanceExport($currentMonth, $currentYear, $searchValue), $filename, \Maatwebsite\Excel\Excel::CSV);

        return Excel::download(new AttendanceExport($currentMonth, $currentYear, $searchValue), $filename);
    }
}
