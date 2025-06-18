@extends('layouts.admin')

@section('content')
    <div class="nk-content ">
        <div class="container-fluid">
            <div class="nk-content-inner">
                <div class="nk-content-body">
                    <div class="nk-block-head nk-block-head-sm">
                        <div class="nk-block-between">
                            <div class="nk-block-head-content">
                                <h3 class="nk-block-title page-title">Attendance</h3>
                                <div class="d-flex align-items-center mt-2">
                                    <label for="monthSelect" class="mr-2 mb-0">Month:</label>
                                    <select id="monthSelect" class="form-select form-select-sm w-auto">
                                        @for ($m = 1; $m <= 12; $m++)
                                            <option value="{{ $m }}"
                                                {{ isset($currentMonth) && $currentMonth == $m ? 'selected' : '' }}>
                                                {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                            </option>
                                        @endfor
                                    </select>
                                    <label for="yearSelect" class="mx-2 mb-0">Year:</label>
                                    <select id="yearSelect" class="form-select form-select-sm w-auto">
                                        @php $yearRange = range(date('Y') - 5, date('Y') + 5); @endphp
                                        @foreach ($yearRange as $year)
                                            <option value="{{ $year }}"
                                                {{ isset($currentYear) && $currentYear == $year ? 'selected' : '' }}>
                                                {{ $year }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button id="applyMonthYear" class="btn btn-sm btn-primary ml-2">Apply</button>
                                </div>
                            </div>
                            <div class="nk-block-head-content">
                                <div class="toggle-wrap nk-block-tools-toggle"><a href="#"
                                        class="btn btn-icon btn-trigger toggle-expand me-n1" data-target="more-options"><em
                                            class="icon ni ni-more-v"></em></a>
                                    <div class="toggle-expand-content" data-content="more-options">
                                        <ul class="nk-block-tools g-3">
                                            <li><a href="#" id="exportBtn" class="btn btn-white btn-outline-light"><em
                                                        class="icon ni ni-download-cloud"></em><span>Export</span></a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="nk-block">
                        <div class="card card-bordered card-stretch">
                            {{-- Loading Spinner Container --}}
                            <div id="loadingSpinner" style="display: none; text-align: center; padding: 20px;">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <p class="mt-2">Loading attendance report...</p>
                            </div>

                            <table class="table-striped table-bordered table-sm table-responsive" id="page_table"
                                style="width:100%">
                                <thead>
                                    <tr class="nk-tb-item nk-tb-head">
                                        <th class="nk-tb-col nk-tb-col-check">
                                            <div class="custom-control custom-control-sm custom-checkbox notext">
                                                <input type="checkbox" class="custom-control-input" id="uid">
                                                <label class="custom-control-label" for="uid"></label>
                                            </div>
                                        </th>
                                        <th class="nk-tb-col"><span>Name</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Days Present</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Day Shifts</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Night Shifts</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Missing Clockouts</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Missing Clockins</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Holiday Day Shifts</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Holiday Night Shifts</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Overtime 1.5x</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Overtime 2x</span></th>
                                        <th class="nk-tb-col nk-tb-col-tools text-end">
                                            <span class="sub-text">Actions</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- DataTables will populate this tbody --}}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('styles')
    <style>
        .minimal-table {
            width: 100%;
            /* Occupy full width */
            border-collapse: collapse;
            /* Remove double borders */
            font-family: sans-serif;
            /* Clean, modern font */
        }

        .minimal-table th,
        .minimal-table td {
            padding: 8px 12px;
            /* Minimal padding */
            text-align: left;
            /* Align text consistently */
            border-bottom: 1px solid #eee;
            /* Light separator lines */
        }

        .minimal-table th {
            font-weight: normal;
            /* Less bold headers */
            color: #555;
            /* Softer header color */
        }

        .minimal-table tbody tr:last-child td {
            border-bottom: none;
            /* No border on the last row */
        }

        /* Proportionate sizing - flexbox for fluid columns */
        .minimal-table thead,
        .minimal-table tbody tr {
            display: flex;
            width: 100%;
        }

        .minimal-table th,
        .minimal-table td {
            flex: 1;
            /* Each column takes equal available space */
            box-sizing: border-box;
            /* Include padding and border in the element's total width and height */
        }

        /* Optional: Hover effect for a subtle touch */
        .minimal-table tbody tr:hover {
            background-color: #f9f9f9;
        }

        #loadingSpinner {
            position: fixed;
            top: 20%;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1050;
            background-color: rgba(255, 255, 255, 0.8);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
    </style>
@endpush

@push('scripts')
    <script>
