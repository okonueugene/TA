<?php
namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Models\Employee;
use App\Models\Attendance;
use App\Helpers\DateHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Yajra\DataTables\Facades\DataTables;
use App\Models\EmployeeShift;
use App\Models\Holiday;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
       // Determine the target month and year from request or default to current
       $currentMonth = $request->get('month', now()->month);
       $currentYear = $request->get('year', now()->year);

       // Calculate date range for the month
       $startDate = Carbon::createFromDate($currentYear, $currentMonth, 1)->startOfMonth();
       $endDate = $startDate->copy()->endOfMonth();

       // Fetch holidays for the month if DateHelper requires them
       // Ensure your DateHelper::getBusinessDays function handles the Holiday collection correctly
       $holidays = Holiday::whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
           ->get(); // Fetch holidays whose start date is within the month

       // Calculate Total Work Days for the month (excluding holidays)
       // Ensure DateHelper::getBusinessDays uses the $holidays collection to exclude those dates
       // If DateHelper only uses year/month, you might need to pass $holidays explicitly or modify it.
       // Assuming it can accept holidays:
       $totalWorkDaysInMonth = DateHelper::getBusinessDays($currentYear, $currentMonth, $holidays);


       // If it's an AJAX request from DataTables, return the JSON data
       if ($request->ajax()) {
           // Use Query Builder for efficient aggregation
           // We need to select employee details and aggregate shift data
           $data = Employee::select([
               'employees.pin',
               'employees.empname',
               'employees.empoccupation',
               'employees.team',
               // Aggregate calculations from employee_shifts table
               DB::raw('COUNT(DISTINCT employee_shifts.shift_date) as days_present'),
               DB::raw("COUNT(CASE WHEN employee_shifts.shift_type = 'day' THEN 1 ELSE NULL END) as day_shifts"),
               DB::raw("COUNT(CASE WHEN employee_shifts.shift_type IN ('standard_night', 'specific_pattern_night') THEN 1 ELSE NULL END) as night_shifts"),
               // Assuming 'is_holiday' column exists and is boolean (1 for true)
               DB::raw("COUNT(CASE WHEN employee_shifts.shift_type = 'day' AND employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END) as holiday_day_shifts"),
               DB::raw("COUNT(CASE WHEN employee_shifts.shift_type IN ('standard_night', 'specific_pattern_night') AND employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END) as holiday_night_shifts"),
               // Summing overtime hours stored per shift
               DB::raw('SUM(employee_shifts.overtime_hours) as total_overtime_hours'),
               // Summing total hours worked stored per shift
               DB::raw('SUM(employee_shifts.hours_worked) as total_total_hours'),
           ])
           // Join with employee_shifts. Using leftJoin to include employees who might have no shifts in the month,
           // but aggregation functions like COUNT and SUM will return 0 or null for them.
           // Inner join (as below) only includes employees with at least one shift in the period,
           // which matches your previous whereExists logic. Let's keep the join behavior.
           ->join('employee_shifts', 'employees.pin', '=', 'employee_shifts.employee_pin')
           // Filter shifts by the desired month and year
           ->whereBetween('employee_shifts.shift_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            // If you only want to sum hours/overtime for complete shifts, add this where clause:
            // ->where('employee_shifts.is_complete', true)
           // Group the results by employee details to get aggregates per employee
           ->groupBy(
               'employees.pin',
               'employees.empname',
               'employees.empoccupation',
               'employees.team'
           )
           // Optional: Add an order by clause if you want default sorting
            ->orderBy('employees.empname', 'asc')
           ->get(); // Execute the query and get the results


           // Now process the aggregated data with DataTables
           return DataTables::of($data)
                // Add the static total work days for the month
               ->addColumn('total_work_days_in_month', function ($row) use ($totalWorkDaysInMonth) {
                    // This is a static value for the month, added to each row
                   return $totalWorkDaysInMonth;
               })
               // The following columns are already calculated in the DB query (aliases match)
               // We just need to define them for DataTables
               ->addColumn('days_present', function ($row) {
                    return $row->days_present ?? 0; // Use ?? 0 to handle cases where no shifts exist (shouldn't happen with join)
               })
               ->addColumn('day_shifts', function ($row) {
                    return $row->day_shifts ?? 0;
               })
               ->addColumn('night_shifts', function ($row) {
                    return $row->night_shifts ?? 0;
               })
               ->addColumn('holiday_day_shifts', function ($row) {
                    return $row->holiday_day_shifts ?? 0;
               })
               ->addColumn('holiday_night_shifts', function ($row) {
                    return $row->holiday_night_shifts ?? 0;
               })
               ->addColumn('overtime_hours', function ($row) {
                    return round($row->total_overtime_hours ?? 0.0, 2); // Round the sum
               })
               ->addColumn('total_hours', function ($row) {
                    return round($row->total_total_hours ?? 0.0, 2); // Round the sum of total hours per shift
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
                    // Ensure the show link can optionally pass month/year if needed for the calendar view
                   $html = '
                   <div class="btn-group">
                       <a href="' . action('App\Http\Controllers\Admin\AttendanceController@show', [$employee->pin, 'month' => $currentMonth, 'year' => $currentYear]) . '" class="btn btn-sm btn-primary"><em class="icon ni ni-eye"></em></a>
                   </div>';
                   return $html;
               })
               ->rawColumns(['action'])
                // Remove specific filter/order for days_present as it's now a standard aggregated column
                // ->orderColumn('days_present', 'days_present $1')
                // ->filterColumn('days_present', function ($query, $keyword) { ... })
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
            ->whereMonth('datetime', $currentMonth)
            ->orderBy('datetime', 'asc')
            ->get()
            ->groupBy(function ($attendance) {
                return date('Y-m-d', strtotime($attendance->datetime));
            });

        $title = "Attendance - " . ucwords($employee->empname);

        return view('admin.attendance.show', compact('employee', 'attendances', 'title'));

    }
}
