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
                                {{-- Optional: Add month/year navigation here --}}
                                {{-- Example: Add dropdowns for month and year --}}
                                <div class="d-flex align-items-center mt-2">
                                     <label for="monthSelect" class="mr-2 mb-0">Month:</label>
                                     <select id="monthSelect" class="form-select form-select-sm w-auto">
                                         @for ($m = 1; $m <= 12; $m++)
                                             <option value="{{ $m }}" {{ (isset($currentMonth) && $currentMonth == $m) ? 'selected' : '' }}>
                                                 {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                             </option>
                                         @endfor
                                     </select>
                                     <label for="yearSelect" class="mx-2 mb-0">Year:</label>
                                     <select id="yearSelect" class="form-select form-select-sm w-auto">
                                         @php $yearRange = range(date('Y') - 5, date('Y') + 5); @endphp {{-- Adjust year range as needed --}}
                                         @foreach ($yearRange as $year)
                                             <option value="{{ $year }}" {{ (isset($currentYear) && $currentYear == $year) ? 'selected' : '' }}>
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
                                            <li><a href="#" class="btn btn-white btn-outline-light"><em
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
                            <table class="cell-border stripe row-border hover" id="page_table" style="width:100%">
                                <thead>
                                    <tr class="nk-tb-item nk-tb-head">
                                        {{-- Checkbox column (optional for selection) --}}
                                        <th class="nk-tb-col nk-tb-col-check">
                                            <div class="custom-control custom-control-sm custom-checkbox notext">
                                                <input type="checkbox" class="custom-control-input" id="uid">
                                                <label class="custom-control-label" for="uid"></label>
                                            </div>
                                        </th>
                                        {{-- Employee Information Columns --}}
                                        <th class="nk-tb-col"><span>Name</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Days Present</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Total Work Days</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Day Shifts</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Night Shifts</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Holiday Day Shifts</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Holiday Night Shifts</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Overtime Hours</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Total Hours</span></th>


                                        {{-- Actions Column --}}
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

    @push('scripts')
        <script>
            $(document).ready(function() {

                // Function to get current month and year from dropdowns or URL
                function getSelectedMonthYear() {
                     const month = $('#monthSelect').val() || new URLSearchParams(window.location.search).get('month') || {{ now()->month }};
                     const year = $('#yearSelect').val() || new URLSearchParams(window.location.search).get('year') || {{ now()->year }};
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


                var columns = [
                    // Checkbox column (assuming you handle this in controller/JS if needed)
                    {
                        data: 'pin', // Assuming pin is used as a unique identifier or is desired here
                        name: 'employees.pin', // Use the database column name for sorting/searching
                        orderable: false, // Checkbox column usually not orderable
                        searchable: true, // Make pin searchable
                         // Optional: Render checkbox or leave blank if controller handles it
                         render: function(data, type, row) {
                             return '<div class="custom-control custom-control-sm custom-checkbox notext">' +
                                    '<input type="checkbox" class="custom-control-input uid" id="uid-' + data + '" value="' + data + '">' +
                                    '<label class="custom-control-label" for="uid-' + data + '"></label>' +
                                    '</div>';
                         }
                    },
                    // Employee Information Columns
                    {
                        data: 'empname',
                        name: 'employees.empname' // Use the database column name for server-side processing
                    },
                
                    // Summary Columns - Ensure names match the aliases from the aggregated query
                    {
                        data: 'days_present',
                        name: 'days_present' // This name should match the alias from DB::raw
                    },
                    {
                        data: 'total_work_days_in_month', // Updated name to match my controller suggestion
                         // This column is added via addColumn in the controller, name matches
                        name: 'total_work_days_in_month',
                         orderable: false, // Usually not orderable as it's a fixed value
                         searchable: false // Usually not searchable
                    },
                    {
                        data: 'day_shifts',
                        name: 'day_shifts' // This name should match the alias
                    },
                     {
                        data: 'night_shifts',
                        name: 'night_shifts' // This name should match the alias
                     },
                     {
                        data: 'holiday_day_shifts',
                        name: 'holiday_day_shifts' // This name should match the alias
                     },
                     {
                        data: 'holiday_night_shifts',
                        name: 'holiday_night_shifts' // This name should match the alias
                     },
                     {
                        data: 'total_overtime_hours', // Updated name to match my controller suggestion
                        name: 'total_overtime_hours', // This name should match the alias
                         // Optional: Render to format the float value if needed
                         render: function(data) { return parseFloat(data).toFixed(2); }
                     },
                     {
                        data: 'total_total_hours', // Updated name to match my controller suggestion
                        name: 'total_total_hours', // This name should match the alias
                         // Optional: Render to format the float value if needed
                         render: function(data) { return parseFloat(data).toFixed(2); }
                     },

                    // Actions Column
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false
                    },
                ];

                // Initialize DataTable
                var attendanceTable = $('#page_table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: buildAjaxUrl(), // Use the function to get the URL
                        type: 'GET'
                    },
                    columns: columns,
                     // Adjust the default ordering if necessary, e.g., order by employee name
                    "order": [
                        [1, 'asc'] // Order by the second column (Name) ascending
                    ]
                });

                // Handle month/year selection change
                $('#applyMonthYear').on('click', function() {
                    // Reload the DataTable with the new month and year from the dropdowns
                    attendanceTable.ajax.url(buildAjaxUrl()).load();
                });

                // Optional: Add event listener for the master checkbox if you want multi-select functionality
                $('#uid').on('click', function(){
                    // Logic to check/uncheck all individual checkboxes
                    $('.uid').prop('checked', $(this).prop('checked'));
                });

            });
        </script>
    @endpush