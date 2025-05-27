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
        try
        {
            DB::beginTransaction();
            $request->validate([
                'empname'       => 'required',
                'empgender'     => 'required',
                'empoccupation' => 'required',
                'empresidence'  => 'required',
                'team'          => 'required',
            ]);

            //get the last employee pin
            $lastEmployee = Employee::orderBy('pin', 'desc')->first();

            Employee::create([
                'pin'           => $lastEmployee->pin + 1,
                'empname'       => $request->empname,
                'empgender'     => $request->empgender,
                'empoccupation' => $request->empoccupation,
                'empresidence'  => $request->empresidence,
                'team'          => $request->team,
            ]);

            DB::commit();
            // Log activity
            activity()
                ->causedBy(auth()->user())
                ->event('created')
                ->performedOn($employee)
                ->useLog('Employee')
                ->log('added a new employee');
            $output = ['success' => true,
                'msg'                => 'Employee created successfully',
            ];

            return response()->json($output);
        } catch (\Exception $e) {
            DB::rollBack();
            $output = ['success' => false,
                'msg'                => 'Failed to create employee',
            ];
            return response()->json($output);
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

            $employee->update([
                'empname'       => $request->empname,
                'empgender'     => $request->empgender,
                'empoccupation' => $request->empoccupation,
                'empresidence'  => $request->empresidence,
                'team'          => $request->team,
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
                'msg'                => 'Employee updated successfully',
            ];

            return response()->json($output);
        } catch (\Exception $e) {
            DB::rollBack();
            $output = ['success' => false,
                'msg'                => 'Failed to update employee',
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
                'msg'                => 'Employee deleted successfully',
            ];

            return response()->json($output);
        } catch (\Exception $e) {
            DB::rollBack();
            $output = ['success' => false,
                'msg'                => 'Failed to delete employee',
            ];
            return response()->json($output);
        }
    }

}
