<?php

namespace App\Exports;
use App\Models\Employee;
use App\Models\Holiday;
use App\Helpers\DateHelper;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class AttendanceExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
{
    protected $month;
    protected $year;
    protected $searchValue;
    protected $totalWorkDaysInMonth;

    public function __construct($month, $year, $searchValue = null)
    {
        $this->month = $month;
        $this->year = $year;
        $this->searchValue = $searchValue;

        // Calculate total work days once in the constructor
        $startDate = Carbon::createFromDate($this->year, $this->month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $holidays = Holiday::whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])->get();
        $this->totalWorkDaysInMonth = DateHelper::getBusinessDays($this->year, $this->month, $holidays);
    }

    public function collection()
    {
        $startDate = Carbon::createFromDate($this->year, $this->month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $query = Employee::select([
            'employees.pin',
            'employees.empname',
            'employees.empoccupation',
            'employees.team',
            DB::raw('COALESCE(COUNT(DISTINCT employee_shifts.shift_date), 0) as days_present'),
            DB::raw("COALESCE(COUNT(CASE WHEN employee_shifts.shift_type = 'day' THEN 1 ELSE NULL END), 0) as day_shifts"),
            DB::raw("COALESCE(COUNT(CASE WHEN employee_shifts.shift_type IN ('standard_night', 'specific_pattern_night') THEN 1 ELSE NULL END), 0) as night_shifts"),
            DB::raw("COALESCE(COUNT(CASE WHEN employee_shifts.shift_type = 'missing_clockout' THEN 1 ELSE NULL END), 0) as missing_clockouts"),
            DB::raw("COALESCE(COUNT(CASE WHEN employee_shifts.shift_type = 'missing_clockin' THEN 1 ELSE NULL END), 0) as missing_clockins"),
            DB::raw("COALESCE(COUNT(CASE WHEN employee_shifts.shift_type = 'day' AND employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END), 0) as holiday_day_shifts"),
            DB::raw("COALESCE(COUNT(CASE WHEN employee_shifts.shift_type IN ('standard_night', 'specific_pattern_night') AND employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END), 0) as holiday_night_shifts"),
            DB::raw("COALESCE(COUNT(CASE WHEN employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END), 0) as total_holiday_shifts"),
            DB::raw("COALESCE(COUNT(CASE WHEN employee_shifts.is_complete = 0 THEN 1 ELSE NULL END), 0) as incomplete_shifts"),
            DB::raw("COALESCE(COUNT(CASE WHEN employee_shifts.is_complete = 0 AND employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END), 0) as incomplete_holiday_shifts"),
            DB::raw('COALESCE(SUM(CASE WHEN employee_shifts.is_complete = 1 THEN employee_shifts.overtime_hours ELSE 0 END), 0) as total_overtime_hours'),
            DB::raw('COALESCE(SUM(CASE WHEN employee_shifts.is_complete = 1 THEN employee_shifts.hours_worked ELSE 0 END), 0) as total_total_hours'),
            DB::raw("COALESCE(COUNT(CASE WHEN employee_shifts.shift_type IN ('inverted_times', 'lookahead_inverted', 'human_error_day', 'human_error_inverted', 'unhandled_pattern') THEN 1 ELSE NULL END), 0) as other_errors"),
        ])
        ->leftJoin('employee_shifts', function($join) use ($startDate, $endDate) {
            $join->on('employees.pin', '=', 'employee_shifts.employee_pin')
                 ->whereBetween('employee_shifts.shift_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
        })
        ->groupBy(
            'employees.pin',
            'employees.empname',
            'employees.empoccupation',
            'employees.team'
        );

        // Apply search filter if present (case-insensitive)
        if (!empty($this->searchValue)) {
            $searchValue = $this->searchValue;
            $query->where(function($q) use ($searchValue) {
                $q->where(DB::raw('LOWER(employees.empname)'), 'like', '%' . strtolower($searchValue) . '%')
                  ->orWhere(DB::raw('LOWER(employees.pin)'), 'like', '%' . strtolower($searchValue) . '%')
                  ->orWhere(DB::raw('LOWER(employees.empoccupation)'), 'like', '%' . strtolower($searchValue) . '%')
                  ->orWhere(DB::raw('LOWER(employees.team)'), 'like', '%' . strtolower($searchValue) . '%');
            });
        } else {
            // Apply havingRaw only when no search is active
            $query->havingRaw('COUNT(employee_shifts.id) > 0');
        }

        $query->orderBy('employees.empname', 'asc');

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'PIN',
            'Employee Name',
            'Occupation',
            'Team',
            'Days Present',
            'Day Shifts',
            'Night Shifts',
            'Missing Clockouts',
            'Missing Clockins',
            'Holiday Day Shifts',
            'Holiday Night Shifts',
            'Total Holiday Shifts',
            'Incomplete Shifts',
            'Incomplete Holiday Shifts',
            'Total Overtime Hours',
            'Total Hours Worked',
            'Other Errors',
            'Total Work Days in Month'
        ];
    }

    public function map($row): array
    {
        return [
            (string) ($row->pin ?? ''),
            ucwords((string) ($row->empname ?? '')),
            ucwords((string) ($row->empoccupation ?? '')),
            (string) ($row->team ?? 'N/A'),
            (int) ($row->days_present ?? 0),
            (int) ($row->day_shifts ?? 0),
            (int) ($row->night_shifts ?? 0),
            (int) ($row->missing_clockouts ?? 0),
            (int) ($row->missing_clockins ?? 0),
            (int) ($row->holiday_day_shifts ?? 0),
            (int) ($row->holiday_night_shifts ?? 0),
            (int) ($row->total_holiday_shifts ?? 0),
            (int) ($row->incomplete_shifts ?? 0),
            (int) ($row->incomplete_holiday_shifts ?? 0),
            (float) round($row->total_overtime_hours ?? 0.0, 2),
            (float) round($row->total_total_hours ?? 0.0, 2),
            (int) ($row->other_errors ?? 0),
            (int) $this->totalWorkDaysInMonth
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => [
                        'argb' => 'FFFFFF',
                    ],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FF4472C4',
                    ],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                
                // Only apply styles if there are data rows
                if ($highestRow <= 1) {
                    return;
                }
                
                // Color code columns based on their content type
                $columnColors = [
                    'A' => 'FFE6F3FF', // PIN - Light blue
                    'B' => 'FFE6F3FF', // Employee Name - Light blue
                    'C' => 'FFE6F3FF', // Occupation - Light blue
                    'D' => 'FFE6F3FF', // Team - Light blue
                    'E' => 'FFE6FFE6', // Days Present - Light green
                    'F' => 'FFFFF2CC', // Day Shifts - Light yellow
                    'G' => 'FFE1D5E7', // Night Shifts - Light purple
                    'H' => 'FFFCE4D6', // Missing Clockouts - Light orange
                    'I' => 'FFFCE4D6', // Missing Clockins - Light orange
                    'J' => 'FFD5E8D4', // Holiday Day Shifts - Light green
                    'K' => 'FFD5E8D4', // Holiday Night Shifts - Light green
                    'L' => 'FFD5E8D4', // Total Holiday Shifts - Light green
                    'M' => 'FFFFD6D6', // Incomplete Shifts - Light red
                    'N' => 'FFFFD6D6', // Incomplete Holiday Shifts - Light red
                    'O' => 'FFF0E68C', // Total Overtime Hours - Khaki
                    'P' => 'FFF0E68C', // Total Hours Worked - Khaki
                    'Q' => 'FFFFE4E1', // Other Errors - Misty rose
                    'R' => 'FFE0E0E0', // Total Work Days - Light gray
                ];

                foreach ($columnColors as $column => $color) {
                    if ($highestRow > 1) {
                        $sheet->getStyle($column . '2:' . $column . $highestRow)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => [
                                    'argb' => $color,
                                ],
                            ],
                        ]);
                    }
                }

                // Apply alternating row colors for better readability
                for ($row = 3; $row <= $highestRow; $row += 2) {
                    $sheet->getStyle('A' . $row . ':R' . $row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => [
                                'argb' => 'FFF8F8F8',
                            ],
                        ],
                    ]);
                }

                // Add borders to all cells
                $sheet->getStyle('A1:R' . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);

                // Center align numeric columns
                $numericColumns = ['E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R'];
                foreach ($numericColumns as $column) {
                    if ($highestRow > 1) {
                        $sheet->getStyle($column . '2:' . $column . $highestRow)->applyFromArray([
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_CENTER,
                            ],
                        ]);
                    }
                }

                // Set row height for header
                $sheet->getRowDimension(1)->setRowHeight(25);
            },
        ];
    }
}