@extends('layouts.admin')
<head>
    <style>
        /* Target FullCalendar elements when the dark mode class is active */
        /* Ensure text is visible against a dark background */

        /* Days of the week headers (e.g., Sun, Mon) */
        .dark-mode .fc .fc-col-header-cell .fc-col-header-cell-cushion {
            color: #ffffff !important; /* White text */
        }

        /* Day numbers in the grid */
        .dark-mode .fc .fc-daygrid-day-number {
            color: #cccccc !important; /* Light grey numbers */
        }

        /* Grid cell borders */
        .dark-mode .fc .fc-daygrid-body-unbalanced .fc-daygrid-day,
        .dark-mode .fc .fc-daygrid-day,
        .dark-mode .fc .fc-scrollgrid {
            border-color: #454d55 !important; /* Darker border color */
        }

        /* Header background (if needed to change from default dark) */
         .dark-mode .fc .fc-col-header {
             background-color: #343a40 !important; /* Example dark background for header row */
         }

        /* Event text color within the blue events */
        /* (This should already be white from the JS, but double check) */
        .dark-mode .fc .fc-event-title {
             color: #ffffff !important;
        }

        /* Ensure the calendar background itself isn't clashing if not set by themeSystem */
        .dark-mode #attendance-calendar {
            background-color: #212529; /* Example dark background for calendar container */
            padding: 10px; /* Add some padding */
            border-radius: 5px;
        }
    </style>
</head>
@section('content')
    <div class="nk-content ">
        <div class="container-fluid">
            <div class="nk-content-inner">
                <div class="nk-content-body">
                    <div class="nk-block">
                        <div class="card card-bordered">
                            <div class="card-aside-wrap">
                                <div class="card-inner bg-lighter card-inner-lg">
                                    <div class="nk-block-head nk-block-head-sm">
                                        <div class="nk-block-between">
                                            <div class="nk-block-head-content">
                                                <h5 class="text-center">{{ ucwords($employee->empname) }}'s Attendance Record</h5>
                                            </div><!-- .nk-block-head-content -->
                                        </div><!-- .nk-block-between -->
                                    </div><!-- .nk-block-head -->
                                    <div class="nk-block">
                                        <div id="attendance-calendar"></div>
                                    </div><!-- .nk-block -->
                                </div>
                            </div><!-- .card-aside-wrap -->
                        </div><!-- .card -->
                    </div><!-- .nk-block -->
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('attendance-calendar');
    
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: ''
            },
            events: [
                @foreach($attendances as $date => $records)
                    @foreach($records as $attendance)
                        {
                            title: '{{ substr($attendance->pin, 0, 1) == 1 ? "Clock In" : "Clock Out" }}: {{ \Carbon\Carbon::parse($attendance->datetime)->format('H:i') }}',
                            start: '{{ \Carbon\Carbon::parse($attendance->datetime)->toIso8601String() }}',
                            allDay: false,
                            color: '{{ substr($attendance->pin, 0, 1) == 1 ? "#5DFA02" : "#D20103" }}'
                        },
                    @endforeach
                @endforeach
            ],
            eventClick: function(info) {
                alert(info.event.title + "\n" + new Date(info.event.start).toLocaleString());
            }
        });
    
        calendar.render();
    });
    </script>
@endpush
