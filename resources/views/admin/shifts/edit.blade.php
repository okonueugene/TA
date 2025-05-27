<div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Edit Shift for {{ ucwords($shift->employee->empname ?? 'N/A') }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <form action="{{ url('/admin/shifts/' . $shift->id) }}" method="POST" class="form" id="editShiftForm">
                @csrf
                @method('PUT')

                {{-- Crucial Identifying Bits (Read-Only) --}}
                <h6 class="mb-3 text-primary">Shift Details (Read-Only)</h6>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Employee Name:</label>
                        <p class="form-control-plaintext">{{ ucwords($shift->employee->empname ?? 'N/A') }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Employee PIN:</label>
                        <p class="form-control-plaintext">{{ $shift->employee_pin }}</p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Shift Date:</label>
                        <p class="form-control-plaintext">{{ $shift->shift_date ? $shift->shift_date->format('Y-m-d (D)') : 'N/A' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Clock-in Time:</label>
                        <p class="form-control-plaintext">{{ $shift->clock_in_time ? $shift->clock_in_time->format('h:i A') : '--:--' }}</p>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Clock-in Attendance ID:</label>
                        <p class="form-control-plaintext">{{ $shift->clock_in_attendance_id ?? 'N/A' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Clock-out Attendance ID:</label>
                        <p class="form-control-plaintext">{{ $shift->clock_out_attendance_id ?? 'N/A' }}</p>
                    </div>
                </div>

                <hr>

                {{-- Editable Fields --}}
                <h6 class="mb-3 text-success">Editable Fields</h6>

                <div class="form-group mb-3">
                    <label for="clock_out_time" class="form-label">Clock-out Time:</label>
                    <input type="time" class="form-control" id="clock_out_time" name="clock_out_time"
                           value="{{ $shift->clock_out_time ? $shift->clock_out_time->format('H:i') : '' }}">
                    @error('clock_out_time')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_holiday" name="is_holiday" value="1" {{ $shift->is_holiday ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_holiday">
                            Is Holiday?
                        </label>
                    </div>
                    @error('is_holiday')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group mb-3">
                    <label for="shift_type" class="form-label">Shift Type:</label>
                    <select class="form-control" id="shift_type" name="shift_type">
                        @php
                            $shiftTypes = [
                                'day' => 'Day Shift',
                                'standard_night' => 'Standard Night Shift',
                                'specific_pattern_night' => 'Specific Night Shift Pattern',
                                'missing_clockin' => 'Missing Clock-in',
                                'missing_clockout' => 'Missing Clock-out',
                                'inverted_times' => 'Inverted Times',
                                'lookahead_inverted' => 'Lookahead Inverted',
                                'human_error_day' => 'Human Error - Day Shift',
                                'human_error_inverted' => 'Human Error - Inverted Times',
                                'unknown' => 'Unknown' // Add 'unknown' if it's a possible type
                            ];
                        @endphp
                        @foreach($shiftTypes as $value => $label)
                            <option value="{{ $value }}" {{ $shift->shift_type == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('shift_type')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_complete" name="is_complete" value="1" {{ $shift->is_complete ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_complete">
                            Is Complete?
                        </label>
                    </div>
                    @error('is_complete')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group mb-3">
                    <label for="notes" class="form-label">Notes:</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3">{{ $shift->notes }}</textarea>
                    @error('notes')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <hr>

                {{-- Derived Calculated Fields (Display Current Value, Read-Only) --}}
                <h6 class="mb-3 text-warning">Calculated Fields (System-Derived)</h6>
                <p class="text-muted small">These values will be re-calculated by the system after you save based on your changes to "Clock-out Time" and "Shift Type".</p>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Hours Worked (Current):</label>
                        <p class="form-control-plaintext">{{ $shift->hours_worked !== null ? number_format($shift->hours_worked, 2) . ' hours' : '--' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Overtime Hours (Current):</label>
                        <p class="form-control-plaintext">{{ $shift->overtime_hours !== null ? number_format($shift->overtime_hours, 2) . ' hours' : '--' }}</p>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>