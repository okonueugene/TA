@extends('layouts.admin')

@section('content')
    <div class="nk-content">
        <div class="container-fluid">
            <div class="nk-content-inner">
                <div class="nk-content-body">
                    <div class="nk-block-head nk-block-head-sm">
                        <div class="nk-block-between">
                            <div class="nk-block-head-content">
                                <h3 class="nk-block-title page-title">Raw Clock Data</h3>
                                <div class="d-flex align-items-center mt-2">
                                    <label for="monthSelect" class="mr-2 mb-0">Month:</label>
                                    <select id="monthSelect" class="form-select form-select-sm w-auto">
                                        @for ($m = 1; $m <= 12; $m++)
                                            <option value="{{ $m }}" {{ $currentMonth == $m ? 'selected' : '' }}>
                                                {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                            </option>
                                        @endfor
                                    </select>
                                    <label for="yearSelect" class="mx-2 mb-0">Year:</label>
                                    <select id="yearSelect" class="form-select form-select-sm w-auto">
                                        @php $yearRange = range(date('Y') - 5, date('Y') + 5); @endphp
                                        @foreach ($yearRange as $year)
                                            <option value="{{ $year }}"
                                                {{ $currentYear == $year ? 'selected' : '' }}>
                                                {{ $year }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button id="applyMonthYear" class="btn btn-sm btn-primary ml-2">Apply</button>
                                </div>
                            </div>
                            <div class="nk-block-head-content">
                                <div class="toggle-wrap nk-block-tools-toggle">
                                    <a href="#" class="btn btn-icon btn-trigger toggle-expand me-n1"
                                        data-target="more-options">
                                        <em class="icon ni ni-more-v"></em>
                                    </a>
                                    <div class="toggle-expand-content" data-content="more-options">
                                        <ul class="nk-block-tools g-3">
                                            <li><a href="#" id="exportBtn" class="btn btn-white btn-outline-light"><em
                                                        class="icon ni ni-download-cloud"></em><span>Export</span></a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="nk-block">
                        <div class="card card-bordered card-stretch">
                            <div id="loadingSpinner" style="display:none; text-align:center; padding:20px;">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <p class="mt-2">Loading Clocking Data...</p>
                            </div>
                            <table class="table-striped table-bordered table-sm" id="page_table" style="width:100%">
                                <thead>
                                    <th class="nk-tb-col"><span>#</span></th>
                                    <th class="nk-tb-col"><span>Name</span></th>
                                    <th class="nk-tb-col tb-col-md"><span>Occupation</span></th>
                                    <th class="nk-tb-col tb-col-md"><span>Team</span></th>
                                    <th class="nk-tb-col tb-col-md"><span>Date</span></th>
                                    <th class="nk-tb-col tb-col-md"><span>In Time</span></th>
                                    <th class="nk-tb-col tb-col-md"><span>Out Time</span></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
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
            border-collapse: collapse;
            font-family: sans-serif;
        }

        .minimal-table th,
        .minimal-table td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .minimal-table th {
            font-weight: normal;
            color: #555;
        }

        .minimal-table tbody tr:last-child td {
            border-bottom: none;
        }

        .minimal-table thead,
        .minimal-table tbody tr {
            display: flex;
            width: 100%;
        }

        .minimal-table th,
        .minimal-table td {
            flex: 1;
            box-sizing: border-box;
        }

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
            // Function to get selected month and year
            function getSelectedMonthYear() {
                return {
                    month: $('#monthSelect').val(),
                    year: $('#yearSelect').val()
                };
            }

            // Initialize DataTable
            var clockingTable = $('#page_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('admin.clocks.index') }}",
                    data: function(d) {
                        const {
                            month,
                            year
                        } = getSelectedMonthYear();
                        d.month = month;
                        d.year = year;
                    }
                },
                columns: [{
                        data: 'employee_pin',
                        name: 'employee_pin'
                    },
                    {
                        data: 'empname',
                        name: 'empname'
                    },
                    {
                        data: 'empoccupation',
                        name: 'empoccupation'
                    },
                    {
                        data: 'team',
                        name: 'team'
                    },
                    {
                        data: 'datetime',
                        name: 'datetime'
                    },
                    {
                        data: 'clock_in_time',
                        name: 'clock_in_time',
                        orderable: true,
                        searchable: true
                    },
                    {
                        data: 'clock_out_time',
                        name: 'clock_out_time',
                        orderable: true,
                        searchable: true
                    }
                ],
                order: [
                    [5, 'desc']
                ], // Default order by date (column 5) descending
                language: {
                    processing: "<div id='loadingSpinner' style='display:block;'>Loading...</div>"
                }
            });

            // Apply month/year filter
            $('#applyMonthYear').on('click', function() {
                clockingTable.ajax.reload();
            });

 $('#exportBtn').on('click', function(e) {
    e.preventDefault();
    $('#loadingSpinner').show();

    const { month, year } = getSelectedMonthYear();
    const searchValue = clockingTable.search();
    let exportUrl = "{{ route('admin.clocks.export') }}?month=" + month + "&year=" + year;
    if (searchValue.trim()) {
        exportUrl += "&search=" + encodeURIComponent(searchValue);
    }

    fetch(exportUrl, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(errorData => { throw new Error(errorData.error || 'Export failed'); });
        }
        return response.blob();
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `clocks_report_${year}_${String(month).padStart(2, '0')}.xlsx`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    })
    .catch(error => {
        console.error('Export error:', error);
        alert('Error exporting data: ' + error.message);
    })
    .finally(() => {
        $('#loadingSpinner').hide();
    });
});
        });
    </script>
@endpush
