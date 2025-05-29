<div class="nk-content ">
    <div class="container-fluid">
        <div class="nk-content-inner">
            <div class="nk-content-body">
                <div class="nk-block-head nk-block-head-sm">
                    <div class="nk-block-between">
                        <div class="nk-block-head-content">
                            <h3 class="nk-block-title page-title">Dashboard</h3>
                            <div class="nk-block-des text-soft">
                            </div>
                        </div>
                        <div class="nk-block-head-content">
                            <div class="toggle-wrap nk-block-tools-toggle"><span
                                    class="badge rounded-pill bg-warning text-dark">{{ date_format(date_create(), 'F') }}</span>
                                <span
                                    class="badge rounded-pill bg-warning text-dark">{{ date('L') == 1 ? 366 - (date('z') + 1) : 365 - (date('z') + 1) }}
                                    Days Left </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="nk-block">
                    <div class="row mb-6">
                        <div class="col-12 col-md-4 mt-4">
                            <div class="card card-bordered h-60">
                                <div class="card-inner">
                                    <div class="project">
                                        <div class="card-header border-bottom text-center">
                                            <h6 class="title">Employees This Month</h6>
                                        </div><br>
                                        <div class="project-details text-center" style="text-size:15px;">
                                            <span>{{ count($presentEmployeesMonth) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4 mt-4">
                            <div class="card card-bordered h-60 text-center">
                                <div class="card-inner">
                                    <div class="project">
                                        <div class="card-header border-bottom text-center">
                                            <h6 class="title">Worked Days This Month</h6>
                                        </div><br>
                                        <div class="project-details text-center" style="text-size:15px;">
                                            <span>{{ $daysWorked }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4 mt-4">
                            <div class="card card-bordered h-60">
                                <div class="card-inner">
                                    <div class="project">
                                        <div class="card-header border-bottom text-center">
                                            <h6 class="title">Total Days This Month</h6>
                                        </div><br>
                                        <div class="project-details text-center" style="text-size:15px;">
                                            <span>{{ $businessDays }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card card-bordered h-100">
                                <div class="card-inner">
                                    <h6 class="title text-center mb-3">Attendance Logs</h6>
                                    <canvas id="attLogsChart" height="250"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card card-bordered h-100">
                                <div class="card-inner">
                                    <h6 class="title text-center mb-3">User Templates</h6>
                                    <canvas id="templatesChart" height="250"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-4">
                        <!-- Doughnut Chart (Clock-in/Clock-out) -->
                        <div class="col-md-6">
                            <div class="card card-bordered h-100">
                                <div class="card-inner">
                                    <h6 class="title text-center mb-3">Clock-in vs Clock-out</h6>
                                    <canvas id="doughnutChart" height="250"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Line Chart (Attendance Over the Week) -->
                        <div class="col-md-6">
                            <div class="card card-bordered h-100">
                                <div class="card-inner">
                                    <h6 class="title text-center mb-3">Weekly Attendance</h6>
                                    <canvas id="lineChart" height="250"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@section('scripts')
    <script>
        // Doughnut Chart (Clock-in vs Clock-out)
        const doughnutCtx = document.getElementById('doughnutChart').getContext('2d');
        new Chart(doughnutCtx, {
            type: 'doughnut',
            data: {
                labels: ['Clock-ins', 'Clock-outs'],
                datasets: [{
                    data: [{{ $clockins }}, {{ $clockouts }}],
                    backgroundColor: ['#36A2EB', '#FF9F40'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Line Chart (Weekly Attendance)
        const lineCtx = document.getElementById('lineChart').getContext('2d');
        new Chart(lineCtx, {
            type: 'line',
            data: {
                labels: {!! json_encode($weekDays) !!},
                datasets: [{
                    label: 'Clock-ins Per Day',
                    data: {!! json_encode($weeklyAttendance) !!},
                    fill: false,
                    borderColor: '#4CAF50',
                    backgroundColor: '#4CAF50',
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });

        // Ensure deviceStatus is available and parsed correctly
    // This is crucial. Make sure your controller passes $deviceStatus as a JSON string
    // or as a PHP array that Blade can convert to JSON.
    // Assuming $deviceStatus is passed as an array:
    const deviceStatusData = @json($deviceStatus ?? []);

    // Extract data for Attendance Logs Chart
    const attLogsStored = parseInt(deviceStatusData.att_logs_stored || 0);
    const attLogsAvailable = parseInt(deviceStatusData.att_logs_available || 0);

    // Extract data for Templates Chart
    const templatesStored = parseInt(deviceStatusData.templates_stored || 0);
    const templatesAvailable = parseInt(deviceStatusData.templates_available || 0);

    // --- Attendance Logs Doughnut Chart ---
    const attLogsCtx = document.getElementById('attLogsChart').getContext('2d');
    new Chart(attLogsCtx, {
        type: 'doughnut',
        data: {
            labels: ['Stored', 'Available'],
            datasets: [{
                data: [attLogsStored, attLogsAvailable],
                backgroundColor: ['#36A2EB', '#FFCE56'], // Blue for stored, Yellow for available
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                title: { // Add a title to the chart itself
                    display: true,
                    text: `Total Capacity: ${parseInt(deviceStatusData.att_logs_capacity || 0)} records`
                }
            }
        }
    });

    // --- Templates Doughnut Chart ---
    const templatesCtx = document.getElementById('templatesChart').getContext('2d');
    new Chart(templatesCtx, {
        type: 'doughnut',
        data: {
            labels: ['Stored', 'Available'],
            datasets: [{
                data: [templatesStored, templatesAvailable],
                backgroundColor: ['#FF6384', '#4BC0C0'], // Red for stored, Green for available
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                title: { // Add a title to the chart itself
                    display: true,
                    text: `Total Capacity: ${parseInt(deviceStatusData.templates_capacity || 0)} records`
                }
            }
        }
    });
    </script>
@endsection
