<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class EmployeesController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Employee::orderBy('id', 'DESC')->get();

            return DataTables::of($query)
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
                                                             data-href="' . action('App\Http\Controllers\Admin\EmployeesController@edit', $row->id) . '"
                                                             >
                                                                <em class="icon ni ni-edit"></em>
                                                                <span>Edit</span>
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a href="' . action('App\Http\Controllers\Admin\EmployeesController@destroy', $row->id) . '"
                                                                <em class="icon ni ni-trash"></em>
                                                                <span>Delete</span>
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>';

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
        $request->validate([
            'empname'       => 'required',
            'empgender'     => 'required',
            'empoccupation' => 'required',
            'empresidence'  => 'required',
            'team'          => 'required',
        ]);

        Employee::create([
            'empname'       => $request->empname,
            'empgender'     => $request->empgender,
            'empoccupation' => $request->empoccupation,
            'empresidence'  => $request->empresidence,
            'team'          => $request->team,
        ]);

        return redirect()->route('admin.employees.index')->with('success', 'Employee created successfully.');
    }

    public function edit($id)
    {
        $employee = Employee::findOrFail($id);
        return view('livewire.admin.edit', compact('employee'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'empname'       => 'required',
            'empgender'     => 'required',
            'empoccupation' => 'required',
            'empresidence'  => 'required',
            'team'          => 'required',
        ]);

        $employee = Employee::findOrFail($id);

        $employee->update([
            'empname'       => $request->empname,
            'empgender'     => $request->empgender,
            'empoccupation' => $request->empoccupation,
            'empresidence'  => $request->empresidence,
            'team'          => $request->team,
        ]);

        return redirect()->route('admin.employees.index')->with('success', 'Employee updated successfully.');
    }

    public function destroy($id)
    {
        Employee::findOrFail($id)->delete();
        return redirect()->route('admin.employees.index')->with('success', 'Employee deleted successfully.');
    }

}
