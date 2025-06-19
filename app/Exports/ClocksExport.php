<?php

namespace App\Exports;

use App\Models\Attendance;
use App\Models\Employee; // Import Employee model
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
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ClocksExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
{
    protected $month;
    protected $year;
    protected $searchValue;

    public function __construct($month, $year, $searchValue = null)
    {
        $this->month = $month;
        $this->year = $year;
        $this->searchValue = $searchValue;
    }

    public function collection()
    {
        $startDate = Carbon::createFromDate($this->year, $this->month, 1)->startOfMonth();
        $endDate   = $startDate->copy()->endOfMonth();

        $query = Employee::select([
            'employees.pin as employee_pin',
            'employees.empname',
            'employees.empoccupation',
            'employees.team',
            DB::raw('DATE(attendances.datetime) as punch_date'),
            DB::raw("MIN(CASE WHEN LEFT(attendances.pin, 1) = '1' THEN attendances.datetime ELSE NULL END) as clock_in_time"),
            DB::raw("MAX(CASE WHEN LEFT(attendances.pin, 1) = '2' THEN attendances.datetime ELSE NULL END) as clock_out_time")
        ])
        ->join('attendances', function($join) {
            $join->on(DB::raw('SUBSTRING(attendances.pin, 2)'), '=', 'employees.pin');
        })
        ->whereBetween('attendances.datetime', [$startDate, $endDate->endOfDay()])
        ->groupBy(
            'employees.pin',
            'employees.empname',
            'employees.empoccupation',
            'employees.team',
            DB::raw('DATE(attendances.datetime)')
        )
        ->havingRaw('COUNT(attendances.id) > 0'); // Ensure there's at least one punch for the day

        // Apply search filter if provided (matches ClocksController index method)
        if (!empty($this->searchValue)) {
            $searchValue = $this->searchValue;
            $query->where(function ($q) use ($searchValue) {
                $q->where(DB::raw('LOWER(employees.empname)'), 'like', '%' . strtolower($searchValue) . '%')
                  ->orWhere(DB::raw('LOWER(employees.pin)'), 'like', '%' . strtolower($searchValue) . '%')
                  ->orWhere(DB::raw('LOWER(employees.empoccupation)'), 'like', '%' . strtolower($searchValue) . '%')
                  ->orWhere(DB::raw('LOWER(employees.team)'), 'like', '%' . strtolower($searchValue) . '%')
                  ->orWhere(DB::raw('DATE(attendances.datetime)'), 'like', '%' . $searchValue . '%')
                  ->orWhere(DB::raw("MIN(CASE WHEN LEFT(attendances.pin, 1) = '1' THEN attendances.datetime ELSE NULL END)"), 'like', '%' . $searchValue . '%')
                  ->orWhere(DB::raw("MAX(CASE WHEN LEFT(attendances.pin, 1) = '2' THEN attendances.datetime ELSE NULL END)"), 'like', '%' . $searchValue . '%');
            });
        }

        // Order the data consistently with the DataTables view
        $query->orderBy(DB::raw('DATE(attendances.datetime)'), 'desc');

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Employee PIN',
            'Employee Name',
            'Designation',
            'Team',
            'Date',
            'In Time',
            'Out Time',
        ];
    }

    public function map($row): array
    {
        return [
            (string) ($row->employee_pin ?? ''),
            ucwords((string) ($row->empname ?? '')),
            ucwords((string) ($row->empoccupation ?? '')),
            (string) ($row->team ?? 'N/A'),
            Carbon::parse($row->punch_date)->format('Y-m-d'),
            $row->clock_in_time ? Carbon::parse($row->clock_in_time)->format('H:i:s') : 'N/A',
            $row->clock_out_time ? Carbon::parse($row->clock_out_time)->format('H:i:s') : 'N/A',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [ // Style for the first row (headings)
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['argb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4472C4'], // A nice blue color
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
                $highestColumn = $sheet->getHighestColumn();

                if ($highestRow <= 1) { // No data rows, only header
                    return;
                }

                // Apply alternating row background colors
                for ($row = 2; $row <= $highestRow; $row++) { // Start from row 2 for data
                    if ($row % 2 == 0) { // Even rows
                        $sheet->getStyle('A' . $row . ':' . $highestColumn . $row)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FFF0F0F0'], // Light gray
                            ],
                        ]);
                    }
                }

                // Apply borders to all cells with data
                $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);

                // Center align numeric and datetime columns (Employee PIN, Date, In Time, Out Time)
                $centerAlignedColumns = ['A', 'E', 'F', 'G'];
                foreach ($centerAlignedColumns as $column) {
                    $sheet->getStyle($column . '2:' . $column . $highestRow)->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                        ],
                    ]);
                }

                // Set header row height
                $sheet->getRowDimension(1)->setRowHeight(25);
            },
        ];
    }
}
