<?php
namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Models\Employee;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ClocksExport;
use App\Http\Controllers\Controller;
use App\Models\Attendance;


class ClocksController extends Controller
{
    public function index(Request $request)
    {
        $currentMonth = $request->get('month', now()->month);
        $currentYear = $request->get('year', now()->year);
        $startDate = Carbon::createFromDate($currentYear, $currentMonth, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        if ($request->ajax()) {
            $query = Employee::select([
                'employees.pin as employee_pin',
                'employees.empname',
                'employees.empoccupation',
                'employees.team',
                \DB::raw('DATE(attendances.datetime) as datetime'),
                \DB::raw("MIN(CASE WHEN LEFT(attendances.pin, 1) = '1' THEN attendances.datetime ELSE NULL END) as clock_in_time"),
                \DB::raw("MAX(CASE WHEN LEFT(attendances.pin, 1) = '2' THEN attendances.datetime ELSE NULL END) as clock_out_time"),
            ])
            ->join('attendances', function ($join) {
                $join->on(\DB::raw('SUBSTRING(attendances.pin, 2)'), '=', 'employees.pin');
            })
            ->whereBetween('attendances.datetime', [$startDate, $endDate->endOfDay()])
            ->groupBy('employees.pin', 'employees.empname', 'employees.empoccupation', 'employees.team', \DB::raw('DATE(attendances.datetime)'));

            // Global search
            if ($request->filled('search.value')) {
                $searchValue = $request->input('search.value');
                $query->havingRaw(
                    "employee_pin LIKE ? OR empname LIKE ? OR empoccupation LIKE ? OR team LIKE ? OR datetime LIKE ? OR DATE_FORMAT(clock_in_time, '%H:%i:%s') LIKE ? OR DATE_FORMAT(clock_out_time, '%H:%i:%s') LIKE ?",
                    array_fill(0, 7, '%' . $searchValue . '%')
                );
            }

            // Individual column searches
            $columns = $request->input('columns', []);
            foreach ($columns as $column) {
                if (!empty($column['search']['value'])) {
                    $searchValue = $column['search']['value'];
                    switch ($column['data']) {
                        case 'employee_pin':
                            $query->where('employees.pin', 'like', '%' . $searchValue . '%');
                            break;
                        case 'empname':
                            $query->where('employees.empname', 'like', '%' . $searchValue . '%');
                            break;
                        case 'empoccupation':
                            $query->where('employees.empoccupation', 'like', '%' . $searchValue . '%');
                            break;
                        case 'team':
                            $query->where('employees.team', 'like', '%' . $searchValue . '%');
                            break;
                        case 'datetime':
                            $query->where(\DB::raw('DATE(attendances.datetime)'), 'like', '%' . $searchValue . '%');
                            break;
                        case 'clock_in_time':
                            $query->havingRaw("DATE_FORMAT(clock_in_time, '%H:%i:%s') LIKE ?", ['%' . $searchValue . '%']);
                            break;
                        case 'clock_out_time':
                            $query->havingRaw("DATE_FORMAT(clock_out_time, '%H:%i:%s') LIKE ?", ['%' . $searchValue . '%']);
                            break;
                    }
                }
            }

            // Ordering
            if ($request->filled('order')) {
                $orderColumnIndex = $request->input('order.0.column');
                $orderDir = $request->input('order.0.dir', 'asc');
                $columnName = $columns[$orderColumnIndex]['data'] ?? 'datetime';
                $orderMap = [
                    'employee_pin' => 'employees.pin',
                    'empname' => 'employees.empname',
                    'empoccupation' => 'employees.empoccupation',
                    'team' => 'employees.team',
                    'datetime' => 'datetime',
                    'clock_in_time' => 'clock_in_time',
                    'clock_out_time' => 'clock_out_time',
                ];
                $query->orderBy($orderMap[$columnName] ?? 'datetime', $orderDir);
            } else {
                $query->orderBy('datetime', 'desc');
            }

            return DataTables::eloquent($query)
                ->editColumn('datetime', fn($row) => Carbon::parse($row->datetime)->format('Y-m-d'))
                ->editColumn('clock_in_time', fn($row) => $row->clock_in_time ? Carbon::parse($row->clock_in_time)->format('H:i:s') : 'N/A')
                ->editColumn('clock_out_time', fn($row) => $row->clock_out_time ? Carbon::parse($row->clock_out_time)->format('H:i:s') : 'N/A')
                ->editColumn('empname', fn($row) => ucwords($row->empname ?? ''))
                ->editColumn('empoccupation', fn($row) => ucwords($row->empoccupation ?? ''))
                ->editColumn('team', fn($row) => $row->team ?? 'N/A')
                ->make(true);
        }

        $title = "Raw Clock Data";
        return view('admin.clocks.index', compact('title', 'currentMonth', 'currentYear'));
    }

    public function show(Request $request, $pin)
    {
        $currentMonth = $request->get('month', now()->month);
        $currentYear = $request->get('year', now()->year);
        $title = "Clock Data for " . Employee::where('pin', $pin)->value('empname');
        return view('admin.clocks.show', compact('title', 'currentMonth', 'currentYear', 'pin'));
    }

    public function export(Request $request)
    {
        try {
            $currentMonth = $request->get('month', now()->month);
            $currentYear = $request->get('year', now()->year);
            $searchValue = $request->get('search');
            $filename = 'raw_clock_data_' . Carbon::create($currentYear, $currentMonth)->format('Y_m') . '.xlsx';

            activity()->causedBy(auth()->user())->event('export_raw_clocks')->log('Exported raw clock data report');
            return Excel::download(new ClocksExport($currentMonth, $currentYear, $searchValue), $filename);
        } catch (\Exception $e) {
            \Log::error('Clocks export error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to export clock data.'], 500);
        }
    }
}