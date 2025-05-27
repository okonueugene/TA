<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeShift;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class ShiftsController extends Controller
{
    const OVERTIME_DAY_CUTOFF_HOUR   = 17; // 5PM (17:00)
    const OVERTIME_DAY_CUTOFF_MINUTE = 30; // 17:30
    const OVERTIME_NIGHT_CUTOFF_HOUR = 7;  // 7AM (07:00)
    public function index(Request $request)
    {

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
                    $html =
                    '
                     <div class="dropdown">
                         <a href="#" class="btn btn-trigger btn-icon dropdown-toggle" data-toggle="dropdown">
                             <em class="icon ni ni-more-h"></em>
                         </a>
                         <ul class="dropdown-menu">
                             <li>
                                 <a href="javascript:void(0);" class="dropdown-item modal-button"
                                     data-href="' . action('App\Http\Controllers\Admin\ShiftsController@edit', $row->id) . '">
                                     Edit
                                 </a>
                             </li>
                             <li>
                                 <a href="javascript:void(0);" class="dropdown-item delete-record"
                                     data-href="' . action('App\Http\Controllers\Admin\ShiftsController@destroy', $row->id) . '">
                                     Delete
                                 </a>
                             </li>
                         </ul>
                     </div>
                    ';

                    return $html;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        $title = 'Shifts';

        return view('admin.shifts.index', compact('title'));
    }

    public function edit($id)
    {
        $shift = EmployeeShift::findOrFail($id);
        return view('admin.shifts.edit', compact('shift'));
    }

    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $shift = EmployeeShift::findOrFail($id);

            // 1. Validate the request data.
            //    Only the fields explicitly allowed for user modification are validated.
            $request->validate([
                'clock_out_time' => 'nullable|date_format:H:i', // Allows for clearing/setting a specific time
                'is_holiday'     => 'nullable|boolean',         // Direct user override
                'shift_type'     => 'required|string|max:191',  // Direct user override
                'is_complete'    => 'required|boolean',         // Direct user override
                'notes'          => 'nullable|string',          // Direct user input
            ]);

            // --- Recalculate derived fields based on editable inputs and existing immutable data ---

            // Retrieve the existing clock_in_time from the database record, as it's not editable.
            $existingClockInTime = $shift->clock_in_time;
            $existingShiftDate   = $shift->shift_date; // Also used for overnight clock-out logic

            // 2. Determine the new clock-out datetime based on the user input.
            $newClockOutTime = null;
            if ($request->filled('clock_out_time')) {
                // Combine the EXISTING shift_date with the NEW clock_out_time from request
                $tempClockOut = Carbon::parse($existingShiftDate->toDateString() . ' ' . $request->input('clock_out_time'));

                // Handle overnight shifts: if new clock-out is earlier than existing clock-in, assume next day.
                // This is crucial for correctly calculating duration.
                if ($existingClockInTime && $tempClockOut->lt($existingClockInTime)) {
                    $tempClockOut->addDay();
                }
                $newClockOutTime = $tempClockOut;
            }
            // If clock_out_time is not filled, it means the user explicitly cleared it or it wasn't provided,
            // so $newClockOutTime remains null, signifying a missing clock-out.

            // 3. Recalculate hours_worked.
            //    Calculated based on immutable clock_in_time and the (potentially new) clock_out_time.
            $calculatedHoursWorked = null;
            if ($existingClockInTime && $newClockOutTime && $newClockOutTime->greaterThan($existingClockInTime)) {
                $calculatedHoursWorked = $newClockOutTime->floatDiffInHours($existingClockInTime);
            }

            // 4. Recalculate overtime_hours.
            //    Calculated using the immutable clock_in_time, the (potentially new) clock_out_time,
            //    and the user-selected shift_type.
            $calculatedOvertimeHours = 0.0;
            if ($existingClockInTime && $newClockOutTime && $newClockOutTime->greaterThan($existingClockInTime)) {
                $calculatedOvertimeHours = $this->calculateOvertime(
                    $existingClockInTime,
                    $newClockOutTime,
                    $request->shift_type// Use the user-submitted shift type for calculation
                );
            }

            // 5. Update the shift record.
            //    Only the allowed fields (user-editable + recalculated) are updated.
            $shift->update([
                // User-editable fields:
                'clock_out_time' => $newClockOutTime,
                'is_holiday'     => $request->boolean('is_holiday'),
                'shift_type'     => $request->shift_type,
                'is_complete'    => $request->boolean('is_complete'),
                'notes'          => $request->notes,

                // Recalculated fields:
                'hours_worked'   => $calculatedHoursWorked,
                'overtime_hours' => (float) round($calculatedOvertimeHours, 2), // Round for storage
            ]);

            DB::commit();

            // Log activity
            activity()
                ->causedBy(auth()->user())
                ->event('updated')
                ->performedOn($shift)
                ->useLog('Shift')
                ->log('updated an employee shift');

            $output = ['success' => true, 'msg' => 'Employee shift updated successfully.'];
            return response()->json($output);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            // Specifically catch validation exceptions to return errors to the frontend
            return response()->json([
                'success' => false,
                'msg'     => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422); // Use 422 status code for unprocessable entity (validation errors)
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update employee shift ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            $output = ['success' => false, 'msg' => 'An unexpected error occurred. Please try again.'];
            // In production, avoid exposing raw exception messages to the user
            return response()->json($output, 500);
        }
    }

    protected function calculateOvertime(Carbon $clockInTime, Carbon $clockOutTime, string $shiftType): float
    {
        $overtimeHours = 0.0;

        if ($shiftType === 'day' || $shiftType === 'human_error_day') {
            $dayOvertimeCutoff = $clockOutTime->copy()->startOfDay()->setTime(self::OVERTIME_DAY_CUTOFF_HOUR, self::OVERTIME_DAY_CUTOFF_MINUTE);
            if ($clockOutTime->greaterThan($dayOvertimeCutoff)) {
                $overtimeHours = $clockOutTime->floatDiffInHours($dayOvertimeCutoff);
            }
        } elseif ($shiftType === 'standard_night' || $shiftType === 'specific_pattern_night') {
            $nightOvertimeCutoff = $clockOutTime->copy()->startOfDay()->setTime(self::OVERTIME_NIGHT_CUTOFF_HOUR, 0);
            if ($clockOutTime->greaterThan($nightOvertimeCutoff)) {
                $overtimeHours = $clockOutTime->floatDiffInHours($nightOvertimeCutoff);
            }
        }

        return max(0.0, $overtimeHours); // Ensure overtime is never negative
    }

    public function destroy($id)
    {
        try
        {
            DB::beginTransaction();

            $shift = EmployeeShift::findOrFail($id);
            $shift->delete();

            DB::commit();

            // Log activity
            activity()
                ->causedBy(auth()->user())
                ->event('deleted')
                ->performedOn($shift)
                ->useLog('Shift')
                ->log('deleted an employee shift');

            $output = ['success' => true, 'msg' => 'Employee shift deleted successfully.'];
            return response()->json($output);
        } catch (\Exception $e) {
            DB::rollBack();
            $output = ['success' => false, 'msg' => 'Failed to delete employee shift.'];
            return response()->json($output);
        }
    }
}
