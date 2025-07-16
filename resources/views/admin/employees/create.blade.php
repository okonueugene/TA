<div class="modal-dialog modal-dialog-scrollable" role="document">
    <div class="modal-content">
        <!-- Modal Header -->
        <div class="modal-header">
            <h5 class="modal-title">Add Employee</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <!-- Modal Body -->
        <div class="modal-body">
            <!-- REMOVED class="form" to avoid conflict with global handler -->
            <form action="{{ url('/admin/employees') }}" method="POST" id="employee-form">
                @csrf
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">PIN <span class="text-danger">*</span></label>
                        <div class="form-control-wrap">
                            <input type="number" class="form-control" name="pin" id="pin" min="1" required>
                            <div class="form-note text-muted">Enter a unique PIN number for the employee</div>
                            <div id="pin-feedback" class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <div class="form-control-wrap">
                            <input type="text" class="form-control" name="empname" required>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Gender <span class="text-danger">*</span></label>
                        <div class="form-control-wrap">
                            <select class="form-control" name="empgender" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Occupation <span class="text-danger">*</span></label>
                        <div class="form-control-wrap">
                            <input type="text" class="form-control" name="empoccupation" required>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Phone</label>
                        <div class="form-control-wrap">
                            <input type="text" class="form-control" name="empphone" maxlength="20">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Residence <span class="text-danger">*</span></label>
                        <div class="form-control-wrap">
                            <input type="text" class="form-control" name="empresidence" required>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Team <span class="text-danger">*</span></label>
                        <div class="form-control-wrap">
                            <input type="text" class="form-control" name="team" required>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Status</label>
                        <div class="form-control-wrap">
                            <select class="form-control" name="status">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Account Number</label>
                        <div class="form-control-wrap">
                            <input type="text" class="form-control" name="acc_no" maxlength="100">
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <!-- Modal Footer -->
        <div class="modal-footer">
            <!-- Changed to use submit-btn class to match global handler expectations -->
            <button type="submit" class="btn btn-md btn-primary submit-btn" form="employee-form">Save</button>
            <button type="button" class="btn btn-md btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let pinValidationInProgress = false;
    let pinIsValid = false;

    // PIN validation with debounce
    let pinValidationTimeout;
    $('#pin').on('input', function() {
        clearTimeout(pinValidationTimeout);
        const pin = $(this).val();
        
        if (pin) {
            pinValidationTimeout = setTimeout(function() {
                checkPinExists(pin);
            }, 500); // Wait 500ms after user stops typing
        } else {
            clearPinValidation();
        }
    });

    function checkPinExists(pin) {
        pinValidationInProgress = true;
        pinIsValid = false;
        
        $.ajax({
            url: "{{ url('/admin/employees/check-pin') }}",
            type: 'POST',
            data: {
                pin: pin,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.exists) {
                    $('#pin').addClass('is-invalid').removeClass('is-valid');
                    $('#pin-feedback').text('An employee with this PIN already exists.');
                    pinIsValid = false;
                } else {
                    $('#pin').removeClass('is-invalid').addClass('is-valid');
                    $('#pin-feedback').text('PIN is available.');
                    pinIsValid = true;
                }
            },
            error: function() {
                // Clear validation state on error
                $('#pin').removeClass('is-valid is-invalid');
                $('#pin-feedback').text('Unable to validate PIN. Please try again.');
                pinIsValid = false;
            },
            complete: function() {
                pinValidationInProgress = false;
            }
        });
    }

    function clearPinValidation() {
        $('#pin').removeClass('is-valid is-invalid');
        $('#pin-feedback').text('');
        pinIsValid = false;
    }

    // Form submission with proper validation
    $('#employee-form').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitBtn = $('.submit-btn');
        const originalText = submitBtn.text();
        const pinValue = $('#pin').val();
        
        // Check if PIN validation is in progress
        if (pinValidationInProgress) {
            iziToast.warning({
                title: "Please Wait",
                message: "PIN validation in progress...",
                position: "topRight",
                timeout: 3000,
                transitionIn: "fadeInDown"
            });
            return;
        }
        
        // Check if PIN is provided
        if (!pinValue) {
            iziToast.error({
                title: "Error",
                message: "Please enter a PIN.",
                position: "topRight",
                timeout: 5000,
                transitionIn: "fadeInDown"
            });
            $('#pin').focus();
            return;
        }
        
        // Check if PIN is valid
        if (!pinIsValid) {
            iziToast.error({
                title: "Error",
                message: "Please enter a valid, unique PIN.",
                position: "topRight",
                timeout: 5000,
                transitionIn: "fadeInDown"
            });
            $('#pin').focus();
            return;
        }
        
        // Show loading state
        submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: form.serialize(),
            success: function(response) {
                if (response.success) {
                    iziToast.success({
                        title: "Success",
                        message: response.msg,
                        position: "topRight",
                        timeout: 5000,
                        transitionIn: "fadeInDown"
                    });
                    
                    // Hide the modal
                    $('.modal').modal('hide');
                    
                    // Reload DataTable
                    $('#page_table').DataTable().ajax.reload();
                    
                    // Reset the form and validation state
                    form[0].reset();
                    clearPinValidation();
                    pinIsValid = false;
                } else {
                    iziToast.error({
                        title: "Error",
                        message: response.msg,
                        position: "topRight",
                        timeout: 10000,
                        transitionIn: "fadeInDown"
                    });
                }
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while saving the employee.';
                
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = xhr.responseJSON.errors;
                    const errorMessages = [];
                    
                    $.each(errors, function(key, value) {
                        errorMessages.push(value[0]);
                    });
                    
                    errorMessage = errorMessages.join('<br>');
                } else if (xhr.responseJSON && xhr.responseJSON.msg) {
                    errorMessage = xhr.responseJSON.msg;
                }
                
                iziToast.error({
                    title: "Error",
                    message: errorMessage,
                    position: "topRight",
                    timeout: 10000,
                    transitionIn: "fadeInDown"
                });
            },
            complete: function() {
                // Restore button state
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Clear validation when modal is closed
    $('.modal').on('hidden.bs.modal', function() {
        clearPinValidation();
        pinIsValid = false;
        pinValidationInProgress = false;
    });
});
</script>