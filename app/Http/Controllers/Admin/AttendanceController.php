<?php
namespace App\Http\Controllers\Admin;

use App\Exports\AttendanceExport;
use App\Helpers\DateHelper;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            $query = Employee::select([
                'employees.pin',
                'employees.empname',
                'employees.empoccupation',
                'employees.team',
                // Aggregate calculations from employee_shifts table
                DB::raw('COUNT(DISTINCT employee_shifts.shift_date) as days_present'),
                DB::raw("COUNT(CASE WHEN employee_shifts.shift_type = 'day' THEN 1 ELSE NULL END) as day_shifts"),
                DB::raw("COUNT(CASE WHEN employee_shifts.shift_type IN ('standard_night', 'specific_pattern_night') THEN 1 ELSE NULL END) as night_shifts"),

                // Count missing clock-outs and clock-ins separately
                DB::raw("COUNT(CASE WHEN employee_shifts.shift_type = 'missing_clockout' THEN 1 ELSE NULL END) as missing_clockouts"),
                DB::raw("COUNT(CASE WHEN employee_shifts.shift_type = 'missing_clockin' THEN 1 ELSE NULL END) as missing_clockins"),

                // Holiday related counts
                DB::raw("COUNT(CASE WHEN employee_shifts.shift_type = 'day' AND employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END) as holiday_day_shifts"),
                DB::raw("COUNT(CASE WHEN employee_shifts.shift_type IN ('standard_night', 'specific_pattern_night') AND employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END) as holiday_night_shifts"),
                DB::raw("COUNT(CASE WHEN employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END) as total_holiday_shifts"),

                // Incomplete Shifts
                DB::raw("COUNT(CASE WHEN employee_shifts.is_complete = 0 THEN 1 ELSE NULL END) as incomplete_shifts"),
                DB::raw("COUNT(CASE WHEN employee_shifts.is_complete = 0 AND employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END) as incomplete_holiday_shifts"),

                // Summing overtime hours only for complete shifts
                DB::raw('SUM(CASE WHEN employee_shifts.is_complete = 1 THEN employee_shifts.overtime_hours ELSE 0 END) as total_overtime_hours'),
                // Summing total hours worked only for complete shifts
                DB::raw('SUM(CASE WHEN employee_shifts.is_complete = 1 THEN employee_shifts.hours_worked ELSE 0 END) as total_total_hours'),

                // Other errors, excluding missing_clockin as it's now separate
                DB::raw("COUNT(CASE WHEN employee_shifts.shift_type IN ('inverted_times', 'lookahead_inverted', 'human_error_day', 'human_error_inverted', 'unhandled_pattern') THEN 1 ELSE NULL END) as other_errors"),
            ])
            ->leftJoin('employee_shifts', function ($join) use ($startDate, $endDate) {
                $join->on('employees.pin', '=', 'employee_shifts.employee_pin')
                    ->whereBetween('employee_shifts.shift_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
            })
            ->groupBy(
                'employees.pin',
                'employees.empname',
                'employees.empoccupation',
                'employees.team'
            );

            // Add HAVING clause to filter out employees with no shifts in the month
            $query->havingRaw('COUNT(employee_shifts.id) > 0');

            // --- Improved Search Functionality ---
            // Handle global search
            if ($request->filled('search.value')) {
                $searchValue = $request->input('search.value');
                $query->where(function ($q) use ($searchValue) {
                    $q->where('employees.empname', 'like', '%' . $searchValue . '%')
                        ->orWhere('employees.pin', 'like', '%' . $searchValue . '%')
                        ->orWhere('employees.empoccupation', 'like', '%' . $searchValue . '%')
                        ->orWhere('employees.team', 'like', '%' . $searchValue . '%');
                });
            }

            // Handle individual column searches
            $columns = $request->get('columns', []);
            if (!empty($columns)) {
                foreach ($columns as $index => $column) {
                    if (!empty($column['search']['value'])) {
                        $searchValue = $column['search']['value'];
                        
                        switch ($column['data']) {
                            case 'empname':
                                $query->where('employees.empname', 'like', '%' . $searchValue . '%');
                                break;
                            case 'pin':
                                $query->where('employees.pin', 'like', '%' . $searchValue . '%');
                                break;
                            case 'empoccupation':
                                $query->where('employees.empoccupation', 'like', '%' . $searchValue . '%');
                                break;
                            case 'team':
                                $query->where('employees.team', 'like', '%' . $searchValue . '%');
                                break;
                        }
                    }
                }
            }

            // Handle ordering
            if ($request->filled('order')) {
                $orderColumn = $request->input('order.0.column');
                $orderDir = $request->input('order.0.dir');
                
                if (isset($columns[$orderColumn])) {
                    $columnName = $columns[$orderColumn]['data'];
                    
                    switch ($columnName) {
                        case 'empname':
                            $query->orderBy('employees.empname', $orderDir);
                            break;
                        case 'pin':
                            $query->orderBy('employees.pin', $orderDir);
                            break;
                        case 'empoccupation':
                            $query->orderBy('employees.empoccupation', $orderDir);
                            break;
                        case 'team':
                            $query->orderBy('employees.team', $orderDir);
                            break;
                        case 'days_present':
                            $query->orderBy('days_present', $orderDir);
                            break;
                        case 'day_shifts':
                            $query->orderBy('day_shifts', $orderDir);
                            break;
                        case 'night_shifts':
                            $query->orderBy('night_shifts', $orderDir);
                            break;
                        case 'total_overtime_hours':
                            $query->orderBy('total_overtime_hours', $orderDir);
                            break;
                        case 'total_total_hours':
                            $query->orderBy('total_total_hours', $orderDir);
                            break;
                        default:
                            $query->orderBy('employees.empname', 'asc');
                    }
                }
            } else {
                // Default ordering
                $query->orderBy('employees.empname', 'asc');
            }

            // Use DataTables::eloquent() instead of DataTables::of() for better integration
            return DataTables::eloquent($query)
                // Add the static total work days for the month
                ->addColumn('total_work_days_in_month', function ($row) use ($totalWorkDaysInMonth) {
                    return $totalWorkDaysInMonth;
                })
                // The following columns are already calculated in the DB query
                ->addColumn('days_present', function ($row) {
                    return $row->days_present ?? 0;
                })
                ->addColumn('day_shifts', function ($row) {
                    return $row->day_shifts ?? 0;
                })
                ->addColumn('night_shifts', function ($row) {
                    return $row->night_shifts ?? 0;
                })
                ->addColumn('missing_clockouts', function ($row) {
                    return $row->missing_clockouts ?? 0;
                })
                ->addColumn('missing_clockins', function ($row) {
                    return $row->missing_clockins ?? 0;
                })
                ->addColumn('holiday_day_shifts', function ($row) {
                    return $row->holiday_day_shifts ?? 0;
                })
                ->addColumn('holiday_night_shifts', function ($row) {
                    return $row->holiday_night_shifts ?? 0;
                })
                ->addColumn('total_holiday_shifts', function ($row) {
                    return $row->total_holiday_shifts ?? 0;
                })
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
                ->addColumn('other_errors', function ($row) {
                    return $row->other_errors ?? 0;
                })
                // Format existing columns
                ->editColumn('empname', function ($row) {
                    return ucwords($row->empname);
                })
                ->editColumn('empoccupation', function ($row) {
                    return ucwords($row->empoccupation);
                })
                ->editColumn('team', function ($row) {
                    return $row->team ?? 'N/A';
                })
                // Add action column
                ->addColumn('action', function ($employee) use ($currentMonth, $currentYear) {
                    $html = '
                    <div class="btn-group">
                        <a href="' . action('App\Http\Controllers\Admin\AttendanceController@show', [$employee->pin, 'month' => $currentMonth, 'year' => $currentYear]) . '" class="btn btn-sm btn-primary"><em class="icon ni ni-eye"></em></a>
                    </div>';
                    return $html;
                })
                ->rawColumns(['action'])
                // Configure searchable columns
                ->filter(function ($query) use ($request) {
                    // This method allows for additional custom filtering if needed
                    // The main search logic is already handled above
                })
                ->make(true);
        }

        $title = "Attendance";
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
        try {
            $currentMonth = $request->get('month', now()->month);
            $currentYear  = $request->get('year', now()->year);
            $searchValue  = $request->get('search', null);

            $filename = 'attendance_report_' . Carbon::createFromDate($currentYear, $currentMonth)->format('Y_m') . '.xlsx';

            // Log activity first
            activity()
                ->causedBy(auth()->user())
                ->event('export_attendance')
                ->log('Exported attendance report');

            return Excel::download(new AttendanceExport($currentMonth, $currentYear, $searchValue), $filename);
        } catch (\Exception $e) {
            \Log::error('Excel export error: ' . $e->getMessage());

            return response()->json([
                'error' => 'Failed to generate Excel file. Please try again.',
            ], 500);
        }
    }
}