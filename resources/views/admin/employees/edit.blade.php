<div class="modal-dialog modal-dialog-scrollable" role="document">
    <div class="modal-content">
        <!-- Modal Header -->
        <div class="modal-header">
            <h5 class="modal-title">Edit Employee</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <!-- Modal Body -->
        <div class="modal-body">
            <form action="{{ url('/admin/employees/' . $employee->id) }}" method="POST" class="form" id="edit-employee-form">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">PIN <span class="text-danger">*</span></label>
                        <div class="form-control-wrap">
                            <input type="number" class="form-control" name="pin" id="edit-pin" 
                                   value="{{ $employee->pin }}" min="1" required 
                                   data-original-pin="{{ $employee->pin }}">
                            <div class="form-note text-muted">Enter a unique PIN number for the employee</div>
                            <div id="edit-pin-feedback" class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <div class="form-control-wrap">
                            <input type="text" class="form-control" name="empname" 
                                   value="{{ $employee->empname }}" required>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Gender <span class="text-danger">*</span></label>
                        <div class="form-control-wrap">
                            <select class="form-control" name="empgender" required>
                                <option value="">Select Gender</option>
                                <option value="male" {{ strtolower($employee->empgender) == 'male' ? 'selected' : '' }}>Male</option>
                                <option value="female" {{ strtolower($employee->empgender) == 'female' ? 'selected' : '' }}>Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Occupation <span class="text-danger">*</span></label>
                        <div class="form-control-wrap">
                            <input type="text" class="form-control" name="empoccupation" 
                                   value="{{ $employee->empoccupation }}" required>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Phone</label>
                        <div class="form-control-wrap">
                            <input type="text" class="form-control" name="empphone" 
                                   value="{{ $employee->empphone }}" maxlength="20">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Residence <span class="text-danger">*</span></label>
                        <div class="form-control-wrap">
                            <input type="text" class="form-control" name="empresidence" 
                                   value="{{ $employee->empresidence }}" required>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Team <span class="text-danger">*</span></label>
                        <div class="form-control-wrap">
                            <input type="text" class="form-control" name="team" 
                                   value="{{ $employee->team }}" required>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Status</label>
                        <div class="form-control-wrap">
                            <select class="form-control" name="status">
                                <option value="active" {{ (strtolower($employee->status) == 'active' || $employee->status == '1') ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ (strtolower($employee->status) == 'inactive' || $employee->status == '0') ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Account Number</label>
                        <div class="form-control-wrap">
                            <input type="text" class="form-control" name="acc_no" 
                                   value="{{ $employee->acc_no }}" maxlength="100">
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <!-- Modal Footer -->
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary submit-btn" form="edit-employee-form">Save Changes</button>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // PIN validation for edit form
    $('#edit-pin').on('blur', function() {
        var pin = $(this).val();
        var originalPin = $(this).data('original-pin');
        
        if (pin && pin != originalPin) {
            checkPinExistsForEdit(pin, {{ $employee->id }});
        } else if (pin == originalPin) {
            // Reset validation if PIN is back to original
            $('#edit-pin').removeClass('is-invalid is-valid');
            $('#edit-pin-feedback').text('');
        }
    });

    function checkPinExistsForEdit(pin, employeeId) {
        $.ajax({
            url: "{{ url('/admin/employees/check-pin') }}",
            type: 'POST',
            data: {
                pin: pin,
                employee_id: employeeId,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.exists) {
                    $('#edit-pin').addClass('is-invalid');
                    $('#edit-pin-feedback').text('An employee with this PIN already exists.');
                } else {
                    $('#edit-pin').removeClass('is-invalid').addClass('is-valid');
                    $('#edit-pin-feedback').text('');
                }
            },
            error: function() {
                $('#edit-pin').removeClass('is-valid is-invalid');
                $('#edit-pin-feedback').text('');
            }
        });
    }

    // Form submission for edit
    $('#edit-employee-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = $('.submit-btn');
        var originalText = submitBtn.text();
        
        // Check if PIN is invalid
        if ($('#edit-pin').hasClass('is-invalid')) {
            toastr.error('Please enter a valid PIN before submitting.');
            return;
        }
        
        // Show loading state
        submitBtn.prop('disabled', true).text('Saving...');
        
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: form.serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(response.msg);
                    $('.modal').modal('hide');
                    if (typeof page_table !== 'undefined') {
                        page_table.ajax.reload();
                    }
                } else {
                    toastr.error(response.msg);
                }
            },
            error: function(xhr) {
                var errors = xhr.responseJSON.errors;
                if (errors) {
                    $.each(errors, function(key, value) {
                        toastr.error(value[0]);
                    });
                } else {
                    toastr.error('An error occurred while updating the employee.');
                }
            },
            complete: function() {
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
});
</script>