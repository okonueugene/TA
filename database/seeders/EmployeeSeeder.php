<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $employee = Employee::create([
            'user_id' => 1,
            'employee_id' => 'E001',
            'gender' => 'Male',
            'department'=> 4,
            'leave_taken'=> 0,
            'carry_over' => 0,
            'available_days' => 0,
            'days' => 0,

        ]);
        $employee1 = Employee::create([
            'user_id' => 2,
            'employee_id' => 'E003',
            'gender' => 'Male',
            'department'=> 1,
            'leave_taken'=> 0,
            'carry_over' => 0,
            'available_days' => 0,
            'days' => 0,

        ]);

        $employee2 = Employee::create([
            'user_id' => 3,
            'employee_id' => 'E004',
            'gender' => 'Male',
            'department'=> 2,
            'leave_taken'=> 0,
            'carry_over' => 0,
            'available_days' => 0,
            'days' => 0,

        ]);

        $employee3 = Employee::create([
            'user_id' => 4,
            'employee_id' => 'E002',
            'gender' => 'Male',
            'department'=> 4,
            'leave_taken'=> 0,
            'carry_over' => 0,
            'available_days' => 0,
            'days' => 0,

        ]);
    }
}
