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
        $this->totalWorkDaysInMonth = DateHelper::getBusinessDays($this->year, $this->month);
    }

    public function collection()
    {
        $startDate = Carbon::createFromDate($this->year, $this->month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $query = Employee::select([
            'employees.pin',
            'employees.empname',
            'employees.empoccupation', // This will be 'Designation' in the output
            'employees.team',
            DB::raw('COALESCE(COUNT(DISTINCT employee_shifts.shift_date), 0) as days_present'),
            // ALIGNMENT FIX: Include 'night' in night_shifts count
            DB::raw("COALESCE(COUNT(CASE WHEN employee_shifts.shift_type = 'day' THEN 1 ELSE NULL END), 0) as day_shifts"),
            DB::raw("COALESCE(COUNT(CASE WHEN employee_shifts.shift_type IN ('night', 'standard_night', 'specific_pattern_night') THEN 1 ELSE NULL END), 0) as night_shifts"),
            DB::raw("COALESCE(COUNT(CASE WHEN employee_shifts.shift_type = 'missing_clockout' THEN 1 ELSE NULL END), 0) as missing_clockouts"),
            DB::raw("COALESCE(COUNT(CASE WHEN employee_shifts.shift_type = 'missing_clockin' THEN 1 ELSE NULL END), 0) as missing_clockins"),
            DB::raw("COALESCE(COUNT(CASE WHEN employee_shifts.shift_type = 'day' AND employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END), 0) as holiday_day_shifts"),
            DB::raw("COALESCE(COUNT(CASE WHEN employee_shifts.shift_type IN ('night', 'standard_night', 'specific_pattern_night') AND employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END), 0) as holiday_night_shifts"),
            DB::raw('COALESCE(SUM(CASE WHEN employee_shifts.is_complete = 1 THEN employee_shifts.overtime_hours_1_5x ELSE 0 END), 0) as overtime_1_5x'),
            DB::raw('COALESCE(SUM(CASE WHEN employee_shifts.is_complete = 1 THEN employee_shifts.overtime_hours_2_0x ELSE 0 END), 0) as overtime_2_0x'),
            // ALIGNMENT FIX: Use consistent alias 'total_actual_hours_worked'
            DB::raw('COALESCE(SUM(CASE WHEN employee_shifts.is_complete = 1 THEN employee_shifts.hours_worked ELSE 0 END), 0) as total_actual_hours_worked'),
            DB::raw("COALESCE(COUNT(CASE WHEN employee_shifts.is_holiday = 1 THEN 1 ELSE NULL END), 0) as total_holiday_shifts"),
            DB::raw("COALESCE(COUNT(CASE WHEN employee_shifts.is_complete = 0 THEN 1 ELSE NULL END), 0) as incomplete_shifts"),
            // ALIGNMENT FIX: Use 'human_error_night' to match controller
            DB::raw("COALESCE(COUNT(CASE WHEN employee_shifts.shift_type IN ('inverted_times', 'lookahead_inverted', 'human_error_day', 'human_error_night', 'unhandled_pattern') THEN 1 ELSE NULL END), 0) as other_errors"),
            DB::raw('COALESCE(SUM(employee_shifts.lateness_minutes), 0) as total_lateness_minutes'),
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

        if (!empty($this->searchValue)) {
            $searchValue = $this->searchValue;
            $query->where(function($q) use ($searchValue) {
                $q->where(DB::raw('LOWER(employees.empname)'), 'like', '%' . strtolower($searchValue) . '%')
                  ->orWhere(DB::raw('LOWER(employees.pin)'), 'like', '%' . strtolower($searchValue) . '%')
                  ->orWhere(DB::raw('LOWER(employees.empoccupation)'), 'like', '%' . strtolower($searchValue) . '%')
                  ->orWhere(DB::raw('LOWER(employees.team)'), 'like', '%' . strtolower($searchValue) . '%');
            });
        }
        
        // ALIGNMENT FIX: Apply havingRaw unconditionally, similar to index method
        $query->havingRaw('COUNT(employee_shifts.id) > 0');

        // Order by pin ascending
        $query->orderBy('employees.pin', 'asc');

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Print',
            'Name',
            'Designation',
            'Day Shift',
            'Night Shift',
            'Team',
            'Days Present',
            'Missing Clockouts',
            'Missing Clockins',
            'Holiday Day Shifts',
            'Holiday Night Shifts',
            'Total Holiday Shifts',
            'Incomplete Shifts',
            'Overtime 1.5x Hours',
            'Overtime 2.0x Hours',
            'Total Hours Worked', // This heading matches the DataTables 'total_hours' column
            'Other Errors',
            'Total Lateness (Minutes)',
            'Total Work Days in Month'
        ];
    }

    public function map($row): array
    {
        return [
            (string) ($row->pin ?? ''),
            ucwords((string) ($row->empname ?? '')),
            ucwords((string) ($row->empoccupation ?? '')),
            (int) ($row->day_shifts ?? 0),
            (int) ($row->night_shifts ?? 0),
            (string) ($row->team ?? 'N/A'),
            (int) ($row->days_present ?? 0),
            (int) ($row->missing_clockouts ?? 0),
            (int) ($row->missing_clockins ?? 0),
            (int) ($row->holiday_day_shifts ?? 0),
            (int) ($row->holiday_night_shifts ?? 0),
            (int) ($row->total_holiday_shifts ?? 0),
            (int) ($row->incomplete_shifts ?? 0),
            (float) round($row->overtime_1_5x ?? 0.0, 2),
            (float) round($row->overtime_2_0x ?? 0.0, 2),
            (float) round($row->total_actual_hours_worked ?? 0.0, 2), // ALIGNMENT FIX: Use new alias
            (int) ($row->other_errors ?? 0),
            (int) ($row->total_lateness_minutes ?? 0),
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
                
                if ($highestRow <= 1) {
                    return;
                }
                
                $columnColors = [
                    'A' => 'FFE6F3FF', // Print (was PIN) - Light blue
                    'B' => 'FFE6F3FF', // Name (was Employee Name) - Light blue
                    'C' => 'FFE6F3FF', // Designation (was Occupation) - Light blue
                    'D' => 'FFFFF2CC', // Day Shifts - Light yellow
                    'E' => 'FFE1D5E7', // Night Shifts - Light purple
                    'F' => 'FFE6F3FF', // Team - Light blue
                    'G' => 'FFE6FFE6', // Days Present - Light green
                    'H' => 'FFFCE4D6', // Missing Clockouts - Light orange
                    'I' => 'FFFCE4D6', // Missing Clockins - Light orange
                    'J' => 'FFD5E8D4', // Holiday Day Shifts - Light green
                    'K' => 'FFD5E8D4', // Holiday Night Shifts - Light green
                    'L' => 'FFD5E8D4', // Total Holiday Shifts - Light green
                    'M' => 'FFFFD6D6', // Incomplete Shifts - Light red
                    'N' => 'FFF0E68C', // Overtime 1.5x Hours - Khaki
                    'O' => 'FFF0E68C', // Overtime 2.0x Hours - Khaki
                    'P' => 'FFF0E68C', // Total Hours Worked - Khaki
                    'Q' => 'FFFFE4E1', // Other Errors - Misty rose
                    'R' => 'FFFFCCCC', // Total Lateness - Light pink
                    'S' => 'FFE0E0E0', // Total Work Days - Light gray
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

                for ($row = 3; $row <= $highestRow; $row += 2) {
                    $sheet->getStyle('A' . $row . ':S' . $row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => [
                                'argb' => 'FFF8F8F8',
                            ],
                        ],
                    ]);
                }

                $sheet->getStyle('A1:S' . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);

                $numericColumns = ['D', 'E', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S'];
                foreach ($numericColumns as $column) {
                    if ($highestRow > 1) {
                        $sheet->getStyle($column . '2:' . $column . $highestRow)->applyFromArray([
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_CENTER,
                            ],
                        ]);
                    }
                }

                $sheet->getRowDimension(1)->setRowHeight(25);
            },
        ];
    }
}