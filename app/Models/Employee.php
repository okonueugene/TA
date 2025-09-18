<?php
namespace App\Models;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Employee extends Model
{
    use HasFactory;

    protected $table = 'employees';

    protected $fillable = [
        'pin',
        'empname',
        'empgender',
        'empoccupation',
        'empphone',
        'empresidence',
        'team',
        'status',
        'acc_no',
        'is_blowmolding',
    ];

    protected $dates = ['created_at', 'updated_at'];

    protected $casts = [
        'is_blowmolding' => 'boolean',
    ];

    // Clock-ins
    public function clockIns()
    {
        return $this->hasMany(Attendance::class, 'pin', 'pin')
            ->whereRaw("LEFT(pin, 1) = '1'")
            ->whereRaw("SUBSTRING(pin, 2) = employees.pin");
    }

    // Clock-outs
    public function clockOuts()
    {
        return $this->hasMany(Attendance::class, 'pin', 'pin')
            ->whereRaw("LEFT(pin, 1) = '2'")
            ->whereRaw("SUBSTRING(pin, 2) = employees.pin");
    }

    // All attendances (merged or raw)
    public function allAttendances()
    {
        return $this->hasMany(Attendance::class, 'pin', 'pin');
    }

    public function scopePresentToday($query)
    {
        $today = Carbon::today()->toDateString();

        return $query->whereIn('pin', function ($sub) use ($today) {
            $sub->select(DB::raw("SUBSTRING(pin, 2)"))
                ->from('attendances')
                ->whereDate('datetime', $today);
        });
    }

    //employees present this month
    public function scopePresentMonth($query)
    {
        $month = Carbon::now()->format('m');
        $year  = Carbon::now()->format('Y');
        return $query->whereIn('pin', function ($sub) use ($month, $year) {
            $sub->select(DB::raw("SUBSTRING(pin, 2)"))
                ->from('attendances')
                ->whereMonth('datetime', $month)
                ->whereYear('datetime', $year);
        });
    }

    public function shifts()
    {
        return $this->hasMany(EmployeeShift::class, 'employee_pin', 'pin');
    }

    public function getShiftsForDate($date)
    {
        return $this->shifts()
            ->where('shift_date', $date instanceof \Carbon\Carbon  ? $date->format('Y-m-d') : $date)
            ->get();
    }

    public function getShiftsForDateRange($startDate, $endDate)
    {
        return $this->shifts()
            ->whereBetween('shift_date', [
                $startDate instanceof \Carbon\Carbon  ? $startDate->format('Y-m-d') : $startDate,
                $endDate instanceof \Carbon\Carbon  ? $endDate->format('Y-m-d') : $endDate,
            ])
            ->get();
    }

    public function getHoursWorkedForDate($date)
    {
        return $this->getShiftsForDate($date)
            ->sum('hours_worked');
    }

    public function getHoursWorkedForDateRange($startDate, $endDate)
    {
        return $this->getShiftsForDateRange($startDate, $endDate)
            ->sum('hours_worked');
    }

}
