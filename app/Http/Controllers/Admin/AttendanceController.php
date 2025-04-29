<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\DateHelper;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        // Determine the target month and year
        $currentMonth = $request->get('month', now()->month);
        $currentYear  = $request->get('year', now()->year);

        // Calculate Total Work Days for the month (can be done once)
        $totalWorkDaysInMonth = DateHelper::getBusinessDays();

        // If it's an AJAX request from DataTables, return the JSON data
        if ($request->ajax()) {
            // Build a query to get employees with their attendance data
            // Only include employees with at least one attendance record this month
            $query = Employee::select([
                'employees.pin',
                'employees.empname',
                'employees.empoccupation',
                'employees.team',
                DB::raw('(SELECT COUNT(DISTINCT DATE(datetime)) FROM attendances WHERE SUBSTRING(attendances.pin, 2) = employees.pin AND MONTH(datetime) = ' . $currentMonth . ' AND YEAR(datetime) = ' . $currentYear . ') as days_present'),
            ])
                ->whereExists(function ($query) use ($currentMonth, $currentYear) {
                    $query->select(DB::raw(1))
                        ->from('attendances')
                        ->whereRaw('SUBSTRING(attendances.pin, 2) = employees.pin')
                        ->whereMonth('datetime', $currentMonth)
                        ->whereYear('datetime', $currentYear);
                });

            // Use Yajra DataTables to process the query
            return DataTables::of($query)
                ->addColumn('total_work_days', function ($employee) use ($totalWorkDaysInMonth) {
                    return $totalWorkDaysInMonth;
                })
                ->editColumn('empname', function ($row) {
                    return ucwords($row->empname);
                })
                ->editColumn('empoccupation', function ($row) {
                    return ucwords($row->empoccupation);
                })
                ->editColumn('team', function ($row) {
                    return $row->team ?? 'N/A';
                })
                ->addColumn('action', function ($employee) use ($currentMonth, $currentYear) {
                    $html = '
                    <div class="btn-group">
                        <a href="' . action('App\Http\Controllers\Admin\AttendanceController@show', [$employee->pin]) . '" class="btn btn-sm btn-primary"><em class="icon ni ni-eye"></em></a>
                    </div>';
                    return $html;
                })
                ->rawColumns(['action'])
                ->orderColumn('days_present', 'days_present $1')
                ->filterColumn('days_present', function ($query, $keyword) {
                    $query->whereRaw('(SELECT COUNT(DISTINCT DATE(datetime)) FROM attendances WHERE SUBSTRING(attendances.pin, 2) = employees.pin AND MONTH(datetime) = ' . request('month', now()->month) . ' AND YEAR(datetime) = ' . request('year', now()->year) . ') LIKE ?', ["%{$keyword}%"]);
                })
                ->make(true);
        }

        $title = "Attendance";
        return view('admin.attendance.index', compact('title'));
    }

    public function show($pin)
    {

        $currentMonth = now()->month;
        $currentYear  = now()->year;

        $employee    = Employee::where('pin', $pin)->firstOrFail();
        $attendances = Attendance::whereIn('pin', ['1' . $pin, '2' . $pin])
            ->whereYear('datetime', $currentYear)
            ->orderBy('datetime', 'asc')
            ->get()
            ->groupBy(function ($attendance) {
                return date('Y-m-d', strtotime($attendance->datetime));
            });

        $title = "Attendance - " . ucwords($employee->empname);

        return view('admin.attendance.show', compact('employee', 'attendances', 'title'));

    }
}