$(document).ready(function() {
    // Function to get current month and year from dropdowns or URL
    function getSelectedMonthYear() {
        const month = $('#monthSelect').val() || new URLSearchParams(window.location.search).get('month') || {{ $currentMonth }};
        const year = $('#yearSelect').val() || new URLSearchParams(window.location.search).get('year') || {{ $currentYear }};
        return { month, year };
    }

    // Function to build the AJAX URL with month and year parameters
    function buildAjaxUrl() {
        const { month, year } = getSelectedMonthYear();
        let url = "{{ url('admin/attendance') }}";
        const params = new URLSearchParams();
        params.append('month', month);
        params.append('year', year);
        return url + '?' + params.toString();
    }

    var url = buildAjaxUrl();

    var columns = [
        // Checkbox column
        {
            data: 'pin',
            name: 'employees.pin',
            orderable: false,
            searchable: true,
            render: function(data, type, row) {
                return '<div class="custom-control custom-control-sm custom-checkbox notext">' +
                    '<input type="checkbox" class="custom-control-input uid" id="uid-' + data +
                    '" value="' + data + '">' +
                    '<label class="custom-control-label" for="uid-' + data + '"></label>' +
                    '</div>';
            }
        },
        // Employee Information Columns
        { data: 'empname', name: 'employees.empname' },
        // Summary Columns - Updated to match new field names
        { data: 'days_present', name: 'days_present', searchable: false },
        { data: 'day_shifts', name: 'day_shifts', searchable: false },
        { data: 'night_shifts', name: 'night_shifts', searchable: false },
        { data: 'missing_clockouts', name: 'missing_clockouts', searchable: false },
        { data: 'missing_clockins', name: 'missing_clockins', searchable: false },
        { data: 'holiday_day_shifts', name: 'holiday_day_shifts', searchable: false },
        { data: 'holiday_night_shifts', name: 'holiday_night_shifts', searchable: false },
        // Updated overtime columns to match new field names
        {
            data: 'overtime_1_5x',
            name: 'overtime_1_5x',
            searchable: false,
            render: function(data) {
                return parseFloat(data || 0).toFixed(2);
            }
        },
        {
            data: 'overtime_2_0x',
            name: 'overtime_2_0x',
            searchable: false,
            render: function(data) {
                return parseFloat(data || 0).toFixed(2);
            }
        },
        // Actions Column
        {
            data: 'action',
            name: 'action',
            orderable: false,
            searchable: false
        },
    ];

    var filters = {
        month: $('#monthSelect').val() || new URLSearchParams(window.location.search).get('month') || {{ $currentMonth }},
        year: $('#yearSelect').val() || new URLSearchParams(window.location.search).get('year') || {{ $currentYear }}
    };

    // Initialize DataTable - Make sure to store reference properly
    var attendanceTable = __initializePageTable(url, columns, filters);

    // Handle month/year selection change
    $('#applyMonthYear').on('click', function() {
        // Update the URL and reload the table
        var newUrl = buildAjaxUrl();
        attendanceTable.ajax.url(newUrl).load();
    });

    // Optional: Add event listener for the master checkbox
    $('#uid').on('click', function() {
        $('.uid').prop('checked', $(this).prop('checked'));
    });

    // Handle the Export button click - Fixed to use proper function scope
    $('#exportBtn').on('click', function(e) {
        e.preventDefault();

        // Show spinner
        $('#loadingSpinner').show();

        // Get selected month and year
        const { month, year } = getSelectedMonthYear();

        // Get current search value from DataTable
        const searchValue = attendanceTable ? attendanceTable.search() : '';

        // Build the export URL with search parameter
        let exportUrl = `{{ url('admin/attendances/export') }}?month=${month}&year=${year}`;
        if (searchValue && searchValue.trim() !== '') {
            exportUrl += `&search=${encodeURIComponent(searchValue)}`;
        }

        console.log('Export URL:', exportUrl); // Debug log

        // Use fetch to send a GET request to the export route
        fetch(exportUrl, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`HTTP error! status: ${response.status}, message: ${text}`);
                });
            }
            
            // Check if response is actually a file
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
                return response.text().then(text => {
                    throw new Error(`Expected Excel file but got: ${contentType}. Response: ${text}`);
                });
            }
            
            return response.blob();
        })
        .then(blob => {
            // Create a temporary link to trigger the download
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `attendance_report_${year}_${String(month).padStart(2, '0')}.xlsx`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url); // Clean up
            
            console.log('Download initiated successfully');
        })
        .catch(error => {
            console.error('Error exporting data:', error);
            alert('Failed to export data: ' + error.message);
        })
        .finally(() => {
            // Hide spinner
            $('#loadingSpinner').hide();
        });
    });
});
    </script>
@endpush
