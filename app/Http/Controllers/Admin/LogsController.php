<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\EmployeeShift;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;

class LogsController extends Controller
{  public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Activity::select([
                'id',
                'log_name',
                'description',
                'subject_type',
                'event',
                'subject_id',
                'causer_type',
                'causer_id',
                'created_at',
            ])
            // Eager load causer and subject relationships conditionally
            // This avoids N+1 queries if causer/subject are frequently accessed
            ->with(['causer' => function($query) {
                // Load specific attributes if causer is a User
                $query->select('id', 'name'); // Assuming User model has a 'name' field
            }])
            ->with(['subject' => function($query) {
                // Load specific attributes for known subject types
                // Example for EmployeeShift: eager load its related Employee
                $query->when(fn($q) => $q->getModel() instanceof EmployeeShift, fn($q) => $q->with('employee:id,empname'));
                // Add other conditions for different subject types if needed
                // e.g., $query->when(fn($q) => $q->getModel() instanceof OtherModel, fn($q) => $q->select('id', 'other_name_field'));
            }])
            ->orderBy('id', 'DESC');

            return DataTables::of($query)
                // Unique Identifier for the log entry
                ->addColumn('log_id', function ($row) {
                   static $serialNumber = 0;
                    $serialNumber++;
                    return $serialNumber;
                     
                })
                // What happened (Event & Description)
                ->addColumn('action_summary', function ($row) {
                    $subjectType = str_replace('App\\Models\\', '', $row->subject_type);
                    if ($row->event && $subjectType !== 'N/A') {
                         return ucfirst($row->event) . ' ' . $subjectType;
                    }
                    return ucfirst($row->description);
                })
                // Who performed the action - now uses relationship
                ->addColumn('action_by', function ($row) {
                    if ($row->causer) {
                        // Check if causer is a User and has a name
                        if ($row->causer instanceof \App\Models\User && $row->causer->name) {
                            return $row->causer->name; // Display user's name
                        }
                        // Fallback for other causer types or if name is missing
                        return str_replace('App\\Models\\', '', get_class($row->causer)) . ' (ID: ' . $row->causer->id . ')';
                    }
                    return 'System/Unknown';
                })
                // On what (Subject Details) - now uses relationship
                ->addColumn('subject_details', function ($row) {
                    if ($row->subject) {
                        if ($row->subject instanceof \App\Models\EmployeeShift) {
                            // If it's an EmployeeShift, display employee's name and shift date
                            if ($row->subject->employee) {
                                return 'Shift for ' . $row->subject->employee->empname . ' on ' . $row->subject->shift_date->format('Y-m-d');
                            }
                            return 'Shift (ID: ' . $row->subject->id . ') on ' . $row->subject->shift_date->format('Y-m-d');
                        }
                        // Fallback for other subject types
                        return str_replace('App\\Models\\', '', get_class($row->subject)) . ' (ID: ' . $row->subject->id . ')';
                    }
                    return 'N/A';
                })
                // When it happened (Formatted Timestamp)
                ->editColumn('created_at', function ($row) {
                    return Carbon::parse($row->created_at)->format('Y-m-d H:i:s A');
                })
                // Add an 'Action' column for a "View Details" button
                ->addColumn('action', function ($row) {
                    // This button could trigger a modal to show full properties
                    return '<button class="btn btn-sm btn-outline-info view-log-details" data-id="' . $row->id . '">View Details</button>';
                })
                // Raw columns (where we allow HTML)
                ->rawColumns(['action_by', 'subject_details', 'action', 'action_summary'])
                ->make(true);
        }

        $title = "Activity Logs";

        return view('admin.logs.index', compact('title'));
    }
}
