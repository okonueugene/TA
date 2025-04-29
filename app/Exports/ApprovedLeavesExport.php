<?php

namespace App\Exports;

use App\Models\Leave;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class ApprovedLeavesExport implements FromCollection, WithMapping, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Leave::where('status', 'approved')->get();
    }

    public function headings(): array
    {
        return [
            'Name',
            'Date From',
            'Date To',
            'Applied Days',
            'Department',
            'Leave Type',
            'Date Posted',
            'Status'
        ];
    }

    public function map($leave): array
    {
        return[
            
         $leave->user->name,
         $leave->date_start,
         $leave->date_end,
         $leave->nodays,
         $leave->dept->name,
         $leave->type->name,
         $leave->date_posted,
         ucfirst($leave->status)
    ];
    }
}
