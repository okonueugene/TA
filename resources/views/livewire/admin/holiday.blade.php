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
                                            <h3 class="nk-block-title page-title">Holidays</h3>
                                        </div><!-- .nk-block-head-content -->
                                        <div class="nk-block-head-content">
                                            <div class="toggle-wrap nk-block-tools-toggle">
                                                <a href="#" class="btn btn-icon btn-trigger toggle-expand mr-n1"
                                                    data-target="pageMenu"><em class="icon ni ni-more-v"></em></a>
                                                <div class="toggle-expand-content" data-content="pageMenu">
                                                    <ul class="nk-block-tools g-3">
                                                        <div class="card-tools">
                                                            <a href="#" class="btn btn-md btn-primary"
                                                                data-toggle="modal" data-target="#addModal"><em
                                                                    class="icon ni ni-plus"></em>
                                                                <span>Add Holiday</span>
                                                            </a>
                                                        </div>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div><!-- .nk-block-head-content -->
                                    </div><!-- .nk-block-between -->
                                </div><!-- .nk-block-head -->
                                <div class="nk-block">
                                    <div id="calendar"></div>
                                </div><!-- .nk-block -->

                            </div>
                        </div><!-- .card-aside-wrap -->
                    </div><!-- .card -->
                </div><!-- .nk-block -->
            </div>
        </div>
    </div>
    {{-- Add Dob Modal --}}
    <div wire:ignore.self class="modal fade" id="addModal">
        <div class="modal-dialog modal-lg modal-dialog-top" role="document">
            <div class="modal-content">
                <a href="#" class="close" data-dismiss="modal" aria-label="Close">
                    <em class="icon ni ni-cross-sm"></em>
                </a>
                <div class="modal-body modal-body-md">
                    <h5 class="modal-title">Add Holiday</h5>
                    <form wire:submit="addHoliday" class="mt-2">
                        <div class="row g-gs">

                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="form-label" for="oder-id">Holiday Name</label>
                                    <div class="form-control-wrap">
                                        <input wire:model="summary" type="text" name="summary" class="form-control"
                                            id="oder-id" placeholder="Enter Holiday Name">
                                    </div>
                                    @error('summary')
                                        <div class="form-note text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label" for="oder-id">Holiday Type</label>
                                <div class="form-group">
                                    <input wire:model="description" name="description" type="text"
                                        class="form-control" id="oder-id" placeholder="Enter Holiday Description">
                                </div>
                                @error('description')
                                    <div class="form-note text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="form-label" for="order-id">Start Date</label>
                                    <div class="form-control-wrap">
                                        <input wire:model="start_date" name= "start_date" type="date"
                                            class="form-control" id="order-id" placeholder="Enter Start Date">
                                    </div>
                                    @error('start_date')
                                        <div class="form-note text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="form-label" for="order-id">End Date</label>
                                    <div class="form-control-wrap">
                                        <input wire:model="end_date" name="end_date" type="date" class="form-control"
                                            id="order-id" placeholder="Enter End Date">
                                    </div>
                                    @error('end_date')
                                        <div class="form-note text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-secondary">
                                    <div wire:submit='addHoliday'>
                                    </div>Add
                                </button>

                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- .Add Modal-Content -->
</div>
@push('styles')
    <style>
        /* Add CSS styles for different event types */
        .observance-event {
            text-align: center;
            font-family: Arial;
            font-style: italic;
        }

        .public-event {
            text-align: center;
            font-family: Arial;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Retrieve holidays
            let holidays = <?php echo json_encode($holidays); ?>;

            // Map holidays to FullCalendar events with different classes
            let holidayEvents = holidays.map(holiday => {
                // Determine the event class based on the description
                let eventClass = holiday.description.toLowerCase().includes("observance") ?
                    "observance-event" :
                    "public-event";

                // Return the event object
                return {
                    title: holiday.summary,
                    start: holiday.start_date,
                    end: holiday.end_date,
                    className: eventClass,
                };
            });
            console.log(holidayEvents)
            // Initialize FullCalendar
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                events: holidayEvents,
            });

            // Render the calendar
            calendar.render();
        });
    </script>
@endpush
