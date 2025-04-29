<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_pin',
        'shift_date',
        'clock_in_attendance_id',
        'clock_out_attendance_id',
        'clock_in_time',
        'clock_out_time',
        'hours_worked',
        'shift_type',
        'is_complete',
        'notes'
    ];

    protected $casts = [
        'shift_date' => 'date',
        'clock_in_time' => 'datetime',
        'clock_out_time' => 'datetime',
        'is_complete' => 'boolean',
        'hours_worked' => 'float'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_pin', 'pin');
    }

    public function clockInRecord()
    {
        return $this->belongsTo(Attendance::class, 'clock_in_attendance_id');
    }

    public function clockOutRecord()
    {
        return $this->belongsTo(Attendance::class, 'clock_out_attendance_id');
    }
}
