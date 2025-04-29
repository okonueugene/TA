<?php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\EmployeeShift;
use App\Http\Controllers\Controller;
use Yajra\DataTables\Facades\DataTables;

class ShiftsController extends Controller
{
    public function index(Request $request)
    {

        // protected $fillable = [
        //     'employee_pin',
        //     'shift_date',
        //     'clock_in_attendance_id',
        //     'clock_out_attendance_id',
        //     'clock_in_time',
        //     'clock_out_time',
        //     'hours_worked',
        //     'shift_type',
        //     'is_complete',
        //     'notes'
        // ];

        // protected $casts = [
        //     'shift_date' => 'date',
        //     'clock_in_time' => 'datetime',
        //     'clock_out_time' => 'datetime',
        //     'is_complete' => 'boolean',
        //     'hours_worked' => 'float'
        // ];

        // Determine the target month and year
        $currentMonth = $request->get('month', now()->month);
        $currentYear  = $request->get('year', now()->year);

        if ($request->ajax()) {
            $query = EmployeeShift::select([
                'employee_shifts.id',
                'employee_shifts.employee_pin',
                'employee_shifts.shift_date',
                'employee_shifts.clock_in_attendance_id',
                'employee_shifts.clock_out_attendance_id',
                'employee_shifts.clock_in_time',
                'employee_shifts.clock_out_time',
                'employee_shifts.hours_worked',
                'employee_shifts.shift_type',
                'employee_shifts.is_complete',
                'employee_shifts.notes',
                'employees.empname',
                'employees.empoccupation',
                'employees.team',
            ])
                ->join('employees', 'employee_shifts.employee_pin', '=', 'employees.pin')
                ->whereMonth('employee_shifts.shift_date', $currentMonth)
                ->orderBy('employee_shifts.shift_date', 'desc')
                ->whereYear('employee_shifts.shift_date', $currentYear);

            return DataTables::of($query)
            ->editColumn('empname', function ($row) {
                return ucwords($row->empname);
            })
             // Format Shift Date
             ->editColumn('shift_date', function ($row) {
                // Assuming shift_date is cast to date in the EmployeeShift model
                return $row->shift_date ? $row->shift_date->format('Y-m-d') : ''; // Example: '2025-04-27'
                // Or a more verbose format: $row->shift_date->format('M d, Y'); // Example: 'Apr 27, 2025'
            })
            // Format Clock-in Time
            ->editColumn('clock_in_time', function ($row) {
                // Assuming clock_in_time is cast to datetime in the EmployeeShift model
                return $row->clock_in_time ? $row->clock_in_time->format('H:i') : '--:--'; // Example: '21:00'
                // Or with AM/PM: $row->clock_in_time->format('h:i A'); // Example: '09:00 PM'
            })
            // Format Clock-out Time
            ->editColumn('clock_out_time', function ($row) {
                // Assuming clock_out_time is cast to datetime in the EmployeeShift model
                return $row->clock_out_time ? $row->clock_out_time->format('H:i') : '--:--'; // Example: '03:41'
                // Or with AM/PM: $row->clock_out_time->format('h:i A'); // Example: '03:41 AM'
            })
             // Format Hours Worked (Optional, but good practice)
             ->editColumn('hours_worked', function ($row) {
                  return $row->hours_worked !== null ? number_format($row->hours_worked, 2) : '--:--'; // Format to 2 decimal places
             })
             // Format Shift Type (Optional, e.g., capitalize)
             ->editColumn('shift_type', function ($row) {
                  return ucwords(str_replace('_', ' ', $row->shift_type)); // Example: 'Missing Clockin'
             })
             // Format Is Complete (Optional, e.g., show Yes/No or an icon)
             ->editColumn('is_complete', function ($row) {
                  return $row->is_complete ? 'Yes' : 'No';
             })
                ->addColumn('action', function ($row) {
                    $html = ' <div class="dropdown">
                                            <a href="#" class="btn btn-trigger btn-icon dropdown-toggle"
                                                data-toggle="dropdown">
                                                <em class="icon ni ni-more-h"></em>
                                            </a>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <ul class="link-list-opt no-bdr">
                                                    <li>
                                                        <a href="javascript:void(0);"
                                                         class="dropdown-item modal-button"
                                                         data-href="' . action('App\Http\Controllers\Admin\ShiftsController@edit', $row->id) . '"
                                                         >
                                                            <em class="icon ni ni-edit"></em>
                                                            <span>Edit</span>
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a href="' . action('App\Http\Controllers\Admin\ShiftsController@destroy', $row->id) . '"
                                                            <em class="icon ni ni-trash"></em>
                                                            <span>Delete</span>
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>';

                    return $html;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        $title = 'Shifts';

        return view('admin.shifts.index', compact('title'));
    }
}
