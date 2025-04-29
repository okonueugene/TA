<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'pin',
        'datetime',
        'verified',
        'status',
        'work_code',
    ];

    protected $casts = [
        'datetime' => 'datetime',
    ];

    // Get the actual employee pin by stripping the first digit
    public function getRealPinAttribute()
    {
        return substr($this->pin, 1);
    }

    // Link to Employee model using the real pin
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'pin', 'pin')
            ->withDefault(); // optional to avoid null errors
    }

    // Custom relationship via real pin
    public function actualEmployee()
    {
        return $this->belongsTo(Employee::class, 'real_pin', 'pin');
    }
}
