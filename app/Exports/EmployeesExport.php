<?php

namespace App\Exports;

use App\Models\Employee;
use App\Models\Department;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class EmployeesExport implements FromCollection, WithMapping, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Employee::all();
    }

    public function headings(): array
    {
        return [
            'Name',
            'Gender',
            'Department',
            'Position',
            'Leave Taken',
            'Remaining Days'
        ];
    }

    public function map($employee): array
    {
        $departments=Department::all();

        return
        [
         $employee->user->name,
         $employee->gender,
         $employee->dept->name,
         ucwords(str_replace('_',' ',$employee->user->user_type)),
         $employee->leave_taken,
         $employee->available_days,
    
    ];
    }
}
