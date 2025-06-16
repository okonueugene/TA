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
        
        /* Custom styles for better event display */
        .fc-daygrid-day {
            min-height: 120px !important; /* Increase cell height */
        }
        
        .fc-event {
            font-size: 10px !important; /* Smaller font for events */
            margin: 1px 0 !important; /* Small margin between events */
            padding: 1px 3px !important; /* Compact padding */
            border-radius: 3px !important;
        }
        
        .fc-event-title {
            font-weight: 500 !important;
        }
        
        /* Hide dates from other months */
        .fc-daygrid-day.fc-day-other {
            display: none !important;
        }
        
        /* Ensure proper spacing for multiple events */
        .fc-daygrid-event-harness {
            margin-bottom: 1px !important;
        }
        
        /* Make sure events don't overlap */
        .fc-daygrid-event {
            margin-bottom: 1px !important;
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
            showNonCurrentDates: false, // This should hide other month dates
            fixedWeekCount: false, // This prevents showing 6 weeks always
            height: 'auto',
            aspectRatio: 1.8, // Adjust this to control calendar proportions
            dayMaxEvents: false, // Show all events instead of limiting
            moreLinkClick: 'popover', // Show popover when there are many events
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: ''
            },
            // Group events by date and display them compactly
            eventDisplay: 'block',
            eventMaxStack: 3, // Limit visible events per day
            dayMaxEventRows: 4, // Show up to 4 rows of events per day
            events: [
                @foreach($attendances as $date => $records)
                    @foreach($records as $attendance)
                        {
                            title: '{{ substr($attendance->pin, 0, 1) == 1 ? "🟢" : "🔴" }} {{ substr($attendance->pin, 0, 1) == 1 ? "Clock-In" : "Clock-Out" }}: {{ \Carbon\Carbon::parse($attendance->datetime)->format('H:i') }}',
                            start: '{{ \Carbon\Carbon::parse($attendance->datetime)->format('Y-m-d') }}',
                            allDay: true, // Make events all-day to fit better
                            backgroundColor: '{{ substr($attendance->pin, 0, 1) == 1 ? "#28a745" : "#dc3545" }}',
                            borderColor: '{{ substr($attendance->pin, 0, 1) == 1 ? "#28a745" : "#dc3545" }}',
                            textColor: '#ffffff',
                            extendedProps: {
                                time: '{{ \Carbon\Carbon::parse($attendance->datetime)->format('H:i:s') }}',
                                type: '{{ substr($attendance->pin, 0, 1) == 1 ? "Clock In" : "Clock Out" }}'
                            }
                        },
                    @endforeach
                @endforeach
            ],
            eventClick: function(info) {
                const eventTitle = info.event.extendedProps.type;
                const eventTime = info.event.extendedProps.time;
                const eventDate = info.event.start.toLocaleDateString();
                
                alert(`${eventTitle}\nDate: ${eventDate}\nTime: ${eventTime}`);
            },
            // Custom rendering for days from other months
            dayCellDidMount: function(info) {
                // Hide cells that are not in the current month
                if (!info.date.getMonth() === calendar.getDate().getMonth()) {
                    info.el.style.display = 'none';
                }
            }
        });
   
        calendar.render();
    });
</script>
@endpush