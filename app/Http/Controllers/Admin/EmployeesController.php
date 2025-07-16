<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class EmployeesController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Employee::orderBy('id', 'DESC')->get();

            return DataTables::of($query)
                ->addColumn('action', function ($row) {
                    $html =
                    '
                    <div class="btn-group">
                        <a href="#" class="btn btn-trigger btn-icon dropdown-toggle" data-toggle="dropdown">
                            <em class="icon ni ni-more-h"></em>
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item modal-button"
                                    data-href="' . action('App\Http\Controllers\Admin\EmployeesController@edit', $row->id) . '">
                                    Edit
                                </a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item delete-record"
                                    data-href="' . action('App\Http\Controllers\Admin\EmployeesController@destroy', $row->id) . '">
                                    Delete
                                </a>
                            </li>
                        </ul>
                    </div>
                    '
                    ;

                    return $html;
                })
                ->editColumn('pin', function ($row) {
                    return $row->pin;
                })
                ->editColumn('empname', function ($row) {
                    return ucwords($row->empname);
                })
                ->editColumn('empgender', function ($row) {
                    return ucwords($row->empgender);
                })
                ->editColumn('empoccupation', function ($row) {
                    return ucwords($row->empoccupation);
                })
                ->editColumn('empresidence', function ($row) {
                    return ucfirst($row->empresidence);
                })
                ->editColumn('team', function ($row) {
                    return $row->team ? $row->team : 'N/A';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        $title = "Employees";

        return view('admin.employees.index', compact('title'));
    }

    public function create()
    {
        return view('admin.employees.create');
    }

public function store(Request $request)
{
    try {
        DB::beginTransaction();
        
        // Custom validation messages
        $messages = [
            'pin.unique' => 'An employee with this PIN already exists.',
            'pin.required' => 'The PIN field is required.',
            'pin.integer' => 'The PIN must be a number.',
            'pin.min' => 'The PIN must be at least 1.',
        ];

        // Validate the request
        $validated = $request->validate([
            'pin'           => 'required|integer|min:1|unique:employees,pin',
            'empname'       => 'required|string|max:255',
            'empgender'     => 'required|string|max:50',
            'empoccupation' => 'required|string|max:255',
            'empphone'      => 'nullable|string|max:20',
            'empresidence'  => 'required|string|max:255',
            'team'          => 'required|string|max:255',
            'status'        => 'nullable|string|max:50',
            'acc_no'        => 'nullable|string|max:100',
        ], $messages);

        // Double-check PIN uniqueness (extra safety)
        if (Employee::where('pin', $request->pin)->exists()) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'msg' => 'An employee with this PIN already exists.'
            ], 422);
        }

        // Create the employee
        $employee = Employee::create([
            'pin'           => $validated['pin'],
            'empname'       => $validated['empname'],
            'empgender'     => $validated['empgender'],
            'empoccupation' => $validated['empoccupation'],
            'empphone'      => $validated['empphone'],
            'empresidence'  => $validated['empresidence'],
            'team'          => $validated['team'],
            'status'        => $validated['status'] ?? 'active',
            'acc_no'        => $validated['acc_no'],
        ]);

        DB::commit();
        
        // Log activity
        activity()
            ->causedBy(auth()->user())
            ->event('created')
            ->performedOn($employee)
            ->useLog('Employee')
            ->log('added a new employee');
            
        return response()->json([
            'success' => true,
            'msg' => 'Employee created successfully',
            'employee' => $employee
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        DB::rollBack();
        
        return response()->json([
            'success' => false,
            'msg' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
        
    } catch (\Exception $e) {
        DB::rollBack();
        
        \Log::error('Employee creation failed: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'msg' => 'Failed to create employee: ' . $e->getMessage()
        ], 500);
    }
}

    public function show($id)
    {
        $employee = Employee::findOrFail($id);
        return view('admin.employees.show', compact('employee'));
    }

    public function edit($id)
    {
        $employee = Employee::findOrFail($id);
        return view('admin.employees.edit', compact('employee'));
    }

    public function update(Request $request, $id)
    {
        try
        {
            DB::beginTransaction();

            $employee = Employee::findOrFail($id);

            // Custom validation messages
            $messages = [
                'pin.unique' => 'An employee with this PIN already exists.',
                'pin.required' => 'The PIN field is required.',
                'pin.integer' => 'The PIN must be a number.',
                'pin.min' => 'The PIN must be at least 1.',
            ];

            $request->validate([
                'pin'           => 'required|integer|min:1|unique:employees,pin,' . $id,
                'empname'       => 'required|string|max:255',
                'empgender'     => 'required|string|max:50',
                'empoccupation' => 'required|string|max:255',
                'empphone'      => 'nullable|string|max:20',
                'empresidence'  => 'required|string|max:255',
                'team'          => 'required|string|max:255',
                'status'        => 'nullable|string|max:50',
                'acc_no'        => 'nullable|string|max:100',
            ], $messages);

            $employee->update([
                'pin'           => $request->pin,
                'empname'       => $request->empname,
                'empgender'     => $request->empgender,
                'empoccupation' => $request->empoccupation,
                'empphone'      => $request->empphone,
                'empresidence'  => $request->empresidence,
                'team'          => $request->team,
                'status'        => $request->status ?? 'active',
                'acc_no'        => $request->acc_no,
            ]);

            DB::commit();
            
            // Log activity
            activity()
                ->causedBy(auth()->user())
                ->event('updated')
                ->performedOn($employee)
                ->useLog('Employee')
                ->log('updated an employee');
                
            $output = ['success' => true,
                'msg'           => 'Employee updated successfully',
            ];

            return response()->json($output);
        } catch (\Exception $e) {
            DB::rollBack();
            $output = ['success' => false,
                'msg'           => 'Failed to update employee: ' . $e->getMessage(),
            ];
            return response()->json($output);
        }
    }

    public function destroy($id)
    {
        try
        {
            DB::beginTransaction();

            $employee = Employee::findOrFail($id);
            $employee->delete();

            DB::commit();
            
            // Log activity
            activity()
                ->causedBy(auth()->user())
                ->event('deleted')
                ->performedOn($employee)
                ->useLog('Employee')
                ->log('deleted an employee');

            $output = ['success' => true,
                'msg'           => 'Employee deleted successfully',
            ];

            return response()->json($output);
        } catch (\Exception $e) {
            DB::rollBack();
            $output = ['success' => false,
                'msg'           => 'Failed to delete employee: ' . $e->getMessage(),
            ];
            return response()->json($output);
        }
    }

    /**
     * Check if PIN already exists
     */
    public function checkPin(Request $request)
    {
        $pin = $request->input('pin');
        $employeeId = $request->input('employee_id'); // For update operations
        
        $query = Employee::where('pin', $pin);
        
        if ($employeeId) {
            $query->where('id', '!=', $employeeId);
        }
        
        $exists = $query->exists();
        
        return response()->json(['exists' => $exists]);
    }
}
