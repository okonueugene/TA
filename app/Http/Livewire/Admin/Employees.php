<?php

namespace App\Http\Livewire\Admin;

use App\Models\User;
use Livewire\Component;
use App\Models\Employee;
use App\Models\Department;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\WithPagination;
use App\Exports\EmployeesExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;


class Employees extends Component
{
    public $pages = 10;
    public $order = 'DESC';

    public function export()
    {
        return Excel::download(new EmployeesExport(), 'users.xlsx');
    }

    public function clearInput()
    {
        $this->name = "";
        $this->email = "";
        $this->password = "";
        $this->user_type = "";
        $this->employee_id = "";
        $this->gender = "";
        $this->department = "";
    }
    public function addEmployee()
    {
        $this->validate([
            'name' => 'required',
            'email' => 'required|email|string|unique:users,email',
            'password' => 'required|string|min:8',
            'employee_id' => 'required|unique:employees,employee_id',
            'gender' => 'required',
            'department' => 'required',
            'user_type' => 'required'

        ]);
        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'user_type' => $this->user_type,

        ]);


        $employee = Employee::create([
            'employee_id' => $this->employee_id,
            'gender' => $this->gender,
            'department' => $this->department,
            'leave_taken' => 0,
            'carry_over' => 0,
            'available_days' => 0,
            'days' => 0,

        ]);



        Employee::where('employee_id', $this->employee_id)->update(['company_id' => $emp->company_id,'user_id' => $user->id ]);

        $this->dispatchBrowserEvent('success', [
            'message' => 'Employee Added successfully',
        ]);

        $this->clearInput();
        $this->emit('userStore');
    }
  

    public function render()
    {
        $title = "Employees List";

        return view('livewire.admin.employee')
            ->extends('layouts.admin', ['title' => $title])
            ->section('content');
    }
}
