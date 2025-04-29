<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $leavetype= LeaveType::create([
            'name'=>'Annual Leave',
            'description'=>'Annual leave is a period of time off work that an employee is entitled to after every 12 consecutive months of service with an employer',
            'duration'=>'21'
         ]);
        $leavetype1= LeaveType::create([
           'name'=>'Sick Leave',
           'description'=>'Sick leave is paid time off from work that workers can use to stay home to address their health needs without losing pay',
           'duration'=>'14'
        ]);
        $leavetype2= LeaveType::create([
           'name'=>'Maternity Leave',
           'description'=>'Maternity leave is a period of paid absence from work, to which a woman is legally entitled during the months immediately before and after childbirth',
           'duration'=>'90'
        ]);
        $leavetype3= LeaveType::create([
           'name'=>'Paternity Leave',
           'description'=>'Paternity leave is a period of absence from work granted to a father after or shortly before the birth of his child.
           ',
           'duration'=>'14'
        ]);
        $leavetype4= LeaveType::create([
           'name'=>'Compassionate Leave',
           'description'=>'Compassionate leave is a period of absence from work granted to someone as the result of particular personal circumstances, especially the death of a close relative',
           'duration'=>'10'
        ]);
    }
}
