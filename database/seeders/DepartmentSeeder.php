<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $department= Department::create([
            'name'=>'Human Resource',
            'description'=>'Deals with the division of a business responsible for finding, screening, recruiting, and training job applicants'
         ]);
        $department1= Department::create([
           'name'=>'Sales And Marketing',
           'description'=>'Deals with the division of a business  responsible for researching and developing marketing opportunities and planning and implementing new sales plans'
        ]);
        $department2= Department::create([
           'name'=>'Finance and Procurement',
           'description'=>'Deals with the division of a business responsible for fulfiling duties in relation to the management, development, implementation and monitoring of the accounting and procurement function with a focus on areas of expenditure including payroll and general financial management'
        ]);
        $department3= Department::create([
           'name'=>'Executive Office',
           'description'=>'Deals with the division of a business responsible for creating the employee schedule, dealing with any employee complaints and ensuring office employee work is up to standard'
        ]);
        $department4= Department::create([
           'name'=>'Engineering and Maintenance',
           'description'=>'Deals with the division of a business responsible for  checking, repairing and servicing machinery, equipment, systems and infrastructures'
        ]);
        $department5= Department::create([
           'name'=>'Production and Planning',
           'description'=>'Deals with the division of a business responsible for scheduling the usage of production materials to ensure optimal levels'
        ]);
        $department6= Department::create([
           'name'=>'SHEQ&R&D',
           'description'=>'Deals with the division of a business responsible for enhancing safety performance by promoting safety consciousness and maintaining excellence standards for safety of people and processes'
        ]);
    }
}
