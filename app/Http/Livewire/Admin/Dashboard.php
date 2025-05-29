<?php
namespace App\Http\Livewire\Admin;

use App\Helpers\DateHelper;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

class Dashboard extends Component
{
    public function render()
    {
        $data = [];
        $title = 'Dashboard';

        $employees             = Employee::orderBy('id', 'ASC')->get();
        $presentEmployees      = Employee::presentToday()->get();
        $presentEmployeesMonth = Employee::presentMonth()->get();
        // $clockins  = 125;
        // $clockouts = 120;
        // Doughnut Chart: Clock-ins vs Clock-outs (for current week)
        $startOfPeriod = Carbon::now()->startOfWeek();
        $endOfPeriod   = Carbon::now()->endOfWeek()->endOfDay();

        $clockins = Attendance::whereBetween('datetime', [$startOfPeriod, $endOfPeriod])
            ->whereRaw("LEFT(pin, 1) = '1'")
            ->count();
        $clockouts = Attendance::whereBetween('datetime', [$startOfPeriod, $endOfPeriod])
            ->whereRaw("LEFT(pin, 1) = '2'")
            ->count();

        // $weekDays         = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        // $weeklyAttendance = [18, 22, 20, 19, 23, 17, 15];

        $weekDays = [];
        $weeklyAttendanceCounts = [];
        $today = Carbon::today();
        
        for ($i = 6; $i >= 0; $i--) {
            $date = $today->copy()->subDays($i);
            $weekDays[] = $date->format('D'); // e.g., Mon, Tue...
        
            $uniqueEmployeesOnDay = DB::table('attendances')
            ->whereDate('datetime', $date)
            ->where(function ($query) {
                $query->where('pin', 'like', '1%')
                      ->orWhere('pin', 'like', '2%');
            })
            ->count(DB::raw('DISTINCT SUBSTRING(pin, 2)'));
        
            $weeklyAttendanceCounts[] = $uniqueEmployeesOnDay;
        }
        
        $weeklyAttendance = $weeklyAttendanceCounts;

        $businessDays = DateHelper::getBusinessDays();

        $daysWorked   = DateHelper::getBusinessDays(today());

        $deviceStatus =  [];


        return view('livewire.admin.dashboard', compact('employees', 'clockins', 'clockouts', 'weekDays', 'weeklyAttendance', 'businessDays', 'presentEmployees', 'daysWorked', 'presentEmployeesMonth', 'deviceStatus'))
            ->extends('layouts.admin', ['title' => $title])
            ->section('content')
        ;
    }
}
